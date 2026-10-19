import axios from 'axios';
import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { i18nPlugin } from '@/Composables/useT';

// axios tự gửi X-XSRF-TOKEN từ cookie; header này giúp Laravel nhận diện AJAX
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const FONT_FALLBACK_DELAY_MS = 200;

// Nạp trang theo nhu cầu: mỗi trang là một chunk riêng, bundle đầu vào chỉ còn khung ứng dụng
const pages = import.meta.glob('./Pages/**/*.vue');

// Layout mặc định theo thư mục trang (cũng nạp động); trang có thể tự khai báo `layout` để ghi đè
function layoutFor(name) {
    if (name === 'Scenes/Viewer') return import('@/Layouts/ViewerLayout.vue');
    if (name.startsWith('Admin/')) return import('@/Layouts/AdminLayout.vue');
    if (name.startsWith('Auth/')) return import('@/Layouts/AuthLayout.vue');
    return import('@/Layouts/PublicLayout.vue');
}

createInertiaApp({
    title: (title) => (title ? `${title} – BaoTang360` : 'BaoTang360 – Naturalis 360°'),
    resolve: async (name) => {
        const loader = pages[`./Pages/${name}.vue`];
        if (!loader) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        const [page, layout] = await Promise.all([loader(), layoutFor(name)]);
        if (page.default.layout === undefined) {
            page.default.layout = layout.default;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nPlugin)
            .mount(el);
        // Vue đã mount: ẩn chân trang tĩnh server-render (app.blade.php), layout Vue hiển thị chân trang riêng
        document.documentElement.classList.add('js-mounted');
        // Nạp font tự host khi rảnh, sau khi nội dung đầu tiên đã vẽ (hàm định nghĩa trong app.blade.php)
        const whenIdle = window.requestIdleCallback || ((cb) => window.setTimeout(cb, FONT_FALLBACK_DELAY_MS));
        whenIdle(() => window.bt360LoadFonts?.());
        // Đổi ngôn ngữ bằng Inertia visit (không tải lại trang): đồng bộ <html lang>
        router.on('navigate', (event) => {
            const locale = event.detail.page.props?.locale;
            if (locale) document.documentElement.setAttribute('lang', locale);
        });
    },
    progress: {
        color: '#C9811C',
        showSpinner: false,
    },
});
