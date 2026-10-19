<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import CoverImage from '@/Components/CoverImage.vue';
import FavoriteButton from '@/Components/FavoriteButton.vue';
import StarRating from '@/Components/StarRating.vue';
import TourCard from '@/Components/TourCard.vue';
import ReviewSection from '@/Components/ReviewSection.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import SectionHeading from '@/Components/Public/SectionHeading.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    tour: { type: Object, required: true },
});
const { t, te } = useT();
const { duration, dateTime } = useFormat();
const lr = useLocaleRoute();
const startForm = useForm({});

const tr = computed(() => props.tour || {});
const steps = computed(() => tr.value.steps || []);
const progress = computed(() => tr.value.progress || null);
const highlights = computed(() => {
    const h = tr.value.highlights;
    if (Array.isArray(h)) return h;
    return h ? String(h).split('\n').map((x) => x.trim()).filter(Boolean) : [];
});
const resumeUrl = computed(() => {
    const step = progress.value?.current_step || 1;
    const scene = steps.value[step - 1]?.scene || steps.value[0]?.scene;
    return scene ? lr(`/tham-quan-360/${scene.slug}?tour=${tr.value.slug}&step=${step}&mode=guided`) : null;
});
const firstSceneFree = computed(() => (steps.value[0]?.scene ? lr(`/tham-quan-360/${steps.value[0].scene.slug}?tour=${tr.value.slug}&mode=free`) : null));
const start = () => startForm.post(lr(`/tour/${tr.value.slug}/bat-dau`));
</script>

<template>
    <PageMeta :title="tr.name" :description="tr.description" :image="tr.cover_url" />
    <div class="container-page py-8">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('tours.title'), href: lr('/tour') }, { label: tr.name }]" class="mb-4" />

        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
            <CoverImage :src="tr.cover_url" :alt="tr.name" icon="route" ratio="aspect-[16/10]" eager class="rounded-xl" />
            <div class="min-w-0">
                <p v-if="tr.theme" class="eyebrow">{{ tr.theme.name }}</p>
                <h1 class="mt-1 text-3xl font-bold md:text-4xl">{{ tr.name }}</h1>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="badge"><Icon name="clock" :size="14" />{{ duration(tr.duration_minutes) }}</span>
                    <span class="badge">{{ te('difficulty', tr.difficulty) }}</span>
                    <span class="badge">{{ te('tour_mode', tr.mode) }}</span>
                    <span class="badge">{{ t('tours.steps_n', { n: tr.steps_count ?? steps.length }) }}</span>
                    <span class="badge">{{ t('tours.scenes_n', { n: tr.scenes_count ?? 0 }) }}</span>
                    <span v-if="tr.rating_count" class="badge"><StarRating :value="tr.rating_avg" readonly :size="12" />{{ Number(tr.rating_avg).toFixed(1) }}</span>
                </div>
                <p class="mt-4 leading-relaxed">{{ tr.description }}</p>
                <ul v-if="highlights.length" class="mt-4 space-y-1.5 text-sm">
                    <li v-for="(h, i) in highlights" :key="i" class="flex items-start gap-2"><Icon name="sparkles" :size="16" class="mt-0.5 text-accent-strong" />{{ h }}</li>
                </ul>

                <section v-if="progress" class="card mt-5 p-4" :aria-label="t('tours.my_progress')">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <h2 class="font-body font-bold">{{ t('tours.my_progress') }}</h2>
                        <span class="font-semibold tabular-nums">{{ progress.percent ?? 0 }}%</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-2" role="progressbar" :aria-valuenow="progress.percent ?? 0" aria-valuemin="0" aria-valuemax="100" :aria-label="t('tours.my_progress')">
                        <div class="h-full rounded-full bg-primary" :style="{ width: `${progress.percent ?? 0}%` }"></div>
                    </div>
                    <p class="mt-2 text-xs text-muted">
                        <template v-if="progress.completed_at">{{ t('tours.completed_at', { time: dateTime(progress.completed_at) }) }}</template>
                        <template v-else>{{ t('tours.at_step', { n: progress.current_step || 1, total: steps.length }) }}</template>
                    </p>
                </section>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-accent" :disabled="startForm.processing || !steps.length" @click="start">
                        <Icon name="play" :size="18" />{{ progress ? t('tours.restart') : t('tours.start_guided') }}
                    </button>
                    <Link v-if="progress && !progress.completed_at && resumeUrl" :href="resumeUrl" class="btn btn-primary"><Icon name="route" :size="18" />{{ t('tours.resume') }}</Link>
                    <Link v-if="firstSceneFree" :href="firstSceneFree" class="btn"><Icon name="compass" :size="18" />{{ t('tours.explore_free') }}</Link>
                    <FavoriteButton v-if="tr.id" type="tour" :id="tr.id" :active="!!tr.is_favorite" />
                </div>
            </div>
        </div>

        <section class="mt-12" aria-labelledby="steps-title">
            <h2 id="steps-title" class="section-title mb-4">{{ t('tours.steps_title') }}</h2>
            <EmptyState v-if="!steps.length" icon="route" :title="t('tours.no_steps')" compact />
            <ol v-else class="relative space-y-4 border-l-2 border-border pl-6">
                <li v-for="s in steps" :key="s.order" class="relative">
                    <span class="absolute -left-[2.2rem] flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold" :class="progress && s.order <= (progress.completed_steps?.length || 0) ? 'bg-success text-on-primary' : 'bg-primary text-on-primary'">{{ s.order }}</span>
                    <div class="card flex flex-col gap-4 p-4 sm:flex-row">
                        <img v-if="s.scene?.preview_url" :src="s.scene.preview_url" :alt="s.scene.title" class="aspect-video w-full rounded-lg object-cover sm:w-44" loading="lazy" />
                        <div class="min-w-0 flex-1">
                            <h3 class="font-body text-base font-bold">{{ s.title || s.scene?.title }}</h3>
                            <p class="text-xs text-muted">
                                <Icon name="door" :size="12" class="inline" /> {{ s.scene?.title }}
                                <template v-if="s.dwell_seconds"> · {{ t('tours.dwell', { s: s.dwell_seconds }) }}</template>
                                <template v-if="s.auto_advance"> · {{ t('tours.auto_advance') }}</template>
                            </p>
                            <p v-if="s.narration" class="mt-2 line-clamp-3 text-sm text-muted">{{ s.narration }}</p>
                        </div>
                    </div>
                </li>
            </ol>
        </section>

        <section v-if="tr.similar?.length" class="mt-12">
            <SectionHeading :eyebrow="t('tours.similar_eyebrow')" :title="t('tours.similar')" :href="lr('/tour')" :link-text="t('common.view_all')" />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <TourCard v-for="s in tr.similar" :key="s.slug" :tour="s" />
            </div>
        </section>

        <ReviewSection
            v-if="tr.id"
            class="mt-12"
            subject-type="tour"
            :subject-id="tr.id"
            :reviews="tr.reviews || []"
            :my-review="tr.my_review"
            :rating-avg="tr.rating_avg"
            :rating-count="tr.rating_count || 0"
        />
    </div>
</template>
