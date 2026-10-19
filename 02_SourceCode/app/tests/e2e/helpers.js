// Tiện ích dùng chung cho bộ kiểm thử E2E BaoTang360 (qa-tester).
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
export const AUTH_DIR = path.join(here, '.auth');
export const IMG_DIR = path.resolve(here, '../../../../05_KiemThu/img');
export const MATRIX_DIR = path.resolve(here, '../../../../05_KiemThu/matrix');
export const FIXTURES = path.join(here, 'fixtures');
export const PASSWORD = process.env.E2E_PASSWORD || 'BaoTang@2026';
export const ROLES = {
    admin: 'admin@baotang360.test',
    curator: 'curator@baotang360.test',
    visitor: 'visitor@baotang360.test',
    premium: 'premium@baotang360.test',
    visitor2: 'visitor2@baotang360.test',
    visitor3: 'visitor3@baotang360.test',
};
// Tài khoản QA đăng ký mới (global-setup) cho luồng mua vé: tránh giới hạn throttle dùng chung theo người dùng (xem bugs/qa_2)
export const SHOP_USERS = ['shopA', 'shopB', 'shopC'];
export const BASE = process.env.E2E_BASE_URL || 'http://localhost:8090';
export const DB_CONTAINER = process.env.E2E_DB_CONTAINER || 'baotang360-db-1';
export const APP_CONTAINER = process.env.E2E_APP_CONTAINER || 'baotang360-app-1';

/** Đường dẫn storageState của một vai trò. */
export const authFile = (role) => path.join(AUTH_DIR, `${role}.json`);
export const asRole = (role) => ({ storageState: authFile(role) });

/** Header X-XSRF-TOKEN lấy từ cookie XSRF-TOKEN hiện có của request context. */
export async function xsrfHeader(req) {
    const state = await req.storageState();
    const c = state.cookies.find((x) => x.name === 'XSRF-TOKEN');
    return c ? { 'X-XSRF-TOKEN': decodeURIComponent(c.value) } : {};
}

/** Giải mã data-page của Inertia trong HTML. */
export function pageFromHtml(html) {
    const m = html.match(/data-page="([^"]*)"/);
    if (!m) return null;
    const json = m[1].replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
    return JSON.parse(json);
}

/** GET một trang, trả {status, page, html}. */
export async function getPage(req, url, opts = {}) {
    const res = await req.get(url, opts);
    const html = await res.text();
    let page = null;
    try { page = pageFromHtml(html); } catch { page = null; }
    return { status: res.status(), page, html, res, url: res.url() };
}

/**
 * Gửi form như trình duyệt (có CSRF, Referer). follow=true: theo redirect và trả page Inertia đích (để đọc errors/flash).
 * follow=false: trả status + Location.
 */
export async function send(req, method, url, data = {}, { referer = '/vi', follow = true, json = false, multipart = null, headers = {} } = {}) {
    if (!(await xsrfHeader(req))['X-XSRF-TOKEN']) await req.get(referer);
    const h = { ...(await xsrfHeader(req)), Referer: new URL(referer, BASE).toString(), Accept: json ? 'application/json' : 'text/html', ...headers };
    const opts = { method, headers: h, maxRedirects: 0 };
    if (multipart) opts.multipart = multipart;
    else if (json) opts.data = data;
    else opts.form = data;
    let res = await req.fetch(url, opts);
    const first = { status: res.status(), location: res.headers()['location'] || '' };
    // Trình duyệt chuyển POST/PATCH/DELETE + 302 thành GET khi theo redirect
    if (follow && [301, 302, 303].includes(first.status) && first.location) {
        res = await req.get(first.location, { headers: { Accept: 'text/html' } });
    }
    const text = await res.text();
    let body = text;
    try { body = JSON.parse(text); } catch { /* HTML */ }
    let page = null;
    if (typeof body === 'string') { try { page = pageFromHtml(text); } catch { page = null; } }
    return { status: follow ? res.status() : first.status, first, location: first.location, url: res.url(), body, page, headers: res.headers() };
}

/** Chuỗi form lồng nhau (Laravel) từ object phẳng có khóa dạng a[b]. */
export const errorsOf = (r) => r.page?.props?.errors || (typeof r.body === 'object' ? r.body?.errors : null) || {};

/** Lưu ảnh minh chứng theo mã ca. */
export async function shot(page, code, opts = {}) {
    fs.mkdirSync(IMG_DIR, { recursive: true });
    await page.screenshot({ path: path.join(IMG_DIR, `${code}.png`), fullPage: false, ...opts });
}

/** Ghi kết quả thực tế vào annotation để tổng hợp vào test_cases.json. */
export function actual(testInfo, text) {
    testInfo.annotations.push({ type: 'actual', description: text });
}

/** Hiển thị khối văn bản (HTTP/JSON/SQL) trên trang trắng rồi chụp làm minh chứng. */
export async function showText(page, code, title, text) {
    const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
    await page.setContent(`<html><body style="font:13px monospace;padding:16px;background:#fff;color:#111"><h3>${esc(code)} – ${esc(title)}</h3><pre style="white-space:pre-wrap">${esc(text)}</pre></body></html>`);
    await shot(page, code, { fullPage: true });
}

/** Chạy truy vấn MySQL (đọc/chuẩn bị dữ liệu) trong container db, trả các dòng dạng mảng. */
export function sql(query) {
    const out = execFileSync('docker', ['exec', DB_CONTAINER, 'mysql', '-ubaotang360', '-pbaotang360_secret', '--default-character-set=utf8mb4', 'baotang360', '-N', '-B', '-e', query], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    return out.trim() ? out.trim().split('\n').map((l) => l.split('\t')) : [];
}

/** Chạy lệnh artisan trong container app (không migrate/seed). */
export function artisan(...args) {
    return execFileSync('docker', ['exec', APP_CONTAINER, 'php', 'artisan', ...args], { encoding: 'utf8' });
}

/** Danh sách ngày tham quan (API công khai). */
export async function visitDays(req, from, to) {
    const r = await req.get(`/api/v1/visit-days?from=${from}&to=${to}`);
    return (await r.json()).data;
}

export const ymd = (d) => new Date(d.getTime() + 7 * 3600e3).toISOString().slice(0, 10);
export const addDays = (n) => ymd(new Date(Date.now() + n * 86400e3));

/** Ngày mở cửa còn chỗ thứ k (bỏ qua hôm nay và ngày mai). */
export async function openDay(req, k = 0, minRemaining = 20) {
    const days = await visitDays(req, addDays(2), addDays(59));
    const ok = days.filter((d) => d.status === 'open' && d.remaining >= minRemaining);
    return ok[k].date;
}

export const rnd = () => Math.random().toString(36).slice(2, 8);
export const FRESH_PASSWORD = 'KiemThu2026A';

/** Làm trống giỏ hàng của context. */
export async function clearCart(req) {
    const { page } = await getPage(req, '/vi/gio-hang');
    for (const it of page?.props?.items || []) await send(req, 'DELETE', `/vi/gio-hang/${it.id}`, {}, { referer: '/vi/gio-hang' });
}

/** Đặt hàng: làm trống giỏ, thêm (package, date, qty), checkout; trả mã đơn. */
export async function placeOrder(req, { packageId = 1, date, qty = 1, method = 'card' } = {}) {
    await clearCart(req);
    const add = await send(req, 'POST', '/vi/gio-hang', { package_id: packageId, ...(date ? { visit_date: date } : {}), quantity: qty }, { referer: '/vi/ve' });
    const e = errorsOf(add);
    if (Object.keys(e).length) throw new Error('Thêm giỏ lỗi: ' + JSON.stringify(e));
    const r = await send(req, 'POST', '/vi/thanh-toan', { contact_name: 'QA Kiểm Thử', contact_email: 'qa@example.com', contact_phone: '0912345678', payment_method: method }, { referer: '/vi/thanh-toan' });
    const m = r.url.match(/don-hang\/(OD-[A-Z0-9]+)/);
    if (!m) throw new Error('Không tạo được đơn: ' + r.first.status + ' ' + r.first.location + ' ' + r.status + ' ' + r.url + ' ' + JSON.stringify(errorsOf(r)) + JSON.stringify(r.page?.props?.flash) + (typeof r.body === 'string' && !r.page ? r.body.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 200) : ''));
    return { code: m[1], page: r.page };
}

/** Thanh toán đơn qua cổng sandbox với kết quả outcome (success|fail|cancel). */
export async function payOrder(req, code, outcome = 'success', method = 'card') {
    const r1 = await send(req, 'POST', `/vi/don-hang/${code}/thanh-toan`, { payment_method: method }, { referer: `/vi/don-hang/${code}` });
    const m = r1.url.match(/cong-thanh-toan\/([^/?]+)/);
    if (!m) return { ok: false, r1 };
    const r2 = await send(req, 'POST', `/vi/cong-thanh-toan/${m[1]}/ket-qua`, { outcome }, { referer: `/vi/cong-thanh-toan/${m[1]}` });
    return { ok: true, txn: m[1], r1, r2 };
}

/** Props Inertia hiện hành của trang trong trình duyệt (lịch sử Inertia hoặc data-page). */
export async function props(page) {
    return page.evaluate(() => window.history.state?.page?.props || JSON.parse(document.getElementById('app').dataset.page).props);
}
