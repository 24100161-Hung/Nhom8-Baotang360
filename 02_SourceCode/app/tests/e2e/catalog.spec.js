// CATL: danh mục hiện vật, lọc, tìm kiếm, phân trang, sắp xếp, chi tiết, blog.
import { test, expect } from '@playwright/test';
import { actual, shot, showText, getPage, sql } from './helpers.js';

test('TC-CATL-001 danh sách hiện vật: 12/trang, tổng khớp CSDL, thẻ có ảnh và tên', async ({ page }, ti) => {
    const { page: p } = await getPage(page.request, '/vi/hien-vat');
    const a = p.props.artifacts;
    const db = sql("SELECT COUNT(*) FROM artifacts WHERE status='published' AND deleted_at IS NULL")[0][0];
    await page.goto('/vi/hien-vat');
    const cards = await page.locator('main a[href*="/vi/hien-vat/"]').count();
    await shot(page, 'TC-CATL-001', { fullPage: true });
    actual(ti, `per_page=${a.per_page}, total=${a.total} (CSDL published=${db}), last_page=${a.last_page}; trang 1 có ${a.data.length} hiện vật, ${cards} liên kết chi tiết trên giao diện; mọi thẻ có cover: ${a.data.every((x) => x.cover?.url)}.`);
    expect(a.per_page).toBe(12);
    expect(a.data.length).toBe(12);
    expect(String(a.total)).toBe(db);
});

test('TC-CATL-002 lọc theo thời kỳ (Kỷ Phấn trắng) → mọi kết quả đúng thời kỳ', async ({ page }, ti) => {
    await page.goto('/vi/hien-vat?period=cretaceous');
    const { page: p } = await getPage(page.request, '/vi/hien-vat?period=cretaceous');
    const a = p.props.artifacts;
    const db = sql("SELECT COUNT(*) FROM artifacts a JOIN periods p ON p.id=a.period_id WHERE p.code='cretaceous' AND a.status='published' AND a.deleted_at IS NULL")[0][0];
    await shot(page, 'TC-CATL-002', { fullPage: true });
    actual(ti, `total=${a.total} (CSDL ${db}); tất cả period.code=cretaceous: ${a.data.every((x) => x.period?.code === 'cretaceous')}.`);
    expect(String(a.total)).toBe(db);
    expect(a.data.every((x) => x.period?.code === 'cretaceous')).toBe(true);
});

test('TC-CATL-003 lọc kết hợp chất liệu + nguồn gốc + nhóm sinh vật; mọi nhóm sinh vật trong bộ lọc có nhãn đã dịch', async ({ page }, ti) => {
    const { page: base } = await getPage(page.request, '/vi/hien-vat');
    const mat = base.props.materials.find((m) => /hộp sọ/i.test(m.code)).code;
    const url = `/vi/hien-vat?material=${encodeURIComponent(mat)}&origin=NL&taxon=mammalia`;
    const { page: p } = await getPage(page.request, url);
    const a = p.props.artifacts;
    const db = sql(`SELECT COUNT(*) FROM artifacts WHERE material='${mat}' AND origin_country='NL' AND taxon_group='mammalia' AND status='published' AND deleted_at IS NULL`)[0][0];
    const raw = base.props.taxa.filter((t) => /^enums\./.test(t.name)).map((t) => t.name);
    await page.goto(url);
    await shot(page, 'TC-CATL-003', { fullPage: true });
    actual(ti, `material="${mat}"&origin=NL&taxon=mammalia: total=${a.total} (CSDL ${db}); mọi kết quả khớp 3 điều kiện: ${a.data.every((x) => x.material === mat && x.origin_country === 'NL' && x.taxon_group === 'mammalia')}. Nhóm sinh vật hiển thị khóa dịch thô trong bộ lọc (vi và en): ${raw.join(', ') || 'không'}.`);
    expect(a.total).toBeGreaterThan(0);
    expect(String(a.total)).toBe(db);
    expect(a.data.every((x) => x.material === mat && x.origin_country === 'NL' && x.taxon_group === 'mammalia')).toBe(true);
    expect(raw, 'Bộ lọc nhóm sinh vật hiển thị khóa dịch thô').toEqual([]);
});

test('TC-CATL-004 tìm kiếm "khung long" (không dấu) → kết quả liên quan khủng long, engine TF-IDF', async ({ page }, ti) => {
    await page.goto('/vi/tim-kiem?q=khung+long');
    const { page: p } = await getPage(page.request, '/vi/tim-kiem?q=khung+long');
    const res = p.props.results;
    await shot(page, 'TC-CATL-004', { fullPage: true });
    const top = res.slice(0, 5).map((r) => `${r.type}:${r.slug}`);
    actual(ti, `engine=${p.props.engine}, ${res.length} kết quả, ${p.props.durationMs} ms; top 5: ${top.join(', ')}; điểm giảm dần: ${res.every((r, i) => i === 0 || r.score <= res[i - 1].score)}.`);
    expect(res.length).toBeGreaterThan(3);
    expect(res.slice(0, 5).some((r) => /khung|dino|rex|compsognathus/i.test(r.slug + r.title))).toBe(true);
});

test('TC-CATL-005 tìm kiếm chuỗi không khớp/ký tự đặc biệt → trạng thái rỗng, không lỗi', async ({ page }, ti) => {
    await page.goto('/vi/tim-kiem?q=zzqxwv9999');
    const { status, page: p } = await getPage(page.request, '/vi/tim-kiem?q=zzqxwv9999');
    const s2 = await getPage(page.request, '/vi/tim-kiem?q=' + encodeURIComponent('%_\\"<>'));
    await shot(page, 'TC-CATL-005');
    const empty = await page.getByText(/Không (tìm thấy|có) kết quả/i).first().isVisible().catch(() => false);
    actual(ti, `q=zzqxwv9999 → ${status}, ${p.props.results.length} kết quả, thông báo rỗng hiển thị: ${empty}; q=%_\\"<> → ${s2.status}, ${s2.page?.props?.results?.length} kết quả.`);
    expect(status).toBe(200);
    expect(p.props.results.length).toBe(0);
    expect(s2.status).toBe(200);
});

test('TC-CATL-006 phân trang biên: page=last, last+1, 0, -1, abc', async ({ page }, ti) => {
    const out = [];
    const { page: first } = await getPage(page.request, '/vi/hien-vat');
    const last = first.props.artifacts.last_page;
    for (const pg of [last, last + 1, 0, -1, 'abc']) {
        const { status, page: p } = await getPage(page.request, `/vi/hien-vat?page=${pg}`);
        out.push(`page=${pg} → ${status}, current=${p?.props?.artifacts?.current_page}, n=${p?.props?.artifacts?.data?.length}`);
    }
    await page.goto(`/vi/hien-vat?page=${last}`);
    await shot(page, 'TC-CATL-006', { fullPage: true });
    actual(ti, out.join('; '));
    expect(out.every((l) => / → 200,/.test(l))).toBe(true);
    expect(out[0]).toMatch(new RegExp(`n=${193 - (last - 1) * 12}$`));
});

test('TC-CATL-007 sắp xếp hợp lệ và giá trị sort ngoài danh sách cho phép (SQLi) → bỏ qua, không lỗi', async ({ page }, ti) => {
    const name = (await getPage(page.request, '/vi/hien-vat?sort=name')).page.props.artifacts.data.map((x) => x.title);
    const sorted = [...name].sort((a, b) => a.localeCompare(b, 'vi'));
    const out = [];
    for (const s of ['password', 'id;DROP TABLE users', "name' OR 1=1--", 'created_at desc']) {
        const { status, page: p } = await getPage(page.request, `/vi/hien-vat?sort=${encodeURIComponent(s)}`);
        out.push(`${s} → ${status}, total=${p?.props?.artifacts?.total}, filters.sort=${p?.props?.filters?.sort}`);
    }
    const users = sql('SELECT COUNT(*) FROM users')[0][0];
    await showText(page, 'TC-CATL-007', 'sort allow-list', `sort=name: ${name.slice(0, 5).join(' | ')}\n` + out.join('\n') + `\nusers còn ${users} bản ghi`);
    actual(ti, `sort=name theo bảng chữ cái: ${JSON.stringify(name) === JSON.stringify(sorted)}; ` + out.join('; ') + `; bảng users còn ${users} bản ghi.`);
    expect(out.every((l) => / → 200,/.test(l) && /total=193/.test(l))).toBe(true);
});

test('TC-CATL-008 chi tiết hiện vật T. rex: ảnh, tên khoa học, nguồn gốc, thời kỳ, chất liệu, kích thước, diễn giải 2 độ sâu', async ({ page }, ti) => {
    const { page: p } = await getPage(page.request, '/vi/hien-vat/trix-tyrannosaurus-rex');
    const a = p.props.artifact;
    const d2 = (await getPage(page.request, '/vi/hien-vat/trix-tyrannosaurus-rex?depth=2')).page.props.artifact.interpretation;
    await page.goto('/vi/hien-vat/trix-tyrannosaurus-rex');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Trix');
    await shot(page, 'TC-CATL-008', { fullPage: true });
    actual(ti, `title="${a.title}", scientific_name="${a.scientific_name}", period=${a.period?.name}, origin=${a.origin_country}/${a.origin_locality}, material=${a.material}, kích thước ${a.length_cm}×${a.width_cm}×${a.height_cm} cm, ảnh=${a.media.images.length}, license=${a.image_license}, depths=${JSON.stringify(a.available_depths)}, depth2 tiêu đề="${d2?.title}", jsonld=${!!a.jsonld}.`);
    expect(a.scientific_name).toMatch(/Tyrannosaurus/);
    expect(a.media.images.length).toBeGreaterThan(0);
    expect(a.available_depths.length).toBeGreaterThanOrEqual(2);
});

test('TC-CATL-009 blog lọc danh mục Khoa học; chi tiết bài có ảnh bìa, tác giả, ngày, lượt xem', async ({ page }, ti) => {
    const { page: p } = await getPage(page.request, '/vi/blog?danh-muc=khoa-hoc');
    const posts = p.props.posts.data;
    const { page: d } = await getPage(page.request, new URL(posts[0].url).pathname);
    const post = d.props.post;
    await page.goto('/vi/blog?danh-muc=khoa-hoc');
    await shot(page, 'TC-CATL-009', { fullPage: true });
    actual(ti, `danh-muc=khoa-hoc: ${posts.length} bài, tất cả category=khoa_hoc: ${posts.every((x) => x.category.code === 'khoa_hoc')}; bài "${post.title}": cover=${!!post.cover_url}, tác giả=${post.author?.name}, ngày=${post.published_at}, lượt xem=${post.view_count}, body ${post.body_html.length} ký tự.`);
    expect(posts.length).toBeGreaterThan(0);
    expect(posts.every((x) => x.category.code === 'khoa_hoc')).toBe(true);
    expect(post.author?.name).toBeTruthy();
    expect(post.view_count).not.toBeUndefined();
});

test('TC-CATL-010 hiện vật/bài viết/phòng không tồn tại → 404 thân thiện, không lộ thông tin kỹ thuật', async ({ page }, ti) => {
    const out = [];
    for (const u of ['/vi/hien-vat/khong-ton-tai-xyz', '/vi/blog/khong-ton-tai', '/vi/tham-quan-360/khong-ton-tai', '/vi/tour/khong-ton-tai', '/api/v1/artifacts/khong-ton-tai']) {
        const r = await page.request.get(u);
        const body = await r.text();
        out.push(`${u} → ${r.status()}${/(Exception|vendor\/|Stack trace|SQLSTATE)/.test(body) ? ' LỘ' : ''}`);
    }
    const res = await page.goto('/vi/hien-vat/khong-ton-tai-xyz');
    await shot(page, 'TC-CATL-010');
    actual(ti, out.join('; ') + `; trang hiển thị: "${(await page.locator('body').innerText()).replace(/\s+/g, ' ').trim().slice(0, 90)}".`);
    expect(res.status()).toBe(404);
    expect(out.every((l) => / → 404$/.test(l))).toBe(true);
});
