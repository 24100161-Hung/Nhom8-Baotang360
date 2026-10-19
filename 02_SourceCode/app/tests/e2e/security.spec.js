// SEC: XSS lưu trữ, CSRF/headers/cookie, giới hạn tần suất (rà soát OWASP chạy tự động).
import { test, expect, request as pwRequest } from '@playwright/test';
import { asRole, authFile, actual, shot, showText, send, getPage, errorsOf, sql, rnd, openDay, clearCart, BASE } from './helpers.js';

const PAYLOAD = `<script>window.__xss=1</script><img src=x onerror="window.__xss=2"> "'><svg onload=window.__xss=3>`;

test('TC-SEC-001 XSS lưu trữ qua sổ lưu bút: payload script/onerror sau khi duyệt hiển thị dạng văn bản, không thực thi', async ({ page, browser }, ti) => {
    const tag = `QA-XSS-${rnd()}`;
    await page.goto('/vi/so-luu-but');
    const r = await send(page.request, 'POST', '/vi/so-luu-but', { display_name: `Khách ${tag}`, message: `${tag} ${PAYLOAD}` }, { referer: '/vi/so-luu-but' });
    const [[id, raw]] = sql(`SELECT id, message FROM guestbook_entries WHERE message LIKE '${tag}%'`);
    const admin = await browser.newContext({ storageState: authFile('admin') });
    await admin.request.get('/vi/quan-tri/so-luu-but');
    const m = await send(admin.request, 'PATCH', `/vi/quan-tri/so-luu-but/${id}`, { status: 'approved' }, { referer: '/vi/quan-tri/so-luu-but' });
    const dialogs = [];
    page.on('dialog', (d) => { dialogs.push(d.message()); d.dismiss(); });
    await page.goto('/vi/so-luu-but');
    await page.getByText(tag).first().scrollIntoViewIfNeeded();
    await page.waitForTimeout(1000);
    const xss = await page.evaluate(() => window.__xss ?? null);
    const shown = await page.getByText(tag).first().innerText();
    await shot(page, 'TC-SEC-001');
    // admin xem trang kiểm duyệt cũng không thực thi
    const ap = await admin.newPage();
    await ap.goto('/vi/quan-tri/so-luu-but?q=' + tag);
    await ap.waitForTimeout(800);
    const xssAdmin = await ap.evaluate(() => window.__xss ?? null);
    await send(admin.request, 'DELETE', `/vi/quan-tri/so-luu-but/${id}`, {}, { referer: '/vi/quan-tri/so-luu-but' });
    actual(ti, `Gửi → flash "${r.page?.props?.flash?.success}"; CSDL lưu nguyên văn (${raw.length} ký tự); admin duyệt → ${m.status}; trang công khai: window.__xss=${xss}, dialog=${dialogs.length}, hiển thị "${shown.slice(0, 80)}…"; trang quản trị: window.__xss=${xssAdmin}. Đã xóa mục thử.`);
    expect(xss).toBeNull();
    expect(xssAdmin).toBeNull();
    expect(shown).toContain('<script>');
    await admin.close();
});

test('TC-SEC-002 CSRF: POST thiếu/sai token → 419; header bảo mật (CSP nonce, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy); cookie phiên HttpOnly + SameSite=Lax', async ({ page }, ti) => {
    const fresh = await pwRequest.newContext({ baseURL: BASE });
    await fresh.get('/vi/lien-he');
    const noTok = await fresh.post('/vi/lien-he', { form: { name: 'CSRF', email: 'c@example.com', subject: 'CSRF test', body: 'Nội dung đủ dài cho kiểm thử' }, maxRedirects: 0 });
    const badTok = await fresh.post('/vi/lien-he', { form: { _token: 'sai-token', name: 'CSRF', email: 'c@example.com', subject: 'CSRF test', body: 'Nội dung đủ dài cho kiểm thử' }, headers: { 'X-XSRF-TOKEN': 'sai' }, maxRedirects: 0 });
    const res = await fresh.get('/vi');
    const h = res.headers();
    const raw = res.headersArray().filter((x) => x.name.toLowerCase() === 'set-cookie').map((x) => x.value);
    const sess = raw.find((c) => c.startsWith('baotang360-session')) || '';
    await fresh.dispose();
    const want = ['content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy', 'strict-transport-security'];
    const present = want.map((k) => `${k}: ${h[k] ? 'có' : 'KHÔNG'}`);
    await showText(page, 'TC-SEC-002', 'CSRF + headers + cookie', `POST /vi/lien-he không token → ${noTok.status()}\nToken sai → ${badTok.status()}\n${present.join('\n')}\nCSP: ${h['content-security-policy']}\nSet-Cookie phiên: ${sess.replace(/=[^;]+;/, '=…;')}`);
    actual(ti, `Không token → ${noTok.status()}, token sai → ${badTok.status()}; headers: ${present.join(', ')} (HSTS chỉ bật khi HTTPS); CSP có nonce: ${/nonce-/.test(h['content-security-policy'] || '')}, frame-ancestors 'none': ${/frame-ancestors 'none'/.test(h['content-security-policy'] || '')}; cookie phiên: httponly=${/httponly/i.test(sess)}, samesite=${(sess.match(/samesite=(\w+)/i) || [])[1]}, secure=${/secure/i.test(sess)} (HTTP cục bộ).`);
    expect(noTok.status()).toBe(419);
    expect(badTok.status()).toBe(419);
    for (const k of want.slice(0, 5)) expect(h[k], k).toBeTruthy();
    expect(sess).toMatch(/httponly/i);
});

test.describe('visitor2', () => {
    test.use(asRole('visitor2'));
    test('TC-SEC-003 giới hạn tần suất: API 120/phút → 429; giới hạn riêng của giỏ hàng không được làm khóa bước đặt hàng', async ({ page }, ti) => {
        const d = await openDay(page.request, 14);
        // API: 125 yêu cầu liên tiếp
        const api = await pwRequest.newContext({ baseURL: BASE });
        // Bộ đếm throttle:api theo IP dùng chung với các ca/worker khác gọi /api/v1 trong cùng phút
        // → đo từ X-RateLimit-Remaining của yêu cầu đầu: yêu cầu thứ (remaining + 2) phải nhận 429.
        const r0 = await api.get('/api/v1/packages');
        const limit = Number(r0.headers()['x-ratelimit-limit']);
        const used = limit - Number(r0.headers()['x-ratelimit-remaining']);
        const codes = [r0.status()];
        for (let i = 1; i < 125 && !codes.includes(429); i++) codes.push((await api.get('/api/v1/packages')).status());
        const first429 = codes.indexOf(429) + 1;
        const expected429 = limit - used + 2;
        const r429 = await api.get('/api/v1/packages');
        await api.dispose();
        // Người dùng thao tác giỏ hàng 11 lần trong 1 phút rồi đặt hàng
        await clearCart(page.request);
        let adds = 0;
        for (let i = 0; i < 11; i++) { await send(page.request, 'POST', '/vi/gio-hang', { package_id: 3, visit_date: d, quantity: 1 }, { referer: '/vi/ve', follow: false }); adds++; }
        const items = (await getPage(page.request, '/vi/gio-hang')).page.props.items.length;
        const co = await send(page.request, 'POST', '/vi/thanh-toan', { contact_name: 'QA', contact_email: 'qa@example.com', contact_phone: '0912345678', payment_method: 'card' }, { referer: '/vi/thanh-toan', follow: false });
        await page.goto('/vi/thanh-toan');
        await showText(page, 'TC-SEC-003', 'Rate limit', `API: yêu cầu thứ ${first429} nhận 429 đầu tiên; Retry-After=${r429.headers()['retry-after']}, X-RateLimit-Limit=${r429.headers()['x-ratelimit-limit']}\nGiỏ: ${adds} lần POST /vi/gio-hang, giỏ có ${items} dòng\nPOST /vi/thanh-toan → ${co.status} ${co.location}`);
        const m = co.location.match(/don-hang\/(OD-[A-Z0-9]+)/);
        if (m) await send(page.request, 'POST', `/vi/don-hang/${m[1]}/huy`, {}, { referer: `/vi/don-hang/${m[1]}` });
        await clearCart(page.request);
        actual(ti, `API /api/v1/packages: bộ đếm đã dùng ${used - 1} yêu cầu trước ca này; 429 đầu tiên ở yêu cầu thứ ${first429} của ca (kỳ vọng ${expected429}, tức yêu cầu thứ ${limit + 1} trong phút; X-RateLimit-Limit=${r429.headers()['x-ratelimit-limit']}); sau ${adds} lần thêm giỏ (throttle:30,1) trong < 1 phút, giỏ có ${items} dòng, POST /vi/thanh-toan (throttle:10,1) → ${co.status}${co.status === 429 ? ' Too Many Requests – bộ đếm throttle:N,1 dùng chung khóa theo người dùng giữa các route' : ' ' + co.location}.`);
        expect(limit).toBe(120);
        expect(first429).toBe(expected429);
        expect(co.status).not.toBe(429);
    });
});
