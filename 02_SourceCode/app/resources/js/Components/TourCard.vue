<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import Icon from '@/Components/Icon.vue';
import StarRating from '@/Components/StarRating.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Thẻ tour: chủ đề, thời lượng, độ khó, số bước, đánh giá. */
defineProps({
    tour: { type: Object, required: true },
    headingLevel: { type: Number, default: 3 },
    eager: { type: Boolean, default: false },
});
const { t, te } = useT();
const { duration } = useFormat();
const DIFF_TONE = { de: 'badge-success', vua: 'badge-warning', kho: 'badge-danger' };
</script>

<template>
    <article class="card card-hover flex h-full flex-col overflow-hidden">
        <Link :href="tour.url" tabindex="-1" aria-hidden="true">
            <CoverImage :src="tour.cover_url" :alt="tour.name" icon="route" ratio="aspect-[16/10]" :eager="eager">
                <span v-if="tour.is_featured" class="badge badge-accent absolute left-3 top-3 shadow">{{ t('tours.featured') }}</span>
            </CoverImage>
        </Link>
        <div class="flex flex-1 flex-col gap-1.5 p-4">
            <p v-if="tour.theme" class="eyebrow">{{ tour.theme.name }}</p>
            <component :is="`h${headingLevel}`" class="text-lg font-bold leading-snug">
                <Link :href="tour.url" class="hover:text-primary">{{ tour.name }}</Link>
            </component>
            <p class="line-clamp-2 text-sm text-muted">{{ tour.description }}</p>
            <div class="mt-auto flex flex-wrap items-center gap-2 pt-2 text-xs">
                <span class="badge"><Icon name="clock" :size="14" />{{ duration(tour.duration_minutes) }}</span>
                <span class="badge" :class="DIFF_TONE[tour.difficulty]">{{ te('difficulty', tour.difficulty) }}</span>
                <span class="badge">{{ t('tours.steps_n', { n: tour.steps_count ?? 0 }) }}</span>
                <span v-if="tour.rating_count" class="ml-auto inline-flex items-center gap-1 text-muted">
                    <StarRating :value="tour.rating_avg" readonly :size="14" />({{ tour.rating_count }})
                </span>
            </div>
        </div>
    </article>
</template>
