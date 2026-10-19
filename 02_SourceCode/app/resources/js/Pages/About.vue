<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import StatCard from '@/Components/StatCard.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useFormat } from '@/Composables/useFormat';

const props = defineProps({
    museum: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const { number } = useFormat();
const app = computed(() => page.props.app || {});
const m = computed(() => props.museum || app.value.museum || {});

const personas = [
    { key: 'visitor', icon: 'compass', depth: 1 },
    { key: 'student', icon: 'book', depth: 1 },
    { key: 'researcher', icon: 'file', depth: 2 },
];
const features = ['pano', 'minimap', 'guided', 'audio', 'tickets', 'analytics'];
const featureIcon = { pano: 'panorama', minimap: 'map', guided: 'route', audio: 'volume', tickets: 'ticket', analytics: 'activity' };
const fmt = (v) => (v != null ? number(v) : '—');
</script>

<template>
    <PageMeta :title="t('about.title')" fallback-key="about.intro" />
    <div class="container-page py-10">
        <header class="mx-auto max-w-3xl text-center">
            <p class="eyebrow">{{ t('about.eyebrow') }}</p>
            <h1 class="mt-2 text-4xl font-bold md:text-5xl">{{ t('about.title') }}</h1>
            <p class="mt-4 text-lg text-muted">{{ t('about.intro') }}</p>
        </header>

        <section class="mt-10 grid grid-cols-2 gap-3 md:grid-cols-4" :aria-label="t('home.stats_label')">
            <StatCard :label="t('home.stat_scenes')" :value="fmt(stats?.scenes)" icon="panorama" />
            <StatCard :label="t('home.stat_artifacts')" :value="fmt(stats?.artifacts)" icon="bone" tone="accent" />
            <StatCard :label="t('home.stat_tours')" :value="fmt(stats?.tours)" icon="route" tone="info" />
            <StatCard :label="t('home.stat_visits')" :value="fmt(stats?.visits)" icon="eye" tone="success" />
        </section>

        <section class="mt-14">
            <h2 class="section-title mb-2">{{ t('about.personas_title') }}</h2>
            <p class="mb-6 max-w-2xl text-muted">{{ t('about.personas_intro') }}</p>
            <div class="grid gap-4 md:grid-cols-3">
                <article v-for="p in personas" :key="p.key" class="card p-5">
                    <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><Icon :name="p.icon" /></span>
                    <h3 class="text-xl font-bold">{{ t(`about.persona_${p.key}_name`) }}</h3>
                    <p class="text-sm font-semibold text-accent-strong">{{ t('about.persona_depth', { depth: t(`depth.${p.depth}`) }) }}</p>
                    <p class="mt-2 text-sm text-muted">{{ t(`about.persona_${p.key}_text`) }}</p>
                </article>
            </div>
        </section>

        <section class="mt-14">
            <h2 class="section-title mb-6">{{ t('about.features_title') }}</h2>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="f in features" :key="f" class="flex gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent-strong"><Icon :name="featureIcon[f]" :size="18" /></span>
                    <div>
                        <h3 class="font-body text-base font-bold">{{ t(`about.feature_${f}`) }}</h3>
                        <p class="text-sm text-muted">{{ t(`about.feature_${f}_text`) }}</p>
                    </div>
                </li>
            </ul>
        </section>

        <section class="card mt-14 grid gap-6 p-6 md:grid-cols-2 md:p-8">
            <div>
                <h2 class="section-title">{{ t('about.academic_title') }}</h2>
                <p class="mt-2 text-muted">{{ t('about.academic_text') }}</p>
                <p class="mt-4 inline-block rounded-lg bg-accent-soft px-3 py-2 text-sm font-semibold text-accent-strong">{{ t('footer.academic_product') }}</p>
            </div>
            <dl class="grid gap-3 text-sm">
                <div v-if="app.course"><dt class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t('footer.course') }}</dt><dd class="font-medium">{{ app.course }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t('footer.course_code') }}</dt><dd class="font-medium">{{ app.courseCode || 'CSE703073' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t('footer.team') }}</dt><dd class="font-medium">{{ t('footer.team_value') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wider text-muted">{{ t('about.data_sources') }}</dt><dd>{{ m.attribution || t('about.data_sources_text') }}</dd></div>
            </dl>
        </section>

        <div class="mt-10 flex flex-wrap justify-center gap-2">
            <Link :href="lr('/tham-quan-360')" class="btn btn-accent"><Icon name="panorama" :size="18" />{{ t('home.cta_start') }}</Link>
            <Link :href="lr('/bao-tang')" class="btn btn-primary">{{ t('home.museum_link') }}</Link>
            <a href="/api/v1/openapi.json" class="btn">{{ t('footer.open_api') }}</a>
        </div>
    </div>
</template>
