<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import Icon from '@/Components/Icon.vue';
import FlashToast from '@/Components/FlashToast.vue';
import AcademicFooter from '@/Components/AcademicFooter.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';

const page = usePage();
const { t, te } = useT();
const lr = useLocaleRoute();
const { user, role } = usePermissions();
const sidebarOpen = ref(false);

// Mỗi mục khai báo các role được thấy; máy chủ vẫn kiểm tra quyền (curator không quản lý vé/đơn/tài khoản)
const ALL = ['admin', 'curator'];
const ADMIN = ['admin'];
const groups = [
    { key: 'overview', items: [{ key: 'dashboard', path: '/quan-tri', icon: 'dashboard', roles: ALL, exact: true }] },
    {
        key: 'content',
        items: [
            { key: 'scenes', path: '/quan-tri/khong-gian-360', icon: 'panorama', roles: ALL },
            { key: 'artifacts', path: '/quan-tri/hien-vat', icon: 'bone', roles: ALL },
            { key: 'tours', path: '/quan-tri/tour', icon: 'route', roles: ALL },
            { key: 'posts', path: '/quan-tri/blog', icon: 'book', roles: ALL },
        ],
    },
    {
        key: 'sales',
        items: [
            { key: 'orders', path: '/quan-tri/don-hang', icon: 'cart', roles: ADMIN },
            { key: 'packages', path: '/quan-tri/goi-ve', icon: 'ticket', roles: ADMIN },
            { key: 'visitdays', path: '/quan-tri/lich-ve', icon: 'calendar', roles: ADMIN },
        ],
    },
    {
        key: 'community',
        items: [
            { key: 'reviews', path: '/quan-tri/danh-gia', icon: 'star', roles: ALL },
            { key: 'guestbook', path: '/quan-tri/so-luu-but', icon: 'edit', roles: ALL },
            { key: 'contacts', path: '/quan-tri/lien-he', icon: 'mail', roles: ADMIN },
        ],
    },
    {
        key: 'analytics',
        items: [
            { key: 'stats', path: '/quan-tri/thong-ke', icon: 'activity', roles: ALL },
            { key: 'flow', path: '/quan-tri/phan-tich-luong', icon: 'sliders', roles: ALL },
            { key: 'quality', path: '/quan-tri/chat-luong-du-lieu', icon: 'check', roles: ALL },
        ],
    },
    {
        key: 'system',
        items: [
            { key: 'users', path: '/quan-tri/nguoi-dung', icon: 'users', roles: ADMIN },
            { key: 'audit', path: '/quan-tri/nhat-ky', icon: 'file', roles: ADMIN },
        ],
    },
];

const visible = computed(() =>
    groups
        .map((g) => ({ ...g, items: g.items.filter((i) => i.roles.includes(role.value)) }))
        .filter((g) => g.items.length),
);
const currentPath = computed(() => (page.url || '').split('?')[0]);
const isActive = (item) =>
    item.exact ? currentPath.value === lr(item.path) : currentPath.value.startsWith(lr(item.path));

router.on('navigate', () => (sidebarOpen.value = false));
</script>

<template>
    <div class="min-h-screen bg-bg lg:flex">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[1200] btn btn-primary">
            {{ t('common.skip_to_content') }}
        </a>

        <div v-if="sidebarOpen" class="fixed inset-0 z-[1001] bg-overlay lg:hidden" aria-hidden="true" @click="sidebarOpen = false"></div>

        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-[1002] flex w-64 flex-col border-r border-border bg-surface transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center justify-between border-b border-border px-4">
                <Link :href="lr('/')" class="rounded-lg" :aria-label="t('nav.home_logo')"><AppLogo /></Link>
                <button type="button" class="btn btn-ghost btn-icon lg:hidden" :aria-label="t('common.close_menu')" @click="sidebarOpen = false">
                    <Icon name="close" />
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto p-3" :aria-label="t('admin.nav_label')">
                <template v-for="g in visible" :key="g.key">
                <p class="px-3 pb-1 pt-3 text-xs font-bold uppercase tracking-wider text-muted first:pt-0">{{ t(`admin.nav_group.${g.key}`) }}</p>
                <ul class="space-y-0.5">
                    <li v-for="item in g.items" :key="item.key">
                        <Link
                            :href="lr(item.path)"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-surface-2"
                            :class="isActive(item) ? 'bg-primary-soft text-primary' : ''"
                            :aria-current="isActive(item) ? 'page' : undefined"
                        >
                            <Icon :name="item.icon" :size="18" />
                            {{ t(`admin.nav.${item.key}`) }}
                        </Link>
                    </li>
                </ul>
                </template>
            </nav>
            <div class="border-t border-border p-3 text-sm">
                <Link :href="lr('/')" class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-surface-2">
                    <Icon name="chevronLeft" :size="18" /> {{ t('admin.back_to_site') }}
                </Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-[1000] flex h-16 items-center gap-2 border-b border-border bg-bg/90 px-4 backdrop-blur">
                <button
                    type="button"
                    class="btn btn-ghost btn-icon lg:hidden"
                    aria-controls="admin-sidebar"
                    :aria-expanded="sidebarOpen"
                    :aria-label="t('common.open_menu')"
                    @click="sidebarOpen = true"
                >
                    <Icon name="menu" />
                </button>
                <div class="ml-auto flex items-center gap-1">
                    <LocaleSwitch />
                    <ThemeToggle />
                    <span v-if="user" class="hidden items-center gap-2 px-2 text-sm sm:flex">
                        <span class="font-medium">{{ user.name }}</span>
                        <span class="badge badge-accent">{{ te('roles', user.role) }}</span>
                    </span>
                    <Link :href="lr('/dang-xuat')" method="post" as="button" class="btn btn-ghost btn-icon" :aria-label="t('nav.logout')">
                        <Icon name="logout" />
                    </Link>
                </div>
            </header>
            <FlashToast />
            <main id="main" class="min-w-0 flex-1 p-4 md:p-6" tabindex="-1">
                <slot />
            </main>
            <footer :aria-label="t('footer.label')">
                <AcademicFooter />
            </footer>
        </div>
    </div>
</template>
