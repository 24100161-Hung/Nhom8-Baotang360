// PAY: thanh toán sandbox (thẻ/chuyển khoản/ví), thất bại, giảm giá Premium, chặn thanh toán lặp, IPN giả mạo.
import { test, expect } from '@playwright/test';
import { asRole, actual, shot, showText, send, getPage, errorsOf, sql, openDay, clearCart, placeOrder, payOrder } from './helpers.js';

test.describe('shopA', () => {
    test.use(asRole('shopA'));
    let paidCode = null;

    test('TC-PAY-001 thanh toán thẻ sandbox thành công → đơn paid, sinh đủ vé, giao dịch success', async ({ page }, ti) => {
        const d = await openDay(page.request, 6);
        const { code } = await placeOrder(page.request, { date: d, qty: 2, method: 'card' });
        const r = await payOrder(page.request, code, 'success', 'card');
        await page.goto(r.r1.url);
        await shot(page, 'TC-PAY-001-sandbox');
        const { page: p } = await getPage(page.request, `/vi/don-hang/${code}`);
        const o = p.props.order;
        const [[pst, pm, amt]] = sql(`SELECT p.status, p.method, p.amount_vnd FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${code}' ORDER BY p.id DESC LIMIT 1`);
        const tickets = o.items.flatMap((i) => i.tickets);
        await page.goto(`/vi/don-hang/${code}`);
        await shot(page, 'TC-PAY-001', { fullPage: true });
        paidCode = code;
        actual(ti, `Đơn ${code}: status=${o.status}, paid_at=${o.paid_at}, total=${o.total_vnd}; payments: ${pst}/${pm}/${amt}; ${tickets.length} vé, mã ${tickets.map((t) => t.code).join(',')}; flash "${r.r2.page?.props?.flash?.success}".`);
        expect(o.status).toBe('paid');
        expect(tickets.length).toBe(2);
        expect(pst).toBe('succeeded');
        expect(Number(amt)).toBe(o.total_vnd);
    });

    test('TC-PAY-002 cổng sandbox trả thất bại → đơn vẫn pending (thanh toán lại được), giao dịch failed, không sinh vé', async ({ page }, ti) => {
        const d = await openDay(page.request, 7);
        const { code } = await placeOrder(page.request, { date: d, qty: 1, method: 'card' });
        const r = await payOrder(page.request, code, 'fail', 'card');
        const { page: p } = await getPage(page.request, `/vi/don-hang/${code}`);
        const o = p.props.order;
        const [[pst]] = sql(`SELECT p.status FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${code}' ORDER BY p.id DESC LIMIT 1`);
        const nt = sql(`SELECT COUNT(*) FROM tickets t JOIN order_items i ON i.id=t.order_item_id JOIN orders o ON o.id=i.order_id WHERE o.code='${code}'`)[0][0];
        await page.goto(`/vi/don-hang/${code}`);
        await shot(page, 'TC-PAY-002', { fullPage: true });
        await send(page.request, 'POST', `/vi/don-hang/${code}/huy`, {}, { referer: `/vi/don-hang/${code}` });
        actual(ti, `Đơn ${code}: status=${o.status}, can_pay=${o.can_pay}; giao dịch ${pst}; số vé ${nt}; flash "${r.r2.page?.props?.flash?.error || r.r2.page?.props?.flash?.success}". (Đã hủy đơn sau khi kiểm.)`);
        expect(o.status).toBe('pending');
        expect(o.can_pay).toBe(true);
        expect(pst).toBe('failed');
        expect(nt).toBe('0');
    });

    test('TC-PAY-005 thanh toán lại đơn đã paid hoặc gửi kết quả lặp cho giao dịch đã xong → bị chặn, không tạo giao dịch/vé mới', async ({ page }, ti) => {
        test.skip(!paidCode, 'cần đơn paid từ TC-PAY-001');
        const n0 = sql(`SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${paidCode}'`)[0][0];
        const t0 = sql(`SELECT COUNT(*) FROM tickets t JOIN order_items i ON i.id=t.order_item_id JOIN orders o ON o.id=i.order_id WHERE o.code='${paidCode}'`)[0][0];
        const [[txn]] = sql(`SELECT p.txn_ref FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${paidCode}' ORDER BY p.id DESC LIMIT 1`);
        const r1 = await send(page.request, 'POST', `/vi/don-hang/${paidCode}/thanh-toan`, { payment_method: 'card' }, { referer: `/vi/don-hang/${paidCode}` });
        const r2 = await send(page.request, 'POST', `/vi/cong-thanh-toan/${txn}/ket-qua`, { outcome: 'success' }, { referer: `/vi/cong-thanh-toan/${txn}` });
        const n1 = sql(`SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${paidCode}'`)[0][0];
        const t1 = sql(`SELECT COUNT(*) FROM tickets t JOIN order_items i ON i.id=t.order_item_id JOIN orders o ON o.id=i.order_id WHERE o.code='${paidCode}'`)[0][0];
        await page.goto(`/vi/don-hang/${paidCode}`);
        await showText(page, 'TC-PAY-005', 'Thanh toán lặp', `POST thanh-toan đơn paid → ${new URL(r1.url).pathname} flash ${JSON.stringify(r1.page?.props?.flash)}\nPOST ket-qua lặp ${txn} → ${r2.status} ${new URL(r2.url).pathname} flash ${JSON.stringify(r2.page?.props?.flash)}\npayments ${n0}→${n1}, tickets ${t0}→${t1}`);
        actual(ti, `POST thanh-toan đơn paid → về ${new URL(r1.url).pathname}, flash.error="${r1.page?.props?.flash?.error}"; gửi lại kết quả success cho ${txn} → ${r2.status}; payments ${n0}→${n1}, vé ${t0}→${t1}.`);
        expect(r1.url).not.toContain('cong-thanh-toan');
        expect(n1).toBe(n0);
        expect(t1).toBe(t0);
    });
});

test.describe('shopC', () => {
    test.use(asRole('shopC'));

    test('TC-PAY-003 thanh toán bằng ví điện tử và chuyển khoản → cổng hiển thị đúng phương thức, giao dịch lưu đúng method', async ({ page }, ti) => {
        const d = await openDay(page.request, 8);
        const { code } = await placeOrder(page.request, { date: d, qty: 1, method: 'ewallet' });
        const r = await payOrder(page.request, code, 'success', 'ewallet');
        const sb = r.r1.page.props;
        await page.goto(r.r1.url);
        await shot(page, 'TC-PAY-003');
        const [[pm, ost]] = sql(`SELECT p.method, o.status FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.code='${code}' ORDER BY p.id DESC LIMIT 1`);
        await send(page.request, 'POST', '/vi/gio-hang', { package_id: 1, visit_date: d, quantity: 1 }, { referer: '/vi/ve' });
        const bad = await send(page.request, 'POST', '/vi/thanh-toan', { contact_name: 'QA', contact_email: 'qa@example.com', contact_phone: '0912345678', payment_method: 'bitcoin' }, { referer: '/vi/thanh-toan' });
        await clearCart(page.request);
        actual(ti, `Đơn ${code}: cổng sandbox method=${sb.payment?.method ?? JSON.stringify(sb).match(/"method":"(\w+)"/)?.[1]}; payments.method=${pm}; order=${ost}. Checkout payment_method=bitcoin → lỗi ${JSON.stringify(errorsOf(bad).payment_method)}.`);
        expect(pm).toBe('ewallet');
        expect(ost).toBe('paid');
        expect(errorsOf(bad).payment_method).toBeTruthy();
    });

    test('TC-PAY-006 IPN sandbox giả mạo (sai chữ ký, sai số tiền) → bị từ chối, đơn không đổi trạng thái', async ({ page }, ti) => {
        const d = await openDay(page.request, 9);
        const { code } = await placeOrder(page.request, { date: d, qty: 1, method: 'bank' });
        const r1 = await send(page.request, 'POST', `/vi/don-hang/${code}/thanh-toan`, { payment_method: 'bank' }, { referer: `/vi/don-hang/${code}` });
        const txn = r1.url.match(/cong-thanh-toan\/([^/?]+)/)[1];
        const [[amt]] = sql(`SELECT total_vnd FROM orders WHERE code='${code}'`);
        const ipn = (body) => page.request.post('/api/v1/payments/sandbox/ipn', { data: body, headers: { Accept: 'application/json' } });
        const a = await ipn({ txn_ref: txn, amount: Number(amt), outcome: 'success', signature: 'deadbeef'.repeat(8) });
        const b = await ipn({ txn_ref: txn, amount: 1000, outcome: 'success', signature: 'deadbeef'.repeat(8) });
        const c = await ipn({ txn_ref: txn, amount: Number(amt), outcome: 'success' });
        const st = sql(`SELECT status FROM orders WHERE code='${code}'`)[0][0];
        await showText(page, 'TC-PAY-006', 'IPN giả mạo', `sai chữ ký → ${a.status()} ${await a.text()}\nsai số tiền → ${b.status()} ${await b.text()}\nkhông chữ ký → ${c.status()} ${await c.text()}\norder ${code}: ${st}`);
        await send(page.request, 'POST', `/vi/don-hang/${code}/huy`, {}, { referer: `/vi/don-hang/${code}` });
        actual(ti, `Đơn ${code} (bank): IPN sai chữ ký → ${a.status()}; sai số tiền → ${b.status()}; thiếu chữ ký → ${c.status()}; trạng thái đơn vẫn ${st}. (Đã hủy đơn sau khi kiểm.)`);
        expect(a.status()).toBeGreaterThanOrEqual(400);
        expect(b.status()).toBeGreaterThanOrEqual(400);
        expect(c.status()).toBeGreaterThanOrEqual(400);
        expect(st).toBe('pending');
    });
});

test.describe('premium', () => {
    test.use(asRole('premium'));
    test('TC-PAY-004 hội viên Premium còn hạn được giảm 20% vé Regular ở bước thanh toán; vé Student không giảm', async ({ page }, ti) => {
        await clearCart(page.request);
        const d = await openDay(page.request, 6);
        await send(page.request, 'POST', '/vi/gio-hang', { package_id: 1, visit_date: d, quantity: 2 }, { referer: '/vi/ve' });
        await send(page.request, 'POST', '/vi/gio-hang', { package_id: 3, visit_date: d, quantity: 1 }, { referer: '/vi/ve' });
        const { page: p } = await getPage(page.request, '/vi/thanh-toan');
        const pr = p.props;
        const mem = pr.auth.user.membership;
        await page.goto('/vi/thanh-toan');
        await shot(page, 'TC-PAY-004', { fullPage: true });
        await clearCart(page.request);
        actual(ti, `membership=${JSON.stringify(mem)}; giỏ 2×Regular + 1×Student: subtotal=${pr.subtotal_vnd}, discount=${pr.discount_vnd}, total=${pr.total_vnd}.`);
        expect(mem?.is_active).toBe(true);
        expect(pr.subtotal_vnd).toBe(400000);
        expect(pr.discount_vnd).toBe(60000);
        expect(pr.total_vnd).toBe(340000);
    });
});
