<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StarRating from '@/Components/StarRating.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import TourCard from '@/Components/TourCard.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    orders: { type: Array, default: () => [] },
    membership: { type: Object, default: null },
    favorites: { type: Object, default: () => ({ artifacts: [], tours: [] }) },
    reviews: { type: Array, default: () => [] },
    tourProgress: { type: Array, default: () => [] },
});
const { t } = useT();
const { money, date, dateTime } = useFormat();
const lr = useLocaleRoute();
const { user, canAccessAdmin, adminHome } = usePermissions();

const favArtifacts = computed(() => props.favorites?.artifacts || []);
const favTours = computed(() => props.favorites?.tours || []);
const pendingOrders = computed(() => props.orders.filter((o) => o.status === 'pending').length);
const itemsText = (o) => (o.items || []).map((i) => `${i.package_name} × ${i.quantity}`).join(', ');
</script>

<template>
    <Head :title="t('account.dashboard_title')" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.dashboard')" :title="t('account.greeting', { name: user?.name || '' })" :description="t('account.dashboard_intro')">
            <template #actions>
                <Link :href="lr('/tai-khoan/ho-so')" class="btn"><Icon name="user" :size="18" />{{ t('nav.profile') }}</Link>
                <Link :href="lr('/tham-quan-360')" class="btn btn-primary"><Icon name="panorama" :size="18" />{{ t('nav.tour360') }}</Link>
                <Link v-if="canAccessAdmin" :href="lr(adminHome)" class="btn"><Icon name="shield" :size="18" />{{ t('nav.admin') }}</Link>
            </template>
        </PageHeader>

        <p v-if="pendingOrders" class="mb-6 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="status">
            <Icon name="clock" :size="18" />{{ t('account.pending_orders', { n: pendingOrders }) }}
        </p>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card p-5 lg:col-span-1" aria-labelledby="mem-title">
                <h2 id="mem-title" class="font-body text-lg font-bold">{{ t('account.membership') }}</h2>
                <template v-if="membership">
                    <p class="mt-3 flex items-center gap-2">
                        <Icon name="award" class="text-accent-strong" />
                        <span class="font-semibold">{{ membership.package_name || membership.package_code || 'Premium' }}</span>
                        <StatusBadge :status="membership.is_active ? 'active' : membership.status || 'expired'" group="membership_status" />
                    </p>
                    <p v-if="membership.ends_at" class="mt-2 text-sm text-muted">{{ t('account.membership_until', { date: date(membership.ends_at) }) }}</p>
                    <p v-if="membership.is_active" class="mt-2 text-sm">{{ t('account.membership_benefit') }}</p>
                </template>
                <template v-else>
                    <p class="mt-2 text-sm text-muted">{{ t('account.no_membership') }}</p>
                    <Link :href="lr('/ve')" class="btn btn-accent mt-3"><Icon name="award" :size="18" />{{ t('account.get_premium') }}</Link>
                </template>
            </section>

            <section class="card p-5 lg:col-span-2" aria-labelledby="orders-title">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="orders-title" class="font-body text-lg font-bold">{{ t('account.my_orders') }}</h2>
                    <Link :href="lr('/ve')" class="link text-sm">{{ t('account.buy_tickets') }}</Link>
                </div>
                <EmptyState v-if="!orders.length" icon="ticket" :title="t('account.no_orders')" compact class="mt-3" />
                <ul v-else class="mt-3 divide-y divide-border">
                    <li v-for="o in orders" :key="o.code" class="flex flex-wrap items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <Link :href="lr(`/don-hang/${o.code}`)" class="font-mono text-sm font-bold hover:text-primary">{{ o.code }}</Link>
                            <p class="truncate text-xs text-muted">{{ itemsText(o) }}</p>
                            <p class="text-xs text-muted">{{ o.paid_at ? dateTime(o.paid_at) : o.hold_expires_at ? t('account.hold_until', { time: dateTime(o.hold_expires_at) }) : '' }}</p>
                        </div>
                        <StatusBadge :status="o.status" group="order_status" :label="o.status_label" />
                        <span class="w-28 text-right font-semibold tabular-nums">{{ money(o.total_vnd) }}</span>
                    </li>
                </ul>
            </section>
        </div>

        <section class="mt-8" aria-labelledby="progress-title">
            <h2 id="progress-title" class="section-title mb-4">{{ t('account.tour_progress') }}</h2>
            <EmptyState v-if="!tourProgress.length" icon="route" :title="t('account.no_tour_progress')" compact>
                <Link :href="lr('/tour')" class="btn btn-primary btn-sm">{{ t('account.browse_tours') }}</Link>
            </EmptyState>
            <ul v-else class="grid gap-3 md:grid-cols-2">
                <li v-for="(p, i) in tourProgress" :key="p.tour?.slug ?? i" class="card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <Link :href="p.tour?.url || lr('/tour')" class="min-w-0 truncate font-semibold hover:text-primary">{{ p.tour?.name }}</Link>
                        <span v-if="p.completed_at" class="badge badge-success">{{ t('account.completed') }}</span>
                    </div>
                    <div class="mt-2 flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2" role="progressbar" :aria-valuenow="Math.round(p.percent || 0)" aria-valuemin="0" aria-valuemax="100" :aria-label="t('account.progress_of', { name: p.tour?.name || '' })">
                            <div class="h-full rounded-full bg-primary" :style="{ width: `${Math.min(100, p.percent || 0)}%` }"></div>
                        </div>
                        <span class="text-sm font-semibold tabular-nums">{{ Math.round(p.percent || 0) }}%</span>
                    </div>
                    <p v-if="p.completed_at" class="mt-1 text-xs text-muted">{{ t('account.completed_at', { date: date(p.completed_at) }) }}</p>
                </li>
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="fav-title">
            <h2 id="fav-title" class="section-title mb-4">{{ t('account.favorites') }}</h2>
            <EmptyState v-if="!favArtifacts.length && !favTours.length" icon="heart" :title="t('account.no_favorites')" :description="t('account.no_favorites_hint')" compact />
            <template v-else>
                <h3 v-if="favArtifacts.length" class="mb-3 font-body text-base font-bold">{{ t('nav.artifacts') }}</h3>
                <div v-if="favArtifacts.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <ArtifactCard v-for="a in favArtifacts" :key="a.slug" :artifact="a" :heading-level="4" />
                </div>
                <h3 v-if="favTours.length" class="mb-3 mt-6 font-body text-base font-bold">{{ t('nav.tours') }}</h3>
                <div v-if="favTours.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <TourCard v-for="tr in favTours" :key="tr.slug" :tour="tr" :heading-level="4" />
                </div>
            </template>
        </section>

        <section class="mt-8" aria-labelledby="rev-title">
            <h2 id="rev-title" class="section-title mb-4">{{ t('account.my_reviews') }}</h2>
            <EmptyState v-if="!reviews.length" icon="star" :title="t('account.no_reviews')" compact />
            <ul v-else class="space-y-3">
                <li v-for="r in reviews" :key="r.id" class="card p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <StarRating :value="r.rating" readonly :size="16" />
                        <StatusBadge :status="r.status" group="review_status" />
                        <span class="ml-auto text-xs text-muted">{{ date(r.created_at) }}</span>
                    </div>
                    <p v-if="r.subject?.title" class="mt-1 text-sm text-muted">
                        <Link v-if="r.subject.url" :href="r.subject.url" class="link">{{ r.subject.title }}</Link><template v-else>{{ r.subject.title }}</template>
                    </p>
                    <p v-if="r.title" class="mt-1 font-semibold">{{ r.title }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ r.body }}</p>
                </li>
            </ul>
        </section>
    </div>
</template>
