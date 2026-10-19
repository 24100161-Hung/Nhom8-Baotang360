import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// Tách thư viện bên thứ ba thành chunk riêng để cache lâu dài và chỉ nạp khi trang cần
const VENDOR_GROUPS = [
    ['vendor-vue', ['/node_modules/vue/', '/node_modules/@vue/']],
    ['vendor-inertia', ['/node_modules/@inertiajs/', '/node_modules/axios/', '/node_modules/qs/', '/node_modules/es-toolkit/', '/node_modules/nprogress/']],
    ['vendor-chart', ['/node_modules/chart.js/', '/node_modules/@kurkle/']],
    // Pannellum 2.5.6 chỉ nạp động trong PanoramaViewer (trang tham quan 360° và xem trước hotspot ở admin)
    ['vendor-pannellum', ['/node_modules/pannellum/']],
];

// Component/composable dùng chung của khu công khai gộp một chunk: Apache phục vụ HTTP/1.1 (6 kết nối),
// hàng chục chunk 1–2 KB trên đường găng làm chậm FCP/LCP trên mạng di động nhiều hơn là tiết kiệm được
const SHARED_UI = ['/resources/js/Components/', '/resources/js/Composables/', '/resources/js/Utils/', '/resources/js/Layouts/PublicLayout.vue'];
// Component riêng của admin và của khung xem 360° đi theo chunk của trang dùng chúng (không vào app-ui)
const PAGE_ONLY_UI = ['/resources/js/Components/Admin/', '/resources/js/Components/Viewer/'];

function vendorChunk(id) {
    if (!id.includes('/node_modules/')) {
        if (PAGE_ONLY_UI.some((p) => id.includes(p))) return undefined;
        return SHARED_UI.some((p) => id.includes(p)) ? 'app-ui' : undefined;
    }
    const group = VENDOR_GROUPS.find(([, paths]) => paths.some((p) => id.includes(p)));
    return group ? group[0] : undefined;
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/fonts.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        chunkSizeWarningLimit: 500,
        rollupOptions: {
            output: {
                manualChunks: vendorChunk,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
});
