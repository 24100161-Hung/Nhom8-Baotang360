// PERM: phân quyền theo vai trò, truy cập trái phép (RBAC/IDOR), liên kết ký số.
import { test, expect } from '@playwright/test';
import { asRole, actual, shot, showText, send, getPage, sql } from './helpers.js';

test('TC-PERM-001 khách vãng lai vào /vi/quan-tri → 302 tới đăng nhập, không lộ dữ liệu', async ({ page }, ti) => {
    const r = await page.request.get('/vi/quan-tri', { maxRedirects: 0 });
    const r2 = await page.request.get('/vi/quan-tri/nguoi-dung', { maxRedirects: 0 });
    const r3 = await page.request.get('/api/v1/me', { headers: { Accept: 'application/json' } });
    await page.goto('/vi/quan-tri');
    await shot(page, 'TC-PERM-001');
    actual(ti, `GET /vi/quan-tri → ${r.status()} ${r.headers()['location']}; /vi/quan-tri/nguoi-dung → ${r2.status()}; GET /api/v1/me không token → ${r3.status()}; trình duyệt dừng ở ${new URL(page.url()).pathname}.`);
    expect(r.status()).toBe(302);
    expect(r.headers()['location']).toContain('/vi/dang-nhap');
    expect(r3.status()).toBe(401);
});

test.describe('visitor', () => {
    test.use(asRole('visitor'));
    test('TC-PERM-002 visitor vào trang quản trị và gọi API ghi quản trị → 403', async ({ page }, ti) => {
        const res = await page.goto('/vi/quan-tri');
        const leak = /(Exception|vendor\/|Stack trace|\.php:)/i.test(await page.content());
        await shot(page, 'TC-PERM-002');
        const w1 = await send(page.request, 'PATCH', '/vi/quan-tri/danh-gia/1', { status: 'approved' }, { follow: false });
        const w2 = await send(page.request, 'DELETE', '/vi/quan-tri/hien-vat/1', {}, { follow: false });
        const w3 = await send(page.request, 'PATCH', '/vi/quan-tri/nguoi-dung/3', { role_id: 1, is_active: 1 }, { follow: false });
        const role = sql('SELECT r.code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=3')[0][0];
        actual(ti, `GET /vi/quan-tri → ${res.status()} (lộ thông tin kỹ thuật: ${leak ? 'có' : 'không'}); PATCH danh-gia/1 → ${w1.status}; DELETE hien-vat/1 → ${w2.status}; tự nâng quyền PATCH nguoi-dung/3 role_id=1 → ${w3.status}, vai trò vẫn là ${role}.`);
        expect(res.status()).toBe(403);
        expect([w1.status, w2.status, w3.status]).toEqual([403, 403, 403]);
        expect(role).toBe('visitor');
        expect(leak).toBe(false);
    });

    test('TC-PERM-005 visitor xem/hủy/thanh toán đơn của người khác (IDOR theo mã đơn) → bị chặn', async ({ page }, ti) => {
        const [[code]] = sql("SELECT o.code FROM orders o JOIN users u ON u.id=o.user_id WHERE u.email='visitor2@baotang360.test' ORDER BY o.id DESC LIMIT 1");
        const before = sql(`SELECT status FROM orders WHERE code='${code}'`)[0][0];
        const g = await page.goto(`/vi/don-hang/${code}`);
        await shot(page, 'TC-PERM-005');
        const c = await send(page.request, 'POST', `/vi/don-hang/${code}/huy`, {}, { follow: false });
        const p = await send(page.request, 'POST', `/vi/don-hang/${code}/thanh-toan`, { payment_method: 'card' }, { follow: false });
        const inv = await page.request.get(`/vi/don-hang/${code}/hoa-don`, { maxRedirects: 0 });
        const after = sql(`SELECT status FROM orders WHERE code='${code}'`)[0][0];
        actual(ti, `Đơn ${code} của visitor2: GET → ${g.status()}; POST huy → ${c.status}; POST thanh-toan → ${p.status}; hóa đơn không chữ ký → ${inv.status()}; trạng thái ${before} → ${after}.`);
        expect([403, 404]).toContain(g.status());
        expect([403, 404]).toContain(c.status);
        expect([403, 404]).toContain(p.status);
        expect(after).toBe(before);
    });

    test('TC-PERM-007 liên kết vé QR/hóa đơn: chữ ký bị sửa hoặc bỏ → 403', async ({ page }, ti) => {
        const [[code]] = sql("SELECT o.code FROM orders o JOIN users u ON u.id=o.user_id JOIN order_items oi ON oi.order_id=o.id JOIN tickets t ON t.order_item_id=oi.id WHERE u.email='visitor@baotang360.test' AND o.status='paid' ORDER BY o.id DESC LIMIT 1");
        const { page: p } = await getPage(page.request, `/vi/don-hang/${code}`);
        const qr = p.props.order.items[0].tickets[0].qr_url;
        const ok = await page.request.get(qr);
        const tampered = qr.replace(/signature=([0-9a-f])/, (m, c) => `signature=${c === 'a' ? 'b' : 'a'}`);
        const bad = await page.request.get(tampered);
        const nosig = await page.request.get(qr.split('?')[0]);
        const inv = p.props.order.invoice_url;
        const invOk = await page.request.get(inv);
        const invBad = await page.request.get(inv.replace(/expires=\d+/, 'expires=9999999999'));
        await page.goto(tampered);
        await shot(page, 'TC-PERM-007');
        actual(ti, `QR đúng chữ ký → ${ok.status()} ${ok.headers()['content-type']}; sửa 1 ký tự chữ ký → ${bad.status()}; bỏ chữ ký → ${nosig.status()}; hóa đơn ký → ${invOk.status()}; sửa expires → ${invBad.status()}. Mã vé/đơn là chuỗi ngẫu nhiên (${code}).`);
        expect(ok.status()).toBe(200);
        expect(bad.status()).toBe(403);
        expect(nosig.status()).toBe(403);
        expect(invBad.status()).toBe(403);
    });
});

test.describe('curator', () => {
    test.use(asRole('curator'));
    test('TC-PERM-003 curator vào gói vé, đơn hàng, người dùng, lịch vé, nhật ký → 403', async ({ page }, ti) => {
        const out = [];
        for (const u of ['/vi/quan-tri/goi-ve', '/vi/quan-tri/don-hang', '/vi/quan-tri/nguoi-dung', '/vi/quan-tri/lich-ve', '/vi/quan-tri/nhat-ky', '/vi/quan-tri/lien-he']) {
            out.push(`${u} → ${(await page.request.get(u, { maxRedirects: 0 })).status()}`);
        }
        const w = await send(page.request, 'PUT', '/vi/quan-tri/goi-ve/1', { code: 'regular', price_vnd: 1000 }, { follow: false });
        const price = sql('SELECT price_vnd FROM ticket_packages WHERE id=1')[0][0];
        await page.goto('/vi/quan-tri/goi-ve');
        await shot(page, 'TC-PERM-003');
        actual(ti, out.join('; ') + `; PUT goi-ve/1 giá 1000 → ${w.status}, giá vẫn ${price}.`);
        expect(out.every((l) => l.endsWith('403'))).toBe(true);
        expect(w.status).toBe(403);
        expect(price).toBe('150000');
    });

    test('TC-PERM-004 curator vào dashboard: thấy KPI nội dung, doanh thu/đơn hàng bị ẩn (null); vẫn quản lý được hiện vật', async ({ page }, ti) => {
        const { status, page: p } = await getPage(page.request, '/vi/quan-tri');
        const k = p.props.kpis;
        const art = (await page.request.get('/vi/quan-tri/hien-vat', { maxRedirects: 0 })).status();
        const tours = (await page.request.get('/vi/quan-tri/tour', { maxRedirects: 0 })).status();
        await page.goto('/vi/quan-tri');
        await shot(page, 'TC-PERM-004', { fullPage: true });
        actual(ti, `Dashboard ${status}; kpis.scenes=${k.scenes}, revenue_vnd=${k.revenue_vnd}, orders=${k.orders}, pending_orders=${k.pending_orders}; /quan-tri/hien-vat → ${art}; /quan-tri/tour → ${tours}.`);
        expect(status).toBe(200);
        expect(k.revenue_vnd).toBeNull();
        expect(k.orders).toBeNull();
        expect([art, tours]).toEqual([200, 200]);
    });
});

test('TC-PERM-006 khách vãng lai thêm giỏ được nhưng vào thanh toán/đặt hàng bị buộc đăng nhập', async ({ page }, ti) => {
    await page.goto('/vi/ve');
    const r1 = await page.request.get('/vi/thanh-toan', { maxRedirects: 0 });
    const r2 = await send(page.request, 'POST', '/vi/thanh-toan', { contact_name: 'Khách', contact_email: 'k@example.com', contact_phone: '0912345678', payment_method: 'card' }, { follow: false });
    const r3 = await send(page.request, 'POST', '/vi/yeu-thich', { type: 'artifact', id: 1 }, { follow: false });
    const r4 = await send(page.request, 'POST', '/vi/danh-gia', { subject_type: 'artifact', subject_id: 1, rating: 5, body: 'Tuyệt vời lắm' }, { follow: false });
    await page.goto('/vi/thanh-toan');
    await shot(page, 'TC-PERM-006');
    actual(ti, `GET /vi/thanh-toan → ${r1.status()} ${r1.headers()['location']}; POST thanh-toan → ${r2.status} ${r2.location}; POST yeu-thich → ${r3.status}; POST danh-gia → ${r4.status}; trình duyệt tới ${new URL(page.url()).pathname}.`);
    expect(r1.status()).toBe(302);
    expect(r1.headers()['location']).toContain('dang-nhap');
    expect(r2.location).toContain('dang-nhap');
    expect(r4.location).toContain('dang-nhap');
});
