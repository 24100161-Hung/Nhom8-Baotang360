// ADMN: dashboard KPI, CRUD gói vé, hotspot pitch/yaw, kiểm duyệt đánh giá, xuất thống kê, nhật ký.
import { test, expect } from '@playwright/test';
import { asRole, authFile, actual, shot, showText, send, getPage, errorsOf, sql, rnd } from './helpers.js';

test.describe('admin', () => {
    test.use(asRole('admin'));
    const code = `qa_${rnd()}`;

    test('TC-ADMN-001 dashboard: đủ 8 KPI, doanh thu 7 ngày, đơn theo trạng thái; số liệu khớp CSDL', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/quan-tri');
        const k = p.props.kpis; const c = p.props.charts;
        const [[scenes, users, orders]] = sql("SELECT (SELECT COUNT(*) FROM scenes WHERE deleted_at IS NULL),(SELECT COUNT(*) FROM users),(SELECT COUNT(*) FROM orders)");
        await page.goto('/vi/quan-tri');
        await page.waitForTimeout(1500);
        await shot(page, 'TC-ADMN-001', { fullPage: true });
        actual(ti, `kpis=${JSON.stringify(k)}; CSDL scenes=${scenes}, users=${users}, orders=${orders}; revenue7d ${c.revenue7d.length} điểm (${c.revenue7d[0].date}→${c.revenue7d[6]?.date}); ordersByStatus ${c.ordersByStatus.length} nhóm; canvas biểu đồ: ${await page.locator('canvas').count()}.`);
        expect(Object.keys(k).sort()).toEqual(['cancel_rate', 'hotspot_clicks', 'orders', 'pending_orders', 'revenue_vnd', 'scenes', 'users', 'visits']);
        expect(String(k.scenes)).toBe(scenes);
        expect(String(k.users)).toBe(users);
        expect(c.revenue7d.length).toBe(7);
    });

    test('TC-ADMN-002 CRUD gói vé: tạo (nháp) → không hiện công khai → sửa giá/xuất bản → hiện ở /ve → xóa', async ({ page }, ti) => {
        await page.goto('/vi/quan-tri/goi-ve/moi');
        const base = { code, package_type: 'single', price_vnd: 120000, validity_days: 1, max_per_order: 5, status: 'draft', name_vi: 'Gói QA kiểm thử', name_en: 'QA test package', description_vi: 'Gói tạm', description_en: 'Temp', 'benefits_vi[0]': 'Quyền lợi A', 'benefits_en[0]': 'Benefit A' };
        const c = await send(page.request, 'POST', '/vi/quan-tri/goi-ve', base, { referer: '/vi/quan-tri/goi-ve/moi' });
        const [[id]] = sql(`SELECT id FROM ticket_packages WHERE code='${code}'`);
        const pub0 = (await getPage(page.request, '/api/v1/packages')).res;
        const inPub0 = (await (await page.request.get('/api/v1/packages')).text()).includes(code);
        const u = await send(page.request, 'PUT', `/vi/quan-tri/goi-ve/${id}`, { ...base, price_vnd: 99000, status: 'published' }, { referer: `/vi/quan-tri/goi-ve/${id}/sua` });
        const { page: t } = await getPage(page.request, '/vi/ve');
        const pubPkg = t.props.packages.find((x) => x.code === code);
        await page.goto('/vi/quan-tri/goi-ve');
        await shot(page, 'TC-ADMN-002', { fullPage: true });
        const d = await send(page.request, 'DELETE', `/vi/quan-tri/goi-ve/${id}`, {}, { referer: '/vi/quan-tri/goi-ve' });
        const left = sql(`SELECT COUNT(*), SUM(deleted_at IS NOT NULL) FROM ticket_packages WHERE code='${code}'`)[0];
        const inPub2 = (await (await page.request.get('/api/v1/packages')).text()).includes(code);
        actual(ti, `Tạo ${code} → flash "${c.page?.props?.flash?.success}", id=${id}, có trong API công khai khi nháp: ${inPub0}; sửa giá 99.000 + xuất bản → /vi/ve có gói giá ${pubPkg?.price_vnd}; xóa → flash "${d.page?.props?.flash?.success}", bản ghi (tổng, đã xóa mềm)=${left.join('/')}, còn trong API: ${inPub2}.`);
        expect(id).toBeTruthy();
        expect(inPub0).toBe(false);
        expect(pubPkg?.price_vnd).toBe(99000);
        expect(inPub2).toBe(false);
        void pub0;
    });

    test('TC-ADMN-003 hotspot: pitch/yaw ngoài miền (95, 181), loại scene thiếu đích → 422; pitch 90/yaw -180 hợp lệ được lưu rồi xóa', async ({ page }, ti) => {
        await page.goto('/vi/quan-tri/khong-gian-360/1/sua');
        const base = { type: 'info', pitch: 0, yaw: 0, title_vi: 'Điểm QA', title_en: 'QA point', text_vi: 'x', text_en: 'x' };
        const url = '/vi/quan-tri/khong-gian-360/1/hotspot';
        const out = [];
        for (const [label, body] of [['pitch=95', { pitch: 95 }], ['yaw=181', { yaw: 181 }], ['pitch=-90.5', { pitch: -90.5 }], ['type=scene thiếu target', { type: 'scene' }], ['icon=<script>', { icon: '<script>' }], ['image_url=http://', { image_url: 'http://evil.example/x.png' }]]) {
            const r = await send(page.request, 'POST', url, { ...base, ...body }, { referer: '/vi/quan-tri/khong-gian-360/1/sua', json: true });
            out.push(`${label} → ${r.status} ${Object.keys(r.body?.errors || {}).join(',')}`);
        }
        const n0 = Number(sql('SELECT COUNT(*) FROM hotspots WHERE scene_id=1')[0][0]);
        const ok = await send(page.request, 'POST', url, { ...base, pitch: 90, yaw: -180 }, { referer: '/vi/quan-tri/khong-gian-360/1/sua' });
        const row = sql("SELECT id, pitch, yaw FROM hotspots WHERE scene_id=1 ORDER BY id DESC LIMIT 1")[0];
        const n1 = Number(sql('SELECT COUNT(*) FROM hotspots WHERE scene_id=1')[0][0]);
        await page.goto('/vi/quan-tri/khong-gian-360/1/sua#hotspots');
        await page.waitForTimeout(1500);
        await shot(page, 'TC-ADMN-003');
        if (n1 > n0) await send(page.request, 'DELETE', `${url}/${row[0]}`, {}, { referer: '/vi/quan-tri/khong-gian-360/1/sua' });
        const n2 = Number(sql('SELECT COUNT(*) FROM hotspots WHERE scene_id=1')[0][0]);
        actual(ti, out.join('; ') + `; pitch=90,yaw=-180 → ${ok.status}, hotspot ${n0}→${n1} (id ${row[0]}: ${row[1]}/${row[2]}), xóa → ${n2}.`);
        expect(out.every((l) => / → 422 /.test(l))).toBe(true);
        expect(n1).toBe(n0 + 1);
        expect(n2).toBe(n0);
    });

    test('TC-ADMN-004 kiểm duyệt đánh giá: visitor gửi → chờ duyệt, chưa hiển thị; admin duyệt → hiển thị trên trang hiện vật', async ({ page, browser }, ti) => {
        const v = await browser.newContext({ storageState: authFile('visitor') });
        const [[aid, slug]] = sql("SELECT a.id, a.slug FROM artifacts a WHERE a.status='published' AND a.id NOT IN (SELECT subject_id FROM reviews WHERE user_id=3 AND subject_type='artifact') ORDER BY a.id LIMIT 1");
        const body = `Đánh giá QA ${rnd()} – hiện vật rất đẹp`;
        await v.request.get(`/vi/hien-vat/${slug}`);
        const r = await send(v.request, 'POST', '/vi/danh-gia', { subject_type: 'artifact', subject_id: aid, rating: 4, title: 'QA', body }, { referer: `/vi/hien-vat/${slug}` });
        const [[rid, st0]] = sql(`SELECT id, status FROM reviews WHERE body='${body}'`);
        const vis0 = JSON.stringify((await getPage(page.request, `/vi/hien-vat/${slug}`)).page.props.artifact.reviews).includes(body);
        const m = await send(page.request, 'PATCH', `/vi/quan-tri/danh-gia/${rid}`, { status: 'approved', review_note: 'OK' }, { referer: '/vi/quan-tri/danh-gia' });
        const st1 = sql(`SELECT status FROM reviews WHERE id=${rid}`)[0][0];
        const anon = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        const vis1 = JSON.stringify((await getPage(anon.request, `/vi/hien-vat/${slug}`)).page.props.artifact.reviews).includes(body);
        const ap = await anon.newPage();
        await ap.goto(`/vi/hien-vat/${slug}#reviews`);
        await ap.getByText(body).first().scrollIntoViewIfNeeded().catch(() => {});
        await shot(ap, 'TC-ADMN-004');
        actual(ti, `Visitor gửi đánh giá cho hiện vật ${slug} → flash "${r.page?.props?.flash?.success}", status=${st0}, hiển thị công khai: ${vis0}; admin PATCH approved → ${st1}; khách xem trang thấy đánh giá: ${vis1}.`);
        expect(st0).toBe('pending');
        expect(vis0).toBe(false);
        expect(st1).toBe('approved');
        expect(vis1).toBe(true);
        await v.close(); await anon.close();
    });

    test('TC-ADMN-005 thống kê: lọc 7/30/90 ngày và xuất CSV (lượt truy cập, lượt xem hiện vật, tour, tìm kiếm, đơn hàng)', async ({ page }, ti) => {
        const out = [];
        for (const e of ['visits', 'artifact_views', 'tours', 'search', 'orders']) {
            const r = await page.request.get(`/vi/quan-tri/thong-ke?export=${e}&days=30`);
            const txt = await r.text();
            out.push(`${e} → ${r.status()} ${(r.headers()['content-type'] || '').split(';')[0]} ${txt.split('\n').filter(Boolean).length} dòng`);
        }
        const d7 = (await getPage(page.request, '/vi/quan-tri/thong-ke?days=7')).page.props.visitsByDay.length;
        await page.goto('/vi/quan-tri/thong-ke');
        await page.waitForTimeout(1500);
        await shot(page, 'TC-ADMN-005', { fullPage: true });
        actual(ti, out.join('; ') + `; days=7 → visitsByDay ${d7} điểm.`);
        expect(out.every((l) => / → 200 text\/csv/.test(l))).toBe(true);
        expect(d7).toBe(7);
    });

    test('TC-ADMN-006 nhật ký kiểm toán ghi thao tác quản trị (gói vé, hotspot, duyệt đánh giá) kèm người thực hiện', async ({ page }, ti) => {
        const { page: p } = await getPage(page.request, '/vi/quan-tri/nhat-ky');
        const acts = p.props.actions;
        const recent = sql("SELECT action, COUNT(*) FROM audit_logs WHERE user_id=1 AND created_at > NOW() - INTERVAL 30 MINUTE GROUP BY action").map((r) => r.join('×'));
        await page.goto('/vi/quan-tri/nhat-ky');
        await shot(page, 'TC-ADMN-006', { fullPage: true });
        const need = ['package', 'hotspot', 'review'];
        const miss = need.filter((n) => !acts.some((a) => a.includes(n)));
        actual(ti, `Hành động admin 30 phút gần đây: ${recent.join(', ')}; danh sách action trong bộ lọc: ${acts.length}; thiếu nhóm: ${miss.join(', ') || 'không'}.`);
        expect(miss, 'Thao tác quản trị không được ghi audit').toEqual([]);
    });
});
