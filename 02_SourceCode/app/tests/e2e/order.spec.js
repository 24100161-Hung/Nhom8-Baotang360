// ORDR: chi tiết đơn, vé QR, hủy đơn chờ, hoàn tiền đơn đã trả, biên hạn hoàn, hóa đơn.
import { test, expect } from '@playwright/test';
import { asRole, actual, shot, showText, send, getPage, sql, openDay, addDays, visitDays, placeOrder, payOrder } from './helpers.js';

test.describe('shopB', () => {
    test.use(asRole('shopB'));
    let paid = null; let cancelled = null;
    const soldOf = (d) => Number(sql(`SELECT sold FROM visit_days WHERE visit_date='${d}'`)[0][0]);

    test('TC-ORDR-001 đơn đã thanh toán: chi tiết đơn, vé có mã QR (ảnh tải được), bản in vé, hiện trong trang tài khoản', async ({ page }, ti) => {
        const d = await openDay(page.request, 10);
        const { code } = await placeOrder(page.request, { date: d, qty: 2 });
        await payOrder(page.request, code, 'success');
        paid = { code, d };
        const { page: p } = await getPage(page.request, `/vi/don-hang/${code}`);
        const t = p.props.order.items[0].tickets;
        const qr = await page.request.get(t[0].qr_url);
        const pdf = await page.request.get(t[0].pdf_url);
        const acc = (await getPage(page.request, '/vi/tai-khoan')).page.props.orders.map((o) => o.code);
        await page.goto(`/vi/don-hang/${code}`);
        const imgOk = await page.locator('img[src*="/qr"]').first().evaluate((i) => i.complete && i.naturalWidth > 0).catch(() => false);
        await shot(page, 'TC-ORDR-001', { fullPage: true });
        actual(ti, `Đơn ${code} paid: ${t.length} vé (${t.map((x) => x.code + '/' + x.status).join(', ')}); QR → ${qr.status()} ${qr.headers()['content-type']}, ảnh hiển thị: ${imgOk}; bản in vé → ${pdf.status()} ${pdf.headers()['content-type']}; trang tài khoản có đơn: ${acc.includes(code)}.`);
        expect(t.length).toBe(2);
        expect(qr.status()).toBe(200);
        expect(imgOk).toBe(true);
        expect(acc).toContain(code);
    });

    test('TC-ORDR-002 hủy đơn đang chờ thanh toán → cancelled, trả lại chỗ trong lịch', async ({ page }, ti) => {
        const d = await openDay(page.request, 11);
        const s0 = soldOf(d);
        const { code } = await placeOrder(page.request, { date: d, qty: 3 });
        const s1 = soldOf(d);
        await page.goto(`/vi/don-hang/${code}`);
        const r = await send(page.request, 'POST', `/vi/don-hang/${code}/huy`, { reason: 'Đổi kế hoạch' }, { referer: `/vi/don-hang/${code}` });
        const [[st, at]] = sql(`SELECT status, cancelled_at FROM orders WHERE code='${code}'`);
        const s2 = soldOf(d);
        cancelled = code;
        await page.goto(`/vi/don-hang/${code}`);
        await shot(page, 'TC-ORDR-002', { fullPage: true });
        actual(ti, `Đơn ${code}: sold ${s0}→${s1}→${s2}; sau hủy status=${st}, cancelled_at=${at}; flash "${r.page?.props?.flash?.success}".`);
        expect(st).toBe('cancelled');
        expect(s2).toBe(s0);
    });

    test('TC-ORDR-003 hủy đơn đã thanh toán, ngày tham quan = hôm nay + 2 (biên trên) → refunded, vé vô hiệu, trả chỗ', async ({ page }, ti) => {
        const days = await visitDays(page.request, addDays(2), addDays(2));
        const d = days[0].status === 'open' && days[0].remaining > 5 ? days[0].date : await openDay(page.request, 12);
        const { code } = await placeOrder(page.request, { date: d, qty: 1 });
        await payOrder(page.request, code, 'success');
        const s1 = soldOf(d);
        const before = (await getPage(page.request, `/vi/don-hang/${code}`)).page.props.order.can_cancel;
        const r = await send(page.request, 'POST', `/vi/don-hang/${code}/huy`, {}, { referer: `/vi/don-hang/${code}` });
        const st = sql(`SELECT status FROM orders WHERE code='${code}'`)[0][0];
        const tk = sql(`SELECT GROUP_CONCAT(t.status) FROM tickets t JOIN order_items i ON i.id=t.order_item_id JOIN orders o ON o.id=i.order_id WHERE o.code='${code}'`)[0][0];
        const pay = sql(`SELECT GROUP_CONCAT(p.status) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${code}'`)[0][0];
        const s2 = soldOf(d);
        await page.goto(`/vi/don-hang/${code}`);
        await shot(page, 'TC-ORDR-003', { fullPage: true });
        actual(ti, `Đơn ${code} ngày ${d}: can_cancel=${before}; sau hủy status=${st}, vé=${tk}, giao dịch=${pay}; sold ${s1}→${s2}; flash "${r.page?.props?.flash?.success}".`);
        expect(before).toBe(true);
        expect(st).toBe('refunded');
        expect(s2).toBe(s1 - 1);
    });

    test('TC-ORDR-004 hủy đơn đã thanh toán, ngày tham quan = ngày mai (dưới hạn hoàn) → bị từ chối, đơn vẫn paid', async ({ page }, ti) => {
        const d = await openDay(page.request, 13);
        const { code } = await placeOrder(page.request, { date: d, qty: 1 });
        await payOrder(page.request, code, 'success');
        // Chuẩn bị dữ liệu: ngày mai đóng cửa (thứ Hai) nên dời ngày tham quan của dòng đơn sang ngày mai bằng SQL
        sql(`UPDATE order_items i JOIN orders o ON o.id=i.order_id SET i.visit_date='${addDays(1)}' WHERE o.code='${code}'`);
        try {
            const o = (await getPage(page.request, `/vi/don-hang/${code}`)).page.props.order;
            const r = await send(page.request, 'POST', `/vi/don-hang/${code}/huy`, {}, { referer: `/vi/don-hang/${code}` });
            const st = sql(`SELECT status FROM orders WHERE code='${code}'`)[0][0];
            await page.goto(`/vi/don-hang/${code}`);
            await shot(page, 'TC-ORDR-004', { fullPage: true });
            actual(ti, `Đơn ${code} (visit_date=${addDays(1)}): can_cancel=${o.can_cancel}; POST huy → flash.error="${r.page?.props?.flash?.error}" ${JSON.stringify(r.page?.props?.errors || {})}; status=${st}.`);
            expect(o.can_cancel).toBe(false);
            expect(st).toBe('paid');
        } finally {
            sql(`UPDATE order_items i JOIN orders o ON o.id=i.order_id SET i.visit_date='${d}' WHERE o.code='${code}'`);
        }
    });

    test('TC-ORDR-005 hóa đơn (signed URL) in được: có mã đơn, dòng vé, tổng tiền, thông tin liên hệ', async ({ page }, ti) => {
        test.skip(!paid, 'cần đơn từ TC-ORDR-001');
        const o = (await getPage(page.request, `/vi/don-hang/${paid.code}`)).page.props.order;
        const r = await page.request.get(o.invoice_url);
        const html = await r.text();
        await page.goto(o.invoice_url);
        await shot(page, 'TC-ORDR-005', { fullPage: true });
        const total = new Intl.NumberFormat('vi-VN').format(o.total_vnd);
        actual(ti, `GET invoice_url → ${r.status()} ${r.headers()['content-type']}; chứa mã ${paid.code}: ${html.includes(paid.code)}; tổng ${total}: ${html.includes(total)}; email liên hệ: ${html.includes('qa@example.com')}.`);
        expect(r.status()).toBe(200);
        expect(html).toContain(paid.code);
        expect(html).toContain(total);
    });

    test('TC-ORDR-006 hủy lại đơn đã hủy / thanh toán đơn đã hủy → bị từ chối, trạng thái không đổi', async ({ page }, ti) => {
        test.skip(!cancelled, 'cần đơn từ TC-ORDR-002');
        const r1 = await send(page.request, 'POST', `/vi/don-hang/${cancelled}/huy`, {}, { referer: `/vi/don-hang/${cancelled}` });
        const r2 = await send(page.request, 'POST', `/vi/don-hang/${cancelled}/thanh-toan`, { payment_method: 'card' }, { referer: `/vi/don-hang/${cancelled}` });
        const st = sql(`SELECT status FROM orders WHERE code='${cancelled}'`)[0][0];
        const np = sql(`SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${cancelled}'`)[0][0];
        await page.goto(`/vi/don-hang/${cancelled}`);
        await showText(page, 'TC-ORDR-006', 'Thao tác trên đơn đã hủy', `huy lần 2 → ${r1.status} flash ${JSON.stringify(r1.page?.props?.flash)}\nthanh-toan → ${new URL(r2.url).pathname} flash ${JSON.stringify(r2.page?.props?.flash)}\nstatus=${st}, payments=${np}`);
        actual(ti, `Đơn ${cancelled}: hủy lần 2 → flash.error="${r1.page?.props?.flash?.error}"; thanh toán → về ${new URL(r2.url).pathname}, flash.error="${r2.page?.props?.flash?.error}"; status=${st}, số giao dịch=${np}.`);
        expect(st).toBe('cancelled');
        expect(r2.url).not.toContain('cong-thanh-toan');
        expect(np).toBe('0');
    });
});
