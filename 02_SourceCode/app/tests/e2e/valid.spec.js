// VALD: hợp lệ dữ liệu ở biểu mẫu công khai/tài khoản (liên hệ, đánh giá, hồ sơ + tải ảnh).
import path from 'node:path';
import fs from 'node:fs';
import { test, expect } from '@playwright/test';
import { asRole, actual, shot, showText, send, getPage, errorsOf, sql, rnd, FIXTURES } from './helpers.js';

test('TC-VALD-001 liên hệ: email sai, nội dung < 10 ký tự, SĐT chữ, thiếu tiêu đề → báo lỗi từng trường; honeypot có giá trị → không lưu', async ({ page }, ti) => {
    await page.goto('/vi/lien-he');
    await page.getByLabel('Họ tên').fill('A');
    await page.locator('input[type=email]').fill('khong-phai-email');
    await page.getByLabel('Nội dung').fill('ngắn');
    await page.locator('form button[type=submit]').click();
    await page.waitForTimeout(800);
    await shot(page, 'TC-VALD-001', { fullPage: true });
    const r = await send(page.request, 'POST', '/vi/lien-he', { name: 'A', email: 'khong-phai-email', phone: 'abc', subject: '', body: 'ngắn' }, { referer: '/vi/lien-he', json: true });
    const subj = `QA honeypot ${rnd()}`;
    const n0 = sql('SELECT COUNT(*) FROM contact_messages')[0][0];
    const h = await send(page.request, 'POST', '/vi/lien-he', { name: 'Bot', email: 'bot@example.com', subject: subj, body: 'Nội dung quảng cáo spam dài', website: 'http://spam.example' }, { referer: '/vi/lien-he' });
    const n1 = sql('SELECT COUNT(*) FROM contact_messages')[0][0];
    const ui = await page.evaluate(() => [...document.querySelectorAll('input:invalid, textarea:invalid')].map((e) => e.name || e.id).join(','));
    actual(ti, `Giao diện chặn bằng ràng buộc HTML5 (trường invalid: ${ui || 'không'}); POST trực tiếp → ${r.status}, lỗi: ${Object.keys(r.body?.errors || {}).join(', ')}; honeypot website=… → flash "${h.page?.props?.flash?.success}", contact_messages ${n0}→${n1}.`);
    expect(r.status).toBe(422);
    expect(Object.keys(r.body.errors).sort()).toEqual(['body', 'email', 'name', 'phone', 'subject']);
    expect(n1).toBe(n0);
});

test.describe('visitor', () => {
    test.use(asRole('visitor'));
    test('TC-VALD-002 đánh giá: rating 0, 6, 3.5, nội dung < 5 ký tự, đối tượng không tồn tại/kiểu lạ → 422; đánh giá lặp cùng hiện vật bị chặn', async ({ page }, ti) => {
        const [[aid]] = sql("SELECT subject_id FROM reviews WHERE user_id=3 AND subject_type='artifact' LIMIT 1");
        await page.goto('/vi/hien-vat');
        const out = [];
        for (const [label, b] of [['rating=0', { rating: 0 }], ['rating=6', { rating: 6 }], ['rating=3.5', { rating: 3.5 }], ['body=abc', { body: 'abc' }], ['subject_type=user', { subject_type: 'user' }], ['subject_id=999999', { subject_id: 999999, subject_type: 'tour' }], ['trùng hiện vật đã đánh giá', {}]]) {
            const r = await send(page.request, 'POST', '/vi/danh-gia', { subject_type: 'artifact', subject_id: Number(aid), rating: 5, body: 'Nội dung hợp lệ đủ dài', ...b }, { referer: '/vi/hien-vat', json: true });
            out.push(`${label} → ${r.status} ${Object.keys(r.body?.errors || {}).join(',')}`);
        }
        await showText(page, 'TC-VALD-002', 'Đánh giá không hợp lệ', out.join('\n'));
        actual(ti, out.join('; '));
        expect(out.slice(0, 6).every((l) => / → 422 /.test(l))).toBe(true);
        expect(out[6]).not.toMatch(/ → (200|201|302) $/);
    });

    test('TC-VALD-003 hồ sơ: ảnh đại diện là tệp văn bản đổi đuôi .jpg, tệp > 2 MB, SĐT sai, tên 1 ký tự → 422; ảnh PNG hợp lệ → lưu', async ({ page }, ti) => {
        await page.goto('/vi/tai-khoan/ho-so');
        const big = Buffer.alloc(2 * 1024 * 1024 + 10, 0xff);
        const pngSmall = fs.readFileSync(path.join(FIXTURES, 'anh-kiem-thu.png'));
        const out = [];
        const up = async (label, multipart) => {
            const r = await send(page.request, 'POST', '/vi/tai-khoan/ho-so', {}, { referer: '/vi/tai-khoan/ho-so', json: true, multipart: { _method: 'patch', ...multipart } });
            out.push(`${label} → ${r.status} ${Object.keys(r.body?.errors || {}).join(',')}`);
            return r;
        };
        await up('gia-anh.jpg (văn bản)', { avatar: { name: 'gia-anh.jpg', mimeType: 'image/jpeg', buffer: fs.readFileSync(path.join(FIXTURES, 'gia-anh.jpg')) } });
        await up('2MB+10B', { avatar: { name: 'to.jpg', mimeType: 'image/jpeg', buffer: Buffer.concat([Buffer.from([0xff, 0xd8, 0xff, 0xe0]), big]) } });
        await up('phone=12ab', { phone: '12ab' });
        await up('name=A', { name: 'A' });
        await up('theme=neon', { theme: 'neon' });
        const ok = await up('anh-kiem-thu.png hợp lệ', { avatar: { name: 'anh-kiem-thu.png', mimeType: 'image/png', buffer: pngSmall } });
        const av = sql("SELECT avatar_path FROM users WHERE id=3")[0][0];
        await page.goto('/vi/tai-khoan/ho-so');
        await shot(page, 'TC-VALD-003', { fullPage: true });
        actual(ti, out.join('; ') + `; avatar_path=${av}.`);
        expect(out.slice(0, 5).every((l) => / → 422 /.test(l))).toBe(true);
        expect(ok.status).not.toBe(422);
    });
});
