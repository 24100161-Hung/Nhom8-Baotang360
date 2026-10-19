<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import SceneCard from '@/Components/SceneCard.vue';
import TourCard from '@/Components/TourCard.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import PackageCard from '@/Components/PackageCard.vue';
import PostCard from '@/Components/PostCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SectionHeading from '@/Components/Public/SectionHeading.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useFormat } from '@/Composables/useFormat';

const props = defineProps({
    museum: { type: Object, default: () => ({}) },
    featuredTours: { type: Array, default: () => [] },
    packages: { type: Array, default: () => [] },
    latestPosts: { type: Array, default: () => [] },
    highlightArtifacts: { type: Array, default: () => [] },
    scenes: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});
const { t } = useT();
const lr = useLocaleRoute();
const { number } = useFormat();

// Ảnh hero: ảnh xem trước của Main Hall (phòng đầu tiên), không có thì nền tối thuần
const heroScene = computed(() => props.scenes.find((s) => s.code === 'main_hall') || props.scenes[0] || null);
const startUrl = computed(() => heroScene.value?.url || lr('/tham-quan-360'));
const statItems = computed(() => [
    { key: 'scenes', icon: 'panorama', value: props.stats?.scenes ?? props.scenes.length },
    { key: 'artifacts', icon: 'bone', value: props.stats?.artifacts },
    { key: 'tours', icon: 'route', value: props.stats?.tours },
    { key: 'visits', icon: 'eye', value: props.stats?.visits },
]);
const fmt = (v) => (v === null || v === undefined ? '—' : number(v));
const steps = ['choose', 'explore', 'guided'];
const stepIcon = { choose: 'door', explore: 'compass', guided: 'route' };
</script>

<template>
    <PageMeta :title="t('home.meta_title')" fallback-key="home.hero_subtitle" :image="heroScene?.preview_url" />

    <!-- Hero: ảnh 360° phòng Main Hall phủ nền tối để chữ trắng đạt tương phản AA -->
    <section class="relative isolate overflow-hidden bg-hero-scrim text-on-hero">
        <img
            v-if="heroScene?.preview_url"
            :src="heroScene.preview_url"
            :alt="t('home.hero_image_alt', { name: heroScene.title })"
            class="absolute inset-0 -z-10 h-full w-full object-cover opacity-60"
            fetchpriority="high"
            decoding="async"
        />
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[var(--ds-hero-scrim-strong)] via-[var(--ds-hero-scrim-soft)] to-transparent" aria-hidden="true"></div>
        <div class="container-page py-16 md:py-24 lg:py-28">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-accent">{{ t('home.eyebrow') }}</p>
            <h1 class="mt-3 max-w-3xl text-4xl font-bold leading-tight text-on-hero md:text-5xl lg:text-6xl">{{ t('home.hero_title') }}</h1>
            <p class="mt-5 max-w-2xl text-lg text-on-hero/90">{{ t('home.hero_subtitle') }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <Link :href="startUrl" class="btn btn-accent px-6 text-base"><Icon name="panorama" />{{ t('home.cta_start') }}</Link>
                <Link :href="lr('/tour')" class="btn border-white/40 bg-white/10 px-6 text-base text-on-hero hover:bg-white/20"><Icon name="route" />{{ t('home.cta_tours') }}</Link>
                <Link :href="lr('/ve')" class="btn border-white/40 bg-transparent px-6 text-base text-on-hero hover:bg-white/10"><Icon name="ticket" />{{ t('home.cta_tickets') }}</Link>
            </div>
            <dl class="mt-12 grid max-w-3xl grid-cols-2 gap-4 sm:grid-cols-4" :aria-label="t('home.stats_label')">
                <div v-for="s in statItems" :key="s.key" class="rounded-xl border border-white/20 bg-white/5 p-3 backdrop-blur-sm">
                    <dt class="flex items-center gap-1.5 text-xs text-on-hero/80"><Icon :name="s.icon" :size="14" />{{ t(`home.stat_${s.key}`) }}</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-on-hero">{{ fmt(s.value) }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <!-- Cách tham quan -->
    <section class="container-page py-12" :aria-labelledby="'how-title'">
        <h2 id="how-title" class="sr-only">{{ t('home.how_title') }}</h2>
        <ol class="grid gap-4 md:grid-cols-3">
            <li v-for="(s, i) in steps" :key="s" class="card flex gap-4 p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary"><Icon :name="stepIcon[s]" /></span>
                <div>
                    <p class="text-xs font-bold text-accent-strong">{{ t('home.step_n', { n: i + 1 }) }}</p>
                    <h3 class="font-body text-base font-bold">{{ t(`home.how_${s}`) }}</h3>
                    <p class="mt-1 text-sm text-muted">{{ t(`home.how_${s}_text`) }}</p>
                </div>
            </li>
        </ol>
    </section>

    <!-- 8 phòng trưng bày -->
    <section class="container-page py-8">
        <SectionHeading :eyebrow="t('home.rooms_eyebrow')" :title="t('home.rooms_title')" :href="lr('/tham-quan-360')" :link-text="t('home.rooms_link')" />
        <EmptyState v-if="!scenes.length" icon="panorama" :title="t('scenes.empty')" />
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <SceneCard v-for="(s, i) in scenes" :key="s.slug" :scene="s" :eager="i < 2" />
        </div>
    </section>

    <!-- Tour nổi bật -->
    <section class="bg-surface-2 py-12">
        <div class="container-page">
            <SectionHeading :eyebrow="t('home.tours_eyebrow')" :title="t('home.tours_title')" :href="lr('/tour')" :link-text="t('common.view_all')" />
            <EmptyState v-if="!featuredTours.length" icon="route" :title="t('tours.empty')" />
            <div v-else class="grid gap-4 md:grid-cols-3">
                <TourCard v-for="tour in featuredTours" :key="tour.slug" :tour="tour" />
            </div>
        </div>
    </section>

    <!-- Hiện vật nổi bật -->
    <section class="container-page py-12">
        <SectionHeading :eyebrow="t('home.artifacts_eyebrow')" :title="t('home.artifacts_title')" :href="lr('/hien-vat')" :link-text="t('common.view_all')" />
        <EmptyState v-if="!highlightArtifacts.length" icon="bone" :title="t('artifacts.empty')" />
        <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <ArtifactCard v-for="a in highlightArtifacts" :key="a.slug" :artifact="a" />
        </div>
    </section>

    <!-- Gói vé -->
    <section class="bg-surface-2 py-12">
        <div class="container-page">
            <SectionHeading :eyebrow="t('home.tickets_eyebrow')" :title="t('home.tickets_title')" :href="lr('/ve')" :link-text="t('home.tickets_link')" />
            <EmptyState v-if="!packages.length" icon="ticket" :title="t('tickets.empty')" />
            <div v-else class="grid gap-6 md:grid-cols-2 lg:mx-auto lg:max-w-4xl">
                <PackageCard v-for="p in packages" :key="p.code" :pkg="p">
                    <template #actions>
                        <Link :href="lr('/ve')" class="btn w-full" :class="p.is_featured ? 'btn-accent' : 'btn-primary'">{{ t('home.buy_ticket') }}</Link>
                    </template>
                </PackageCard>
            </div>
        </div>
    </section>

    <!-- Blog mới -->
    <section class="container-page py-12">
        <SectionHeading :eyebrow="t('home.blog_eyebrow')" :title="t('home.blog_title')" :href="lr('/blog')" :link-text="t('common.view_all')" />
        <EmptyState v-if="!latestPosts.length" icon="book" :title="t('home.blog_empty')" />
        <div v-else class="grid gap-4 md:grid-cols-3">
            <PostCard v-for="p in latestPosts" :key="p.slug" :post="p" />
        </div>
    </section>

    <!-- Giới thiệu bảo tàng -->
    <section class="container-page pb-4">
        <div class="card grid items-center gap-6 p-6 md:grid-cols-[1fr_auto] md:p-8">
            <div>
                <p class="eyebrow">{{ t('home.museum_eyebrow') }}</p>
                <h2 class="mt-1 text-2xl font-bold">{{ museum?.name || 'Naturalis Biodiversity Center' }}</h2>
                <p class="mt-2 max-w-2xl text-muted">{{ museum?.summary || t('home.museum_text') }}</p>
                <p class="mt-3 text-xs text-muted">{{ museum?.attribution || t('home.museum_attribution') }}</p>
            </div>
            <Link :href="lr('/bao-tang')" class="btn btn-primary">{{ t('home.museum_link') }}<Icon name="arrowRight" :size="18" /></Link>
        </div>
    </section>
</template>
