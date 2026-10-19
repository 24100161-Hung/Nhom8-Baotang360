// Đăng nhập một lần cho mỗi vai trò và lưu storageState (đăng nhập giới hạn 5 lần/phút theo email+IP).
// storageState cũ còn phiên hợp lệ thì dùng lại.
import fs from 'node:fs';
import path from 'node:path';
import { request } from '@playwright/test';
import { AUTH_DIR, ROLES, PASSWORD, SHOP_USERS, FRESH_PASSWORD, xsrfHeader, rnd } from './helpers.js';

async function sessionValid(baseURL, file) {
    if (!fs.existsSync(file)) return false;
    const ctx = await request.newContext({ baseURL, storageState: file });
    const res = await ctx.get('/vi/tai-khoan', { maxRedirects: 0 });
    await ctx.dispose();
    return res.status() === 200;
}

async function login(baseURL, email, file) {
    const ctx = await request.newContext({ baseURL });
    await ctx.get('/vi/dang-nhap');
    const res = await ctx.post('/vi/dang-nhap', { form: { email, password: PASSWORD }, headers: { ...(await xsrfHeader(ctx)), Referer: '/vi/dang-nhap' }, maxRedirects: 0 });
    const loc = res.headers()['location'] || '';
    if (![302, 303].includes(res.status()) || loc.includes('dang-nhap')) throw new Error(`Đăng nhập ${email} thất bại: ${res.status()} ${loc}`);
    await ctx.storageState({ path: file });
    await ctx.dispose();
}

export default async function globalSetup(config) {
    const baseURL = config.projects[0].use.baseURL;
    fs.mkdirSync(AUTH_DIR, { recursive: true });
    for (const [role, email] of Object.entries(ROLES)) {
        const file = path.join(AUTH_DIR, `${role}.json`);
        if (await sessionValid(baseURL, file)) continue;
        await login(baseURL, email, file);
    }
    for (const label of SHOP_USERS) {
        const file = path.join(AUTH_DIR, `${label}.json`);
        if (await sessionValid(baseURL, file)) continue;
        const ctx = await request.newContext({ baseURL });
        await ctx.get('/vi/dang-ky');
        const email = `qa_${label.toLowerCase()}_${rnd()}@example.com`;
        const res = await ctx.post('/vi/dang-ky', { form: { name: `QA ${label}`, email, phone: '0912345678', password: FRESH_PASSWORD, password_confirmation: FRESH_PASSWORD }, headers: { ...(await xsrfHeader(ctx)), Referer: baseURL + '/vi/dang-ky' }, maxRedirects: 0 });
        if (![302, 303].includes(res.status())) throw new Error(`Đăng ký ${email} thất bại: ${res.status()}`);
        await ctx.storageState({ path: file });
        fs.writeFileSync(path.join(AUTH_DIR, `${label}.email`), email);
        await ctx.dispose();
    }
}
