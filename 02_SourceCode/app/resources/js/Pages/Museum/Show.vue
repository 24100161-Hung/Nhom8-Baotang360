<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import SceneCard from '@/Components/SceneCard.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import MiniMap from '@/Components/Viewer/MiniMap.vue';
import SectionHeading from '@/Components/Public/SectionHeading.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useFormat } from '@/Composables/useFormat';

const props = defineProps({
    museum: { type: Object, default: () => ({}) },
    scenes: { type: Array, default: () => [] },
    floorplan: { type: Object, default: null },
    stats: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const { number } = useFormat();

const m = computed(() => props.museum || {});
const startUrl = computed(() => props.scenes[0]?.url || lr('/tham-quan-360'));
// Backend: license = mã enum ('cc_by_sa_3') kèm license_label; bản cũ: license = nhãn, license_code = mã
const licenseLabel = computed(() => {
    const m0 = m.value;
    if (m0.license_label) return m0.license_label;
    const code = m0.license_code || (/^[a-z0-9_]+$/.test(m0.license || '') ? m0.license : null);
    return code ? te('license', code) : m0.license || 'CC BY-SA 3.0';
});
const fmt = (v) => (v === null || v === undefined ? '—' : number(v));
const openScene = (scene) => router.visit(scene.url);
</script>

<template>
    <PageMeta :title="m.name || t('museum.title')" :description="m.summary" fallback-key="museum.intro" :image="m.logo_url" />
    <div class="container-page py-8">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.museum') }]" class="mb-4" />

        <header class="card grid gap-6 p-6 md:grid-cols-[auto_1fr_auto] md:items-center md:p-8">
            <img v-if="m.logo_url" :src="m.logo_url" :alt="t('museum.logo_alt', { name: m.name })" class="h-20 w-20 rounded-xl bg-white object-contain p-2 shadow-ds" />
            <span v-else class="flex h-20 w-20 items-center justify-center rounded-xl bg-primary-soft text-primary"><Icon name="landmark" :size="40" /></span>
            <div class="min-w-0">
                <p class="eyebrow">{{ [m.city, m.country].filter(Boolean).join(', ') || 'Leiden, Nederland' }}</p>
                <h1 class="mt-1 text-3xl font-bold md:text-4xl">{{ m.name || 'Naturalis Biodiversity Center' }}</h1>
                <p v-if="m.summary" class="mt-2 max-w-2xl text-muted">{{ m.summary }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="badge badge-success">© {{ licenseLabel }}</span>
                    <a v-if="m.website" :href="m.website" target="_blank" rel="noopener noreferrer" class="badge hover:border-primary">
                        <Icon name="external" :size="14" />{{ m.website.replace(/^https?:\/\//, '') }}
                    </a>
                </div>
            </div>
            <Link :href="startUrl" class="btn btn-accent"><Icon name="panorama" />{{ t('museum.start_360') }}</Link>
        </header>

        <section class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4" :aria-label="t('home.stats_label')">
            <StatCard :label="t('home.stat_scenes')" :value="fmt(stats?.scenes ?? scenes.length)" icon="panorama" />
            <StatCard :label="t('home.stat_artifacts')" :value="fmt(stats?.artifacts)" icon="bone" tone="accent" />
            <StatCard :label="t('home.stat_tours')" :value="fmt(stats?.tours)" icon="route" tone="info" />
            <StatCard :label="t('home.stat_visits')" :value="fmt(stats?.visits)" icon="eye" tone="success" />
        </section>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_24rem]">
            <section :aria-labelledby="'about-museum'">
                <h2 id="about-museum" class="section-title mb-4">{{ t('museum.about_title') }}</h2>
                <div v-if="m.description_html" class="prose-museum" v-html="m.description_html"></div>
                <p v-else class="text-muted">{{ t('museum.intro') }}</p>
            </section>
            <aside class="space-y-4">
                <section class="card p-4" :aria-labelledby="'license-title'">
                    <h2 id="license-title" class="font-body text-base font-bold">{{ t('museum.license_title') }}</h2>
                    <p class="mt-2 text-sm">{{ m.attribution || t('home.museum_attribution') }}</p>
                    <a href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank" rel="noopener noreferrer license" class="link mt-2 inline-block text-sm">{{ t('museum.license_link') }}</a>
                </section>
                <section :aria-label="t('minimap.title')">
                    <h2 class="mb-2 font-body text-base font-bold">{{ t('museum.floorplan_title') }}</h2>
                    <MiniMap :floorplan="floorplan" :scenes="scenes" :collapsible="false" static-map :dark="false" @select="openScene" />
                    <p class="mt-2 text-xs text-muted">{{ t('museum.floorplan_hint') }}</p>
                </section>
            </aside>
        </div>

        <section class="mt-12">
            <SectionHeading :eyebrow="t('home.rooms_eyebrow')" :title="t('museum.rooms_title')" :href="lr('/tham-quan-360')" :link-text="t('home.rooms_link')" />
            <EmptyState v-if="!scenes.length" icon="panorama" :title="t('scenes.empty')" />
            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <SceneCard v-for="s in scenes" :key="s.slug" :scene="s" />
            </div>
        </section>
    </div>
</template>
