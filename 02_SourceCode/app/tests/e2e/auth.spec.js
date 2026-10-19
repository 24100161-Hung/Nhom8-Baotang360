// AUTH: đăng nhập, đăng ký, đăng xuất, quên mật khẩu, khóa tài khoản, giới hạn tần suất.
import fs from 'node:fs';
import { test, expect } from '@playwright/test';
import { PASSWORD, FRESH_PASSWORD, AUTH_DIR, actual, shot, showText, send, getPage, errorsOf, rnd, sql, asRole, APP_CONTAINER, props } from './helpers.js';
import { execFileSync } from 'node:child_process';

const freshFile = `${AUTH_DIR}/fresh-user.json`;

test('TC-AUTH-001 đăng nhập visitor hợp lệ qua giao diện → vào trang tài khoản', async ({ page }, ti) => {
    await page.goto('/vi/dang-nhap');
    await page.locator('input[autocomplete="username"]').fill('visitor3@baotang360.test');
    await page.locator('input[autocomplete="current-password"]').fill(PASSWORD);
    await page.locator('form button[type=submit]').click();
    await page.waitForURL(/\/vi\/tai-khoan/);
    const user = (await props(page)).auth.user;
    const cookies = await page.context().cookies();
    const sess = cookies.find((c) => c.name === 'baotang360-session');
    await shot(page, 'TC-AUTH-001');
    actual(ti, `Chuyển tới ${new URL(page.url()).pathname}; auth.user=${user.email}, role=${user.role}; cookie phiên httpOnly=${sess?.httpOnly}, sameSite=${sess?.sameSite}.`);
    expect(user.email).toBe('visitor3@baotang360.test');
    expect(sess.httpOnly).toBe(true);
});

test('TC-AUTH-002 đăng nhập sai mật khẩu → báo lỗi chung, không đăng nhập', async ({ page }, ti) => {
    await page.goto('/vi/dang-nhap');
    await page.locator('input[autocomplete="username"]').fill('visitor3@baotang360.test');
    await page.locator('input[autocomplete="current-password"]').fill('SaiMatKhau123');
    await page.locator('form button[type=submit]').click();
    await expect(page.getByText(/không đúng/i).first()).toBeVisible();
    const msg = (await props(page)).errors.email;
    await shot(page, 'TC-AUTH-002');
    const r = await send(page.request, 'POST', '/vi/dang-nhap', { email: 'khongtontai_' + rnd() + '@example.com', password: 'SaiMatKhau123' }, { referer: '/vi/dang-nhap' });
    const msg2 = errorsOf(r).email;
    actual(ti, `Vẫn ở ${new URL(page.url()).pathname}; thông báo "${msg}"; email không tồn tại nhận cùng thông báo "${msg2}" (không lộ email có tồn tại).`);
    expect(page.url()).toContain('/dang-nhap');
    expect(msg2).toBe(msg);
});

test('TC-AUTH-003 giới hạn đăng nhập 5 lần/phút theo email → lần 6 nhận 429', async ({ page }, ti) => {
    await page.goto('/vi/dang-nhap');
    const email = `brute_${rnd()}@example.com`;
    const codes = [];
    for (let i = 0; i < 5; i++) {
        const r = await send(page.request, 'POST', '/vi/dang-nhap', { email, password: 'Sai' + i }, { referer: '/vi/dang-nhap', follow: false });
        codes.push(r.status);
    }
    const sixthHtml = await send(page.request, 'POST', '/vi/dang-nhap', { email, password: 'Sai5' }, { referer: '/vi/dang-nhap', follow: false });
    const sixthJson = await send(page.request, 'POST', '/vi/dang-nhap', { email, password: 'Sai6' }, { referer: '/vi/dang-nhap', follow: false, json: true });
    const realPw = await send(page.request, 'POST', '/vi/dang-nhap', { email, password: PASSWORD }, { referer: '/vi/dang-nhap', follow: false, json: true });
    await page.goto('/vi/dang-nhap');
    const flash = JSON.stringify((await props(page)).flash || {}) + JSON.stringify((await props(page)).errors || {});
    actual(ti, `5 lần POST sai cho ${email}: ${codes.join(', ')}; lần 6 (form) → ${sixthHtml.status} ${sixthHtml.location}; lần 7 (Accept JSON) → ${sixthJson.status}, Retry-After=${sixthJson.headers['retry-after']}; lần 8 → ${realPw.status}. Thông báo trên trang: ${flash.slice(0, 160)}`);
    await showText(page, 'TC-AUTH-003', 'Brute-force đăng nhập', `5 lần sai: ${codes.join(', ')}\nLần 6 (form): ${sixthHtml.status} → ${sixthHtml.location}\nLần 7 (JSON): ${sixthJson.status} Retry-After ${sixthJson.headers['retry-after']}\n${JSON.stringify(sixthJson.body).slice(0, 300)}\nLần 8: ${realPw.status}\nTrang đăng nhập: ${flash}`);
    expect(codes.every((c) => c === 302)).toBe(true);
    expect(sixthJson.status).toBe(429);
});

test('TC-AUTH-004 đăng ký tài khoản hợp lệ → vai trò visitor, tự đăng nhập', async ({ browser }, ti) => {
    const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await ctx.newPage();
    const email = `qa_${rnd()}@example.com`;
    await page.goto('/vi/dang-ky');
    await page.locator('input[autocomplete=name]').fill('QA Người Mới');
    await page.locator('input[autocomplete=email]').fill(email);
    await page.locator('input[autocomplete=tel]').fill('0912345678');
    await page.locator('input[autocomplete=new-password]').nth(0).fill(FRESH_PASSWORD);
    await page.locator('input[autocomplete=new-password]').nth(1).fill(FRESH_PASSWORD);
    await page.locator('form button[type=submit]').click();
    await page.waitForURL((u) => !u.pathname.includes('dang-ky'));
    const user = (await props(page)).auth.user;
    await shot(page, 'TC-AUTH-004');
    const [row] = sql(`SELECT r.code, LEFT(u.password,4) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email='${email}'`);
    fs.writeFileSync(freshFile, JSON.stringify({ email, id: user.id }));
    await ctx.storageState({ path: `${AUTH_DIR}/fresh.json` });
    actual(ti, `Đăng ký ${email} → chuyển tới ${new URL(page.url()).pathname}; auth.user.role=${user.role}; CSDL role=${row[0]}, mật khẩu băm tiền tố ${row[1]} (bcrypt).`);
    expect(user.role).toBe('visitor');
    expect(row[1]).toMatch(/^\$2y\$/);
    await ctx.close();
});

test('TC-AUTH-005 đăng ký mật khẩu yếu/biên (7 ký tự, thiếu chữ hoa, thiếu số) → bị từ chối', async ({ page }, ti) => {
    await page.goto('/vi/dang-ky');
    const out = [];
    for (const pw of ['Abcde12', 'abcdefg1', 'Abcdefgh', 'Abcdefg1']) {
        const email = `qa_weak_${rnd()}@example.com`;
        const r = await send(page.request, 'POST', '/vi/dang-ky', { name: 'QA Yếu', email, phone: '0912345678', password: pw, password_confirmation: pw }, { referer: '/vi/dang-ky', json: true });
        out.push(`${pw} → ${r.status} ${(r.body?.errors?.password || ['OK'])[0]}`);
        if (r.status === 201 || r.status === 200 || r.status === 302) await send(page.request, 'POST', '/vi/dang-xuat', {}, { referer: '/vi' });
    }
    await showText(page, 'TC-AUTH-005', 'Mật khẩu biên', out.join('\n'));
    actual(ti, out.join('; '));
    expect(out.slice(0, 3).every((l) => / 422 /.test(l))).toBe(true);
    expect(out[3]).not.toMatch(/ 422 /);
});

test('TC-AUTH-006 đăng ký email đã tồn tại (khác hoa thường) → lỗi trùng email', async ({ page }, ti) => {
    await page.goto('/vi/dang-ky');
    const r = await send(page.request, 'POST', '/vi/dang-ky', { name: 'QA Trùng', email: 'Visitor@BaoTang360.test', phone: '0912345678', password: FRESH_PASSWORD, password_confirmation: FRESH_PASSWORD }, { referer: '/vi/dang-ky', json: true });
    const n = sql("SELECT COUNT(*) FROM users WHERE LOWER(email)='visitor@baotang360.test'")[0][0];
    actual(ti, `HTTP ${r.status}; lỗi email: ${JSON.stringify(r.body?.errors?.email)}; số bản ghi visitor@ trong CSDL = ${n}.`);
    await showText(page, 'TC-AUTH-006', 'Email trùng', `HTTP ${r.status}\n${JSON.stringify(r.body, null, 2)}\nCOUNT = ${n}`);
    if (r.status !== 422) await send(page.request, 'POST', '/vi/dang-xuat', {}, { referer: '/vi' });
    expect(r.status).toBe(422);
    expect(Number(n)).toBe(1);
});

test('TC-AUTH-007 đăng xuất → phiên bị hủy, vào /tai-khoan bị chuyển về đăng nhập', async ({ browser }, ti) => {
    const ctx = await browser.newContext({ storageState: `${AUTH_DIR}/fresh.json` });
    const page = await ctx.newPage();
    await page.goto('/vi/tai-khoan');
    const before = new URL(page.url()).pathname;
    const oldCookie = (await ctx.cookies()).find((c) => c.name === 'baotang360-session').value;
    await page.locator('header').getByRole('button').filter({ hasText: /QA|tài khoản/i }).first().click().catch(() => {});
    const r = await send(page.request, 'POST', '/vi/dang-xuat', {}, { referer: '/vi/tai-khoan', follow: false });
    const after = await page.request.get('/vi/tai-khoan', { maxRedirects: 0 });
    // phát lại cookie phiên cũ
    const replay = await (await browser.newContext({ storageState: { cookies: [{ name: 'baotang360-session', value: oldCookie, domain: 'localhost', path: '/', expires: -1, httpOnly: true, secure: false, sameSite: 'Lax' }], origins: [] } })).request.get('/vi/tai-khoan', { maxRedirects: 0 });
    await page.goto('/vi/tai-khoan');
    await shot(page, 'TC-AUTH-007');
    actual(ti, `Trước: ${before}; POST /vi/dang-xuat → ${r.status} ${r.location}; GET /vi/tai-khoan → ${after.status()} ${after.headers()['location']}; phát lại cookie phiên cũ → ${replay.status()}.`);
    expect(before).toBe('/vi/tai-khoan');
    expect(after.status()).toBe(302);
    expect(replay.status()).toBe(302);
    await ctx.close();
});

test('TC-AUTH-008 quên mật khẩu → mail (driver log) chứa liên kết, đặt lại thành công và đăng nhập bằng mật khẩu mới', async ({ browser }, ti) => {
    const { email } = JSON.parse(fs.readFileSync(freshFile, 'utf8'));
    // Tiền điều kiện: tài khoản QA đang hoạt động (lượt trước TC-AUTH-009 có thể đã khóa; broker bỏ qua tài khoản bị khóa)
    sql(`UPDATE users SET is_active=1 WHERE email='${email}'`);
    sql(`DELETE FROM password_reset_tokens WHERE email='${email}'`);
    const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await ctx.newPage();
    await page.goto('/vi/quen-mat-khau');
    await page.locator('input[type=email]').fill(email);
    await page.locator('form button[type=submit]').click();
    await page.waitForTimeout(1500);
    await shot(page, 'TC-AUTH-008');
    const flash = JSON.stringify((await props(page)).flash || {});
    const log = execFileSync('docker', ['exec', APP_CONTAINER, 'sh', '-c', 'cat storage/logs/laravel*.log'], { encoding: 'utf8' });
    const links = [...log.matchAll(/dat-lai-mat-khau\/([A-Za-z0-9]{40,})/g)];
    const mailToken = links.length ? links[links.length - 1][1] : null;
    const tokenRows = sql(`SELECT COUNT(*) FROM password_reset_tokens WHERE email='${email}'`)[0][0];
    // Mail không có trong log → tạo token bằng broker (tinker) để vẫn kiểm được luồng đặt lại
    const token = mailToken || execFileSync('docker', ['exec', APP_CONTAINER, 'php', 'artisan', 'tinker', '--execute', `echo Illuminate\\Support\\Facades\\Password::broker()->createToken(App\\Models\\User::where('email','${email}')->first());`], { encoding: 'utf8' }).trim().split('\n').pop();
    const newPw = 'DatLai2026Moi';
    const r = await send(page.request, 'POST', `/vi/dat-lai-mat-khau/${token}`, { token, email, password: newPw, password_confirmation: newPw }, { referer: `/vi/dat-lai-mat-khau/${token}`, follow: false });
    const login = await send(page.request, 'POST', '/vi/dang-nhap', { email, password: newPw }, { referer: '/vi/dang-nhap', follow: false });
    const reuse = await send(page.request, 'POST', `/vi/dat-lai-mat-khau/${token}`, { token, email, password: 'LanHai2026X', password_confirmation: 'LanHai2026X' }, { referer: '/vi/dang-nhap', json: true });
    fs.writeFileSync(freshFile, JSON.stringify({ email, password: newPw }));
    actual(ti, `Flash: ${flash}; bản ghi password_reset_tokens=${tokenRows}; mail đặt lại trong storage/logs/laravel*.log: ${mailToken ? 'có' : 'KHÔNG có (MAIL_MAILER=log ghi mức debug, LOG_LEVEL=info lọc mất)'}; dùng token từ broker: POST đặt lại → ${r.status} ${r.location}; đăng nhập mật khẩu mới → ${login.status} ${login.location}; dùng lại token lần 2 → ${reuse.status}.`);
    expect(login.location).toContain('/tai-khoan');
    expect(reuse.status).not.toBe(302);
    expect(mailToken, 'Mail đặt lại mật khẩu không được ghi vào log').toBeTruthy();
    await ctx.close();
});

test.describe('khóa tài khoản', () => {
    test.use(asRole('admin'));
    test('TC-AUTH-009 admin khóa tài khoản → phiên đang mở bị đẩy ra, không đăng nhập lại được', async ({ page, browser }, ti) => {
        const { email, password } = JSON.parse(fs.readFileSync(freshFile, 'utf8'));
        const u = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        await u.request.get('/vi/dang-nhap');
        await send(u.request, 'POST', '/vi/dang-nhap', { email, password }, { referer: '/vi/dang-nhap', follow: false });
        const okBefore = (await u.request.get('/vi/tai-khoan', { maxRedirects: 0 })).status();
        const [[id, roleId]] = sql(`SELECT id, role_id FROM users WHERE email='${email}'`);
        await page.goto('/vi/quan-tri/nguoi-dung');
        const r = await send(page.request, 'PATCH', `/vi/quan-tri/nguoi-dung/${id}`, { role_id: roleId, is_active: 0 }, { referer: '/vi/quan-tri/nguoi-dung' });
        const after = (await u.request.get('/vi/tai-khoan', { maxRedirects: 0 })).status();
        const relog = await send(u.request, 'POST', '/vi/dang-nhap', { email, password }, { referer: '/vi/dang-nhap' });
        await page.goto(`/vi/quan-tri/nguoi-dung?q=${encodeURIComponent(email)}`);
        await shot(page, 'TC-AUTH-009');
        const active = sql(`SELECT is_active FROM users WHERE id=${id}`)[0][0];
        actual(ti, `Trước khóa /tai-khoan → ${okBefore}; PATCH khóa → ${r.status}, is_active=${active}; phiên cũ /tai-khoan → ${after}; đăng nhập lại → ${new URL(relog.url).pathname}, lỗi "${errorsOf(relog).email}".`);
        expect(okBefore).toBe(200);
        expect(active).toBe('0');
        expect(after).toBe(302);
        expect(relog.url).toContain('dang-nhap');
        await u.close();
    });
});
