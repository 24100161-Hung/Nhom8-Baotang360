// VR: trình xem 360° (tiles multires), hotspot, minimap, tour có hướng dẫn, audio TTS, ngôn ngữ/theme.
import { test, expect } from '@playwright/test';
import { actual, shot, props, getPage } from './helpers.js';

async function openViewer(page, slug = 'main-hall', q = '') {
    await page.goto(`/vi/tham-quan-360/${slug}${q}`);
    await page.waitForSelector('.pnlm-render-container canvas, .pnlm-container canvas', { timeout: 30000 });
    await page.waitForSelector('.bt-hs__btn', { state: 'attached', timeout: 30000 });
    await page.waitForTimeout(2500);
}

test('TC-VR-001 trình xem 360° tải ảnh multires tiles (Pannellum), canvas WebGL hiển thị, không lỗi JS', async ({ page }, ti) => {
    const tiles = []; const errs = [];
    page.on('response', (r) => { if (r.url().includes('/storage/tiles/')) tiles.push(r.status()); });
    page.on('pageerror', (e) => errs.push(e.message));
    await openViewer(page);
    const p = await props(page);
    const overlay = page.locator('div[role=status]').filter({ hasText: 'Đang tải ảnh 360' });
    const loading = await overlay.waitFor({ state: 'hidden', timeout: 10000 }).then(() => 'ẩn').catch(() => 'VẪN HIỂN THỊ sau 10 s');
    const fallback = await page.evaluate(() => performance.getEntriesByType('resource').filter((e) => e.name.includes('/storage/panoramas/')).length);
    await shot(page, 'TC-VR-001');
    actual(ti, `panorama.type=${p.scene.panorama.type}, maxLevel=${p.scene.panorama.config?.maxLevel}, cubeResolution=${p.scene.panorama.config?.cubeResolution}; tải ${tiles.length} tile (${tiles.filter((s) => s === 200).length} mã 200), ảnh equirectangular dự phòng tải ${fallback} lần; lỗi JS: ${errs.length}; lớp phủ "Đang tải ảnh 360°…": ${loading}.`);
    expect(p.scene.panorama.type).toBe('multires');
    expect(tiles.filter((s) => s === 200).length).toBeGreaterThanOrEqual(6);
    expect(errs).toEqual([]);
    expect(loading, 'Lớp phủ đang tải không tắt khi dùng tiles multires').toBe('ẩn');
});

test('TC-VR-002 bấm hotspot hiện vật → panel thông tin (ảnh, tên, tên khoa học, thời kỳ, nguồn gốc); Esc đóng panel', async ({ page }, ti) => {
    await openViewer(page);
    await page.locator('.bt-hs--artifact .bt-hs__btn').first().evaluate((b) => b.click());
    const panel = page.locator('aside[role=dialog][aria-labelledby=hs-panel-title]');
    await expect(panel).toBeVisible();
    await page.waitForTimeout(800);
    const title = (await panel.locator('#hs-panel-title').textContent()).trim();
    const text = (await panel.innerText()).replace(/\s+/g, ' ');
    const img = await panel.locator('img').count();
    await shot(page, 'TC-VR-002');
    await page.keyboard.press('Escape');
    const closed = await panel.waitFor({ state: 'hidden', timeout: 3000 }).then(() => true).catch(() => false);
    actual(ti, `Panel "${title}", ${img} ảnh, nội dung: "${text.slice(0, 180)}…"; Esc đóng panel: ${closed}.`);
    expect(title.length).toBeGreaterThan(2);
    expect(img).toBeGreaterThan(0);
    expect(closed).toBe(true);
});

test('TC-VR-003 bấm hotspot chuyển phòng → sang "dinosaur-era" không tải lại trang, danh sách đã thăm cập nhật', async ({ page }, ti) => {
    await openViewer(page);
    await page.evaluate(() => { window.__noReload = true; });
    await page.locator('.bt-hs--scene .bt-hs__btn').first().evaluate((b) => b.click());
    await page.waitForURL(/dinosaur-era/, { timeout: 20000 });
    await page.waitForTimeout(2500);
    const same = await page.evaluate(() => window.__noReload === true);
    const p = await props(page);
    await shot(page, 'TC-VR-003');
    actual(ti, `URL → ${new URL(page.url()).pathname}; không tải lại trang (SPA/partial reload): ${same}; visited=${JSON.stringify(p.visited)}; scene=${p.scene.slug}, ${p.scene.hotspots.length} hotspot.`);
    expect(p.scene.slug).toBe('dinosaur-era');
    expect(same).toBe(true);
    expect(p.visited).toEqual(expect.arrayContaining(['main-hall', 'dinosaur-era']));
});

test('TC-VR-004 minimap: đánh dấu phòng hiện tại, nón FOV, phòng chưa thăm; bấm phòng trên minimap để chuyển phòng', async ({ page }, ti) => {
    await openViewer(page);
    const mm = page.locator('.minimap').first();
    await expect(mm).toBeVisible();
    const current = await mm.locator('.minimap__room.is-current').getAttribute('aria-label');
    const unvisited = await mm.locator('.minimap__room.is-unvisited').count();
    const cone = await mm.locator('.minimap__cone').count();
    const coneD0 = await mm.locator('.minimap__cone').getAttribute('d').catch(() => null);
    const canvas = page.locator('.pnlm-render-container canvas, .pnlm-container canvas').first();
    const box = await canvas.boundingBox();
    await page.mouse.move(box.x + box.width * 0.5, box.y + box.height * 0.3);
    await page.mouse.down(); await page.mouse.move(box.x + box.width * 0.5 - 300, box.y + box.height * 0.3, { steps: 15 }); await page.mouse.up();
    await page.waitForTimeout(800);
    if ((await mm.locator('.minimap__cone').getAttribute('d')) === coneD0) {
        await page.locator('.pnlm-container, .pnlm-ui').first().focus().catch(() => {});
        await page.keyboard.down('ArrowRight'); await page.waitForTimeout(1200); await page.keyboard.up('ArrowRight');
        await page.waitForTimeout(800);
    }
    const coneD1 = await mm.locator('.minimap__cone').getAttribute('d').catch(() => null);
    await shot(page, 'TC-VR-004');
    await mm.locator('.minimap__room[aria-label*="Kỷ Băng"], .minimap__room[aria-label*="Ice"]').first().dispatchEvent('click');
    await page.waitForURL(/ice-age/, { timeout: 20000 });
    actual(ti, `Phòng hiện tại: "${current}"; ${unvisited} phòng chưa thăm; nón FOV: ${cone}, xoay kéo chuột → nón đổi hướng: ${coneD0 !== coneD1}; bấm "Kỷ Băng Hà" trên minimap → ${new URL(page.url()).pathname}.`);
    expect(current).toMatch(/Sảnh/);
    expect(unvisited).toBe(7);
    expect(cone).toBe(1);
    expect(coneD0).not.toBe(coneD1);
});

test('TC-VR-005 tour có hướng dẫn: bắt đầu → bước 1, Tiếp theo → bước 2 (tiến trình tăng, lưu máy chủ), Quay lại → bước 1', async ({ page }, ti) => {
    const progress = [];
    page.on('response', async (r) => { if (r.url().includes('tien-trinh')) progress.push(`${r.status()} ${(await r.text().catch(() => '')).slice(0, 90)}`); });
    await page.goto('/vi/tour/khung-long-45-phut');
    await Promise.all([page.waitForURL(/mode=guided/, { timeout: 20000 }), page.getByRole('button', { name: /Bắt đầu tour có hướng dẫn|Bắt đầu lại/ }).first().click()]);
    await page.waitForSelector('.tour-bar', { timeout: 20000 });
    await page.waitForTimeout(2000);
    const bar = page.locator('.tour-bar [role=progressbar]');
    const v1 = await bar.getAttribute('aria-valuetext');
    const s1 = new URL(page.url()).searchParams.get('step');
    await page.locator('.tour-bar').getByRole('button', { name: /Tiếp theo/ }).click();
    await page.waitForURL(/step=2/, { timeout: 20000 });
    await page.waitForTimeout(2000);
    const v2 = await bar.getAttribute('aria-valuetext');
    const pulse = await page.locator('.bt-hs--pulse').count();
    await shot(page, 'TC-VR-005');
    await page.locator('.tour-bar').getByRole('button', { name: /Quay lại/ }).click();
    await page.waitForURL(/step=1/, { timeout: 20000 });
    actual(ti, `Bước ${s1}: "${v1}"; sau Tiếp theo: "${v2}", hotspot được nhấn mạnh: ${pulse}; Quay lại → step=${new URL(page.url()).searchParams.get('step')}; POST tien-trinh: ${progress.join(' | ')}.`);
    expect(s1).toBe('1');
    expect(v1).not.toBe(v2);
    expect(progress.some((x) => x.startsWith('200'))).toBe(true);
});

test('TC-VR-006 thuyết minh âm thanh: mở audio guide, có bản VI/EN (TTS từ transcript), chuyển EN đổi nội dung', async ({ page }, ti) => {
    await openViewer(page);
    const box = page.locator('#viewer-audio');
    const openedByDefault = await box.isVisible();
    if (!openedByDefault) await page.locator('button[aria-controls=viewer-audio]').click();
    await expect(box).toBeVisible();
    const radios = box.locator('[role=radio]');
    const nr = await radios.count();
    await box.locator('button[aria-expanded]').first().click({ timeout: 3000 }).catch(() => {});
    await page.waitForTimeout(300);
    const t1 = (await box.innerText()).replace(/\s+/g, ' ');
    const supported = await page.evaluate(() => 'speechSynthesis' in window);
    await shot(page, 'TC-VR-006');
    await radios.filter({ hasText: /EN|English/i }).first().click();
    await page.waitForTimeout(500);
    const t2 = (await box.innerText()).replace(/\s+/g, ' ');
    const play = await box.getByRole('button', { name: /Phát|Nghe|Play/i }).first().isEnabled().catch(() => false);
    actual(ti, `Audio guide hiển thị (mở sẵn: ${openedByDefault}), ${nr} ngôn ngữ; speechSynthesis=${supported}; nút phát bật: ${play}; VI: "${t1.slice(0, 90)}…"; sau chọn EN: "${t2.slice(0, 90)}…".`);
    expect(nr).toBeGreaterThanOrEqual(2);
    expect(t1).not.toBe(t2);
});

test('TC-VR-007 chuyển ngôn ngữ VI→EN giữ nguyên phòng; đổi theme sáng/tối lưu lại sau khi tải lại trang', async ({ page }, ti) => {
    await openViewer(page, 'dinosaur-era');
    await page.locator('a[aria-label*="English"], a[aria-label*="tiếng Anh"], button[aria-label*="English"]').first().click();
    await page.waitForURL(/\/en\/tham-quan-360\/dinosaur-era/, { timeout: 20000 });
    const p = await props(page);
    await page.goto('/vi');
    const th0 = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    await page.locator('header button[aria-label*="tối"], header button[aria-label*="sáng"], button[aria-label*="Dark"], button[aria-label*="Light"]').first().click();
    const th1 = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    await page.reload();
    const th2 = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    await shot(page, 'TC-VR-007');
    actual(ti, `Chuyển EN → ${new URL(page.url()).pathname === '/vi' ? '/en/tham-quan-360/dinosaur-era' : ''} locale=${p.locale}, tiêu đề phòng "${p.scene.title}"; theme ${th0} → ${th1}, sau tải lại ${th2}.`);
    expect(p.locale).toBe('en');
    expect(th1).not.toBe(th0);
    expect(th2).toBe(th1);
});
