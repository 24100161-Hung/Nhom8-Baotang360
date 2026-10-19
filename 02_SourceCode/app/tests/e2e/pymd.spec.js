// PYMD: dịch vụ Python – gợi ý tour (TF-IDF + cosine), phân tích drop-off, sinh tiles multires.
import { test, expect, request as pwRequest } from '@playwright/test';
import { asRole, actual, shot, showText, send, getPage, sql } from './helpers.js';

const PY = process.env.E2E_PY_URL || 'http://127.0.0.1:8021';
const TOKEN = process.env.E2E_PY_TOKEN || 'dev-analytics-token';

test('TC-PYMD-001 gợi ý tour tương tự (TF-IDF + cosine): không gồm chính tour, điểm giảm dần, có lý do; Python và API Laravel khớp', async ({ page }, ti) => {
    const api = await (await page.request.get('/api/v1/tours/khung-long-45-phut/similar')).json();
    const py = await pwRequest.newContext();
    const r = await py.post(`${PY}/recommend/tours`, { data: { tour_id: 1, locale: 'vi', limit: 4 }, headers: { 'X-Analytics-Token': TOKEN } });
    const pj = await r.json();
    const noTok = (await py.post(`${PY}/recommend/tours`, { data: { tour_id: 1 } })).status();
    await py.dispose();
    const res = pj.results;
    const txt = `Laravel /api/v1/tours/khung-long-45-phut/similar: ${api.data.map((t) => t.slug).join(', ')}\nPython /recommend/tours (${r.status()}):\n${res.map((x) => `${x.slug} score=${x.score} why=${JSON.stringify(x.why).slice(0, 120)}`).join('\n')}\nKhông token → ${noTok}`;
    await showText(page, 'TC-PYMD-001', 'Gợi ý tour', txt);
    actual(ti, `API Laravel: ${api.data.map((t) => t.slug).join(', ')}; Python ${r.status()}: ${res.map((x) => `${x.slug}(${x.score})`).join(', ')}; điểm giảm dần: ${res.every((x, i) => i === 0 || x.score <= res[i - 1].score)}; có "why": ${res.every((x) => x.why?.length)}; gọi Python thiếu token → ${noTok}.`);
    expect(res.length).toBeGreaterThan(0);
    expect(res.map((x) => x.slug)).not.toContain('khung-long-45-phut');
    expect(api.data.map((t) => t.slug)).not.toContain('khung-long-45-phut');
    expect(res.every((x, i) => i === 0 || x.score <= res[i - 1].score)).toBe(true);
    expect(noTok).toBe(401);
});

test.describe('admin', () => {
    test.use(asRole('admin'));
    test('TC-PYMD-002 phân tích luồng /quan-tri/phan-tich-luong: chạy lại → báo cáo drop-off từng tour (drop_rate ∈ [0,1], bước tệ nhất), luồng chuyển phòng', async ({ page }, ti) => {
        await page.goto('/vi/quan-tri/phan-tich-luong');
        const run = await send(page.request, 'POST', '/vi/quan-tri/phan-tich-luong', {}, { referer: '/vi/quan-tri/phan-tich-luong' });
        const { page: p } = await getPage(page.request, '/vi/quan-tri/phan-tich-luong');
        const rep = p.props.report;
        const drop = rep?.dropoff || [];
        const rates = drop.flatMap((t) => t.steps.map((s) => s.drop_rate));
        await page.goto('/vi/quan-tri/phan-tich-luong');
        await page.waitForTimeout(1500);
        await shot(page, 'TC-PYMD-002', { fullPage: true });
        const w = drop[0] ? `${drop[0].tour?.name ?? drop[0].tour}: bước tệ nhất ${JSON.stringify(drop[0].worst_step).slice(0, 80)}` : '-';
        actual(ti, `POST chạy lại → flash "${run.page?.props?.flash?.success || run.page?.props?.flash?.error}"; generated_at=${rep?.generated_at}, engine/nguồn=${rep?.source ?? rep?.engine ?? '-'}; ${drop.length} tour, ${rates.length} bước, drop_rate trong [0,1]: ${rates.every((x) => x >= 0 && x <= 1)}; ${w}; scene_flow ${rep?.scene_flow?.length} cạnh, scene_exit ${rep?.scene_exit?.length} phòng.`);
        expect(drop.length).toBeGreaterThan(0);
        expect(rates.every((x) => x >= 0 && x <= 1)).toBe(true);
        expect(rep.scene_flow.length).toBeGreaterThan(0);
    });
});

test('TC-PYMD-003 tiles multires đã sinh xong cho cả 8 phòng: tiles_state=done, config.json và tile cấp 1 truy cập được, Python báo done', async ({ page }, ti) => {
    const rows = sql("SELECT id, slug, tiles_state FROM scenes WHERE deleted_at IS NULL ORDER BY id");
    const py = await pwRequest.newContext();
    const out = [];
    for (const [id, slug, st] of rows) {
        const cfg = await page.request.get(`/storage/tiles/${id}/config.json`);
        const tile = await page.request.get(`/storage/tiles/${id}/1/f/0_0.jpg`);
        const s = await (await py.get(`${PY}/tiles/status/${id}`, { headers: { 'X-Analytics-Token': TOKEN } })).json();
        out.push(`${id} ${slug}: db=${st}, config.json ${cfg.status()}, tile 1/f/0_0 ${tile.status()}, python=${s.state} maxLevel=${s.max_level} tiles=${s.tile_count}`);
    }
    await py.dispose();
    await showText(page, 'TC-PYMD-003', 'Trạng thái tiles 8 phòng', out.join('\n'));
    actual(ti, out.join('; '));
    expect(rows.length).toBe(8);
    expect(out.every((l) => /db=done, config.json 200, tile 1\/f\/0_0 200, python=done/.test(l))).toBe(true);
});
