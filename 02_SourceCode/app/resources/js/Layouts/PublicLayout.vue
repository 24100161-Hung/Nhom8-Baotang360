<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import Icon from '@/Components/Icon.vue';
import FlashToast from '@/Components/FlashToast.vue';
import AcademicFooter from '@/Components/AcademicFooter.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AccountMenu from '@/Components/AccountMenu.vue';
import ConsultButton from '@/Components/ConsultButton.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const mobileOpen = ref(false);

const nav = [
    { key: 'museum', path: '/bao-tang' },
    { key: 'tour360', path: '/tham-quan-360' },
    { key: 'artifacts', path: '/hien-vat' },
    { key: 'tours', path: '/tour' },
    { key: 'tickets', path: '/ve' },
    { key: 'blog', path: '/blog' },
    { key: 'contact', path: '/lien-he' },
];
const quickLinks = [
    { key: 'about', path: '/gioi-thieu' },
    { key: 'guestbook', path: '/so-luu-but' },
    { key: 'search', path: '/tim-kiem' },
    { key: 'cart', path: '/gio-hang' },
];

const app = computed(() => page.props.app || {});
const museum = computed(() => app.value.museum || {});
const cartCount = computed(() => Number(page.props.cart?.count || 0));
const locales = computed(() => page.props.locales || [{ code: 'vi', label: 'Tiếng Việt' }, { code: 'en', label: 'English' }]);
const currentPath = computed(() => (page.url || '').split('?')[0]);
const isActive = (path) => currentPath.value === lr(path) || currentPath.value.startsWith(`${lr(path)}/`);
const telHref = computed(() => (museum.value.phone ? `tel:${String(museum.value.phone).replace(/[^\d+]/g, '')}` : null));

function switchTo(code, e) {
    e.preventDefault();
    if (code === page.props.locale) return;
    router.visit(page.props.currentUrlOtherLocale || `/${code}`, { preserveState: true, preserveScroll: true });
}

// Đóng menu di động sau mỗi lần điều hướng
router.on('navigate', () => (mobileOpen.value = false));
watch(mobileOpen, (v) => document.body.classList.toggle('overflow-hidden', v));
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[1200] btn btn-primary">
            {{ t('common.skip_to_content') }}
        </a>

        <header class="sticky top-0 z-[1000] border-b border-border bg-bg/90 backdrop-blur supports-[backdrop-filter]:bg-bg/80">
            <div class="container-page flex h-16 items-center gap-2">
                <Link :href="lr('/')" class="shrink-0 rounded-lg" :aria-label="t('nav.home_logo')">
                    <AppLogo />
                </Link>

                <nav class="ml-3 hidden flex-1 items-center gap-0.5 lg:flex" :aria-label="t('nav.main')">
                    <Link
                        v-for="item in nav"
                        :key="item.key"
                        :href="lr(item.path)"
                        class="whitespace-nowrap rounded-full px-3 py-1.5 text-sm font-medium transition-colors hover:bg-surface-2"
                        :class="isActive(item.path) ? 'bg-primary-soft text-primary' : ''"
                        :aria-current="isActive(item.path) ? 'page' : undefined"
                    >
                        {{ t(`nav.${item.key}`) }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-0.5">
                    <Link :href="lr('/tim-kiem')" class="btn btn-ghost btn-icon hidden sm:inline-flex" :aria-label="t('nav.search')" :title="t('nav.search')">
                        <Icon name="search" />
                    </Link>
                    <Link :href="lr('/gio-hang')" class="btn btn-ghost btn-icon relative" :aria-label="t('nav.cart_count', { n: cartCount })" :title="t('nav.cart')">
                        <Icon name="cart" />
                        <span v-if="cartCount" class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold text-[#1A1206]" aria-hidden="true">{{ cartCount > 99 ? '99+' : cartCount }}</span>
                    </Link>
                    <LocaleSwitch />
                    <ThemeToggle />
                    <div class="hidden md:block"><AccountMenu /></div>
                    <button
                        type="button"
                        class="btn btn-ghost btn-icon lg:hidden"
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-nav"
                        :aria-label="mobileOpen ? t('common.close_menu') : t('common.open_menu')"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Icon :name="mobileOpen ? 'close' : 'menu'" />
                    </button>
                </div>
            </div>

            <div v-show="mobileOpen" id="mobile-nav" class="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-border bg-bg lg:hidden">
                <nav class="container-page grid gap-1 py-3 sm:grid-cols-2" :aria-label="t('nav.mobile')">
                    <Link
                        v-for="item in [...nav, ...quickLinks]"
                        :key="item.key"
                        :href="lr(item.path)"
                        class="rounded-lg px-3 py-2.5 font-medium hover:bg-surface-2"
                        :class="isActive(item.path) ? 'bg-primary-soft text-primary' : ''"
                        :aria-current="isActive(item.path) ? 'page' : undefined"
                    >
                        {{ t(`nav.${item.key}`) }}
                    </Link>
                </nav>
                <div class="container-page border-t border-border py-3 md:hidden">
                    <AccountMenu />
                </div>
            </div>
        </header>

        <FlashToast />

        <main id="main" class="flex-1" tabindex="-1">
            <slot />
        </main>

        <footer class="mt-16 border-t border-border bg-surface">
            <div class="ornament"></div>
            <div class="container-page grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <AppLogo />
                    <p class="mt-3 max-w-sm text-sm text-muted">{{ t('footer.tagline') }}</p>
                    <address class="mt-4 space-y-1.5 text-sm not-italic">
                        <p class="font-semibold">{{ museum.name || 'Naturalis Biodiversity Center' }}</p>
                        <p v-if="museum.address" class="flex items-start gap-2"><Icon name="pin" :size="16" class="mt-0.5 text-muted" />{{ museum.address }}</p>
                        <p v-if="museum.phone" class="flex items-center gap-2"><Icon name="phone" :size="16" class="text-muted" /><a :href="telHref" class="hover:text-primary hover:underline">{{ museum.phone }}</a></p>
                        <p v-if="museum.email" class="flex items-center gap-2"><Icon name="mail" :size="16" class="text-muted" /><a :href="`mailto:${museum.email}`" class="break-all hover:text-primary hover:underline">{{ museum.email }}</a></p>
                        <p v-if="museum.website" class="flex items-center gap-2"><Icon name="globe" :size="16" class="text-muted" /><a :href="museum.website" target="_blank" rel="noopener noreferrer" class="break-all hover:text-primary hover:underline">{{ museum.website.replace(/^https?:\/\//, '') }}</a></p>
                    </address>
                </div>
                <nav :aria-label="t('footer.explore')">
                    <h2 class="mb-3 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('footer.explore') }}</h2>
                    <ul class="grid gap-2 text-sm">
                        <li v-for="item in nav" :key="item.key">
                            <Link :href="lr(item.path)" class="hover:text-primary hover:underline">{{ t(`nav.${item.key}`) }}</Link>
                        </li>
                    </ul>
                </nav>
                <nav :aria-label="t('footer.quick_links')">
                    <h2 class="mb-3 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('footer.quick_links') }}</h2>
                    <ul class="grid gap-2 text-sm">
                        <li v-for="item in quickLinks" :key="item.key">
                            <Link :href="lr(item.path)" class="hover:text-primary hover:underline">{{ t(`nav.${item.key}`) }}</Link>
                        </li>
                    </ul>
                    <h2 class="mb-2 mt-5 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('footer.language') }}</h2>
                    <ul class="flex gap-2 text-sm">
                        <li v-for="l in locales" :key="l.code">
                            <a
                                :href="l.code === page.props.locale ? `/${l.code}` : page.props.currentUrlOtherLocale || `/${l.code}`"
                                :hreflang="l.code"
                                :lang="l.code"
                                class="badge px-3 py-1"
                                :class="l.code === page.props.locale ? 'badge-primary' : 'hover:border-primary'"
                                :aria-current="l.code === page.props.locale ? 'true' : undefined"
                                @click="switchTo(l.code, $event)"
                            >{{ l.label || l.code.toUpperCase() }}</a>
                        </li>
                    </ul>
                </nav>
                <div class="text-sm">
                    <h2 class="mb-3 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('footer.academic') }}</h2>
                    <dl class="space-y-1">
                        <div><dt class="inline text-muted">{{ t('footer.course_code') }}:</dt> <dd class="inline font-medium">{{ app.courseCode || 'CSE703073' }}</dd></div>
                        <div v-if="app.course"><dt class="inline text-muted">{{ t('footer.course') }}:</dt> <dd class="inline font-medium">{{ app.course }}</dd></div>
                        <div><dt class="inline text-muted">{{ t('footer.team') }}:</dt> <dd class="inline font-medium">{{ t('footer.team_value') }}</dd></div>
                    </dl>
                    <p class="mt-3 rounded-lg bg-accent-soft px-3 py-2 text-xs font-semibold text-accent-strong">
                        {{ app.notice || t('footer.academic_notice') }}
                    </p>
                </div>
            </div>
            <div class="border-t border-border">
                <div class="container-page flex flex-col gap-1 py-4 pr-36 text-xs text-muted md:flex-row md:items-center md:justify-between">
                    <p>
                        {{ t('footer.attribution_prefix') }}
                        <a :href="museum.website || 'https://www.naturalis.nl'" target="_blank" rel="noopener noreferrer" class="font-semibold text-text hover:underline">Naturalis Biodiversity Center</a>
                        –
                        <a href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank" rel="noopener noreferrer license" class="font-semibold text-text hover:underline">CC BY-SA 3.0</a>
                    </p>
                    <p>© 2026 {{ app.name || 'BaoTang360' }} · CSE703073 – {{ t('footer.team_value') }} – “{{ t('footer.academic_product') }}”</p>
                </div>
                <div class="container-page border-t border-border py-3 pr-36">
                    <AcademicFooter variant="full" />
                </div>
            </div>
        </footer>

        <ConsultButton />
    </div>
</template>
