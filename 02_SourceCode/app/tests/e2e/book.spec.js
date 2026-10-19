// BOOK: gói vé, lịch ngày, giỏ hàng, giữ chỗ 15 phút, sức chứa.
import { test, expect } from '@playwright/test';
import { asRole, authFile, actual, shot, showText, send, getPage, errorsOf, sql, artisan, openDay, addDays, clearCart, placeOrder, visitDays } from './helpers.js';

const addCart = (req, body) => send(req, 'POST', '/vi/gio-hang', body, { referer: '/vi/ve' });
const cartOf = async (req) => (await getPage(req, '/vi/gio-hang')).page.props;
const firstDay = async (req, status) => (await visitDays(req, addDays(1), addDays(59))).find((d) => d.status === status).date;

test('TC-BOOK-001 trang vé: gói Regular 150.000đ, Premium 590.000đ, lịch 60 ngày có trạng thái còn vé/hết vé/đóng cửa', async ({ page }, ti) => {
    const { page: p } = await getPage(page.request, '/vi/ve');
    const pk = Object.fromEntries(p.props.packages.map((x) => [x.code, x.price_vnd]));
    const cal = p.props.calendar;
    const st = cal.reduce((m, d) => ({ ...m, [d.status]: (m[d.status] || 0) + 1 }), {});
    await page.goto('/vi/ve');
    await shot(page, 'TC-BOOK-001', { fullPage: true });
    actual(ti, `Gói: ${JSON.stringify(pk)}; lịch ${cal.length} ngày từ ${cal[0].date} đến ${cal[cal.length - 1].date}; trạng thái ${JSON.stringify(st)}; ngày hết vé có remaining=0: ${cal.filter((d) => d.status === 'sold_out').every((d) => d.remaining === 0)}.`);
    expect(pk.regular).toBe(150000);
    expect(pk.premium).toBe(590000);
    expect(cal.length).toBe(60);
    expect(Object.keys(st).sort()).toEqual(['closed', 'open', 'sold_out']);
});

test('TC-BOOK-002 khách vãng lai thêm 2 vé Regular ngày còn vé vào giỏ → giỏ có 1 dòng, tổng 300.000đ', async ({ page }, ti) => {
    const date = await openDay(page.request, 0);
    await page.goto('/vi/ve');
    const r = await addCart(page.request, { package_id: 1, visit_date: date, quantity: 2 });
    const c = await cartOf(page.request);
    await page.goto('/vi/gio-hang');
    await shot(page, 'TC-BOOK-002', { fullPage: true });
    actual(ti, `POST /vi/gio-hang (${date}, SL 2) → flash "${r.page?.props?.flash?.success}"; giỏ: ${c.items.length} dòng, SL ${c.items[0]?.quantity}, tạm tính ${c.subtotal_vnd}đ, cart.count=${c.cart.count}, canCheckout=${c.canCheckout}.`);
    expect(c.items.length).toBe(1);
    expect(c.subtotal_vnd).toBe(300000);
});

test.describe('visitor2 – giỏ hàng', () => {
    test.use(asRole('visitor2'));
    test.beforeEach(async ({ page }) => { await clearCart(page.request); });

    test('TC-BOOK-003 cập nhật số lượng, đổi ngày (gộp dòng trùng), đổi sang ngày đóng cửa bị từ chối, xóa dòng', async ({ page }, ti) => {
        const d1 = await openDay(page.request, 0); const d2 = await openDay(page.request, 1);
        const closed = await firstDay(page.request, 'closed');
        await addCart(page.request, { package_id: 1, visit_date: d1, quantity: 1 });
        await addCart(page.request, { package_id: 1, visit_date: d2, quantity: 2 });
        let c = await cartOf(page.request);
        const it1 = c.items.find((i) => i.visit_date.startsWith(d1));
        await send(page.request, 'PATCH', `/vi/gio-hang/${it1.id}`, { quantity: 3 }, { referer: '/vi/gio-hang' });
        c = await cartOf(page.request);
        const q3 = c.items.find((i) => i.id === it1.id).quantity;
        const bad = await send(page.request, 'PATCH', `/vi/gio-hang/${it1.id}`, { visit_date: closed }, { referer: '/vi/gio-hang' });
        await send(page.request, 'PATCH', `/vi/gio-hang/${it1.id}`, { visit_date: d2 }, { referer: '/vi/gio-hang' });
        c = await cartOf(page.request);
        const merged = c.items.map((i) => `${i.visit_date.slice(0, 10)}×${i.quantity}`).join(', ');
        await page.goto('/vi/gio-hang');
        await shot(page, 'TC-BOOK-003', { fullPage: true });
        for (const i of c.items) await send(page.request, 'DELETE', `/vi/gio-hang/${i.id}`, {}, { referer: '/vi/gio-hang' });
        const after = (await cartOf(page.request)).items.length;
        actual(ti, `PATCH SL 1→3: ${q3}; đổi sang ngày đóng cửa ${closed} → lỗi ${JSON.stringify(errorsOf(bad))}; đổi ${d1}→${d2} (đã có dòng ${d2}×2) → giỏ: ${merged}; xóa hết → ${after} dòng.`);
        expect(q3).toBe(3);
        expect(Object.keys(errorsOf(bad)).length).toBeGreaterThan(0);
        expect(merged).toBe(`${d2}×5`);
        expect(after).toBe(0);
    });

    test('TC-BOOK-004 chọn ngày đóng cửa (thứ Hai) → bị từ chối, giỏ không đổi', async ({ page }, ti) => {
        const closed = await firstDay(page.request, 'closed');
        const r = await addCart(page.request, { package_id: 1, visit_date: closed, quantity: 1 });
        const n = (await cartOf(page.request)).items.length;
        await page.goto('/vi/ve');
        await showText(page, 'TC-BOOK-004', `Thêm vé ngày đóng cửa ${closed}`, JSON.stringify(errorsOf(r), null, 2) + `\nSố dòng giỏ: ${n}`);
        actual(ti, `Ngày ${closed} (closed) → lỗi ${JSON.stringify(errorsOf(r))}; giỏ ${n} dòng.`);
        expect(errorsOf(r).visit_date).toBeTruthy();
        expect(n).toBe(0);
    });

    test('TC-BOOK-005 chọn ngày hết vé (sold_out) → báo hết vé, giỏ không đổi', async ({ page }, ti) => {
        const so = await firstDay(page.request, 'sold_out');
        const r = await addCart(page.request, { package_id: 1, visit_date: so, quantity: 1 });
        const n = (await cartOf(page.request)).items.length;
        await page.goto('/vi/ve');
        await showText(page, 'TC-BOOK-005', `Thêm vé ngày hết vé ${so}`, JSON.stringify(errorsOf(r), null, 2) + `\nSố dòng giỏ: ${n}`);
        actual(ti, `Ngày ${so} (sold_out) → lỗi ${JSON.stringify(errorsOf(r))}; giỏ ${n} dòng.`);
        expect(Object.keys(errorsOf(r)).length).toBeGreaterThan(0);
        expect(n).toBe(0);
    });

    test('TC-BOOK-006 số lượng biên: 0, 11 (> max_per_order 10), -1, "abc" bị từ chối; 10 hợp lệ; cộng dồn vượt 10 bị từ chối', async ({ page }, ti) => {
        const d = await openDay(page.request, 2);
        const out = [];
        for (const q of [0, 11, -1, 'abc', 10, 1]) {
            const r = await addCart(page.request, { package_id: 1, visit_date: d, quantity: q });
            const e = errorsOf(r);
            out.push(`SL ${q} → ${Object.keys(e).length ? 'lỗi ' + Object.values(e)[0] : 'OK'}`);
        }
        const c = await cartOf(page.request);
        await page.goto('/vi/gio-hang');
        await showText(page, 'TC-BOOK-006', 'Số lượng biên', out.join('\n') + `\nGiỏ: ${c.items.map((i) => i.quantity).join(',')}`);
        actual(ti, out.join('; ') + `; giỏ còn SL ${c.items.map((i) => i.quantity).join(',')}.`);
        expect(out.slice(0, 4).every((l) => l.includes('lỗi'))).toBe(true);
        expect(out[4]).toContain('OK');
        expect(out[5]).toContain('lỗi');
        expect(c.items[0].quantity).toBe(10);
    });

    test('TC-BOOK-007 ngày biên: hôm qua, hôm nay, ngày chưa có lịch (+120), sai định dạng → chỉ hôm nay (nếu mở) hợp lệ', async ({ page }, ti) => {
        const today = (await visitDays(page.request, addDays(0), addDays(0)))[0];
        const out = [];
        for (const d of [addDays(-1), addDays(0), addDays(120), '2026-13-45', '18/10/2026', '']) {
            const r = await addCart(page.request, { package_id: 1, visit_date: d, quantity: 1 });
            const e = errorsOf(r);
            out.push(`"${d}" → ${Object.keys(e).length ? 'lỗi ' + Object.values(e)[0] : 'OK'}`);
        }
        await page.goto('/vi/gio-hang');
        await showText(page, 'TC-BOOK-007', 'Ngày tham quan biên', out.join('\n') + `\nHôm nay status=${today.status}`);
        actual(ti, out.join('; ') + ` (hôm nay status=${today.status}).`);
        expect(out[0]).toContain('lỗi');
        expect(out[1]).toContain(today.status === 'open' ? 'OK' : 'lỗi');
        expect(out.slice(2).every((l) => l.includes('lỗi'))).toBe(true);
    });
});

test.describe('visitor3 – đặt hàng', () => {
    test.use(asRole('visitor3'));

    test('TC-BOOK-008 đặt hàng giữ chỗ 15 phút: hold_expires_at = tạo + 15 phút, đếm ngược, sold tăng đúng số lượng', async ({ page }, ti) => {
        const d = await openDay(page.request, 3);
        const sold0 = Number(sql(`SELECT sold FROM visit_days WHERE visit_date='${d}'`)[0][0]);
        const { code } = await placeOrder(page.request, { date: d, qty: 2 });
        const [[st, diff]] = sql(`SELECT status, TIMESTAMPDIFF(SECOND, created_at, hold_expires_at) FROM orders WHERE code='${code}'`);
        const sold1 = Number(sql(`SELECT sold FROM visit_days WHERE visit_date='${d}'`)[0][0]);
        const { page: p } = await getPage(page.request, `/vi/don-hang/${code}`);
        await page.goto(`/vi/don-hang/${code}`);
        await shot(page, 'TC-BOOK-008', { fullPage: true });
        await send(page.request, 'POST', `/vi/don-hang/${code}/huy`, {}, { referer: `/vi/don-hang/${code}` });
        actual(ti, `Đơn ${code} status=${st}; hold_expires_at − created_at = ${diff} s; seconds_left=${p.props.order.seconds_left}, can_pay=${p.props.order.can_pay}; visit_days ${d}.sold ${sold0} → ${sold1}. (Đã hủy đơn sau khi kiểm.)`);
        expect(st).toBe('pending');
        expect(Number(diff)).toBe(900);
        expect(p.props.order.seconds_left).toBeGreaterThan(850);
        expect(sold1 - sold0).toBe(2);
    });

    test('TC-BOOK-009 hết hạn giữ chỗ → app:expire-orders chuyển expired, trả chỗ, không thanh toán được', async ({ page }, ti) => {
        const d = await openDay(page.request, 4);
        const sold0 = Number(sql(`SELECT sold FROM visit_days WHERE visit_date='${d}'`)[0][0]);
        const { code } = await placeOrder(page.request, { date: d, qty: 3 });
        const sold1 = Number(sql(`SELECT sold FROM visit_days WHERE visit_date='${d}'`)[0][0]);
        sql(`UPDATE orders SET hold_expires_at = NOW() - INTERVAL 1 MINUTE WHERE code='${code}'`);
        const out = artisan('app:expire-orders').trim();
        const st = sql(`SELECT status FROM orders WHERE code='${code}'`)[0][0];
        const sold2 = Number(sql(`SELECT sold FROM visit_days WHERE visit_date='${d}'`)[0][0]);
        const pay = await send(page.request, 'POST', `/vi/don-hang/${code}/thanh-toan`, { payment_method: 'card' }, { referer: `/vi/don-hang/${code}` });
        await page.goto(`/vi/don-hang/${code}`);
        await shot(page, 'TC-BOOK-009', { fullPage: true });
        actual(ti, `Đơn ${code}: sold ${sold0} → ${sold1} khi giữ chỗ; lùi hold_expires_at 1 phút, chạy app:expire-orders ("${out}") → status=${st}, sold=${sold2}; POST thanh-toan → về ${new URL(pay.url).pathname}, flash=${JSON.stringify(pay.page?.props?.flash)}.`);
        expect(st).toBe('expired');
        expect(sold2).toBe(sold0);
        expect(pay.url).not.toContain('cong-thanh-toan');
    });

    test('TC-BOOK-010 sức chứa: ngày còn 1 chỗ → thêm 2 vé bị từ chối, 1 vé OK; đặt hàng khi đã hết chỗ bị chặn (khóa hàng)', async ({ page, browser }, ti) => {
        await clearCart(page.request);
        const admin = await browser.newContext({ storageState: authFile('admin') });
        const d = await openDay(page.request, 5);
        const [[cap0, sold]] = sql(`SELECT capacity, sold FROM visit_days WHERE visit_date='${d}'`);
        await admin.request.get('/vi/quan-tri/lich-ve');
        const set = await send(admin.request, 'PATCH', '/vi/quan-tri/lich-ve', { date: d, capacity: Number(sold) + 1, status: 'open' }, { referer: '/vi/quan-tri/lich-ve' });
        try {
            const r2 = await addCart(page.request, { package_id: 1, visit_date: d, quantity: 2 });
            const r1 = await addCart(page.request, { package_id: 1, visit_date: d, quantity: 1 });
            // người khác mua mất chỗ cuối trước khi visitor2 đặt hàng
            sql(`UPDATE visit_days SET sold = capacity WHERE visit_date='${d}'`);
            const co = await send(page.request, 'POST', '/vi/thanh-toan', { contact_name: 'QA', contact_email: 'qa@example.com', contact_phone: '0912345678', payment_method: 'card' }, { referer: '/vi/thanh-toan' });
            const st = sql(`SELECT capacity, sold, status FROM visit_days WHERE visit_date='${d}'`)[0].join('/');
            await page.goto('/vi/thanh-toan');
            await shot(page, 'TC-BOOK-010', { fullPage: true });
            actual(ti, `Admin PATCH lịch ${d} capacity=${Number(sold) + 1} → ${set.status === 200 ? 'OK' : set.status}; thêm 2 vé → ${JSON.stringify(errorsOf(r2))}; thêm 1 vé → ${Object.keys(errorsOf(r1)).length ? JSON.stringify(errorsOf(r1)) : 'OK'}; sau khi chỗ cuối bị bán, POST thanh-toan → ${new URL(co.url).pathname} lỗi ${JSON.stringify(errorsOf(co)).slice(0, 120)} flash ${JSON.stringify(co.page?.props?.flash?.error || '')}; visit_days cap/sold/status=${st}.`);
            expect(Object.keys(errorsOf(r2)).length).toBeGreaterThan(0);
            expect(Object.keys(errorsOf(r1)).length).toBe(0);
            expect(co.status).not.toBe(429);
            expect(co.url).not.toContain('/don-hang/');
            expect(JSON.stringify(errorsOf(co)) + (co.page?.props?.flash?.error || '')).toMatch(/chỗ|vé|mở bán/i);
        } finally {
            sql(`UPDATE visit_days SET capacity=${cap0}, sold=${sold}, status='open' WHERE visit_date='${d}'`);
            await admin.close();
        }
    });
});
