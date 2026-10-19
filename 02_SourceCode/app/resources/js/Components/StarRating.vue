<script setup>
import { computed, useId } from 'vue';
import { useT } from '@/Composables/useT';

/**
 * Sao đánh giá 1–5. readonly: hiển thị điểm trung bình (hỗ trợ nửa sao).
 * Không readonly: nhóm radio (phím mũi tên đổi số sao) dùng với v-model.
 */
const props = defineProps({
    value: { type: [Number, String], default: 0 },
    readonly: { type: Boolean, default: false },
    size: { type: Number, default: 20 },
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
const model = defineModel({ type: Number, default: 0 });
const { t } = useT();
const uid = useId();
const STARS = [1, 2, 3, 4, 5];
const STAR = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';

const shown = computed(() => Number(props.readonly ? props.value : model.value) || 0);
const fill = (n) => Math.max(0, Math.min(1, shown.value - (n - 1)));
</script>

<template>
    <span v-if="readonly" class="inline-flex items-center gap-0.5" role="img" :aria-label="t('reviews.stars_aria', { n: Number(shown).toFixed(1) })">
        <svg v-for="n in STARS" :key="n" :width="size" :height="size" viewBox="0 0 24 24" aria-hidden="true">
            <defs>
                <linearGradient :id="`${uid}-g${n}`">
                    <stop :offset="`${fill(n) * 100}%`" stop-color="var(--ds-accent)" />
                    <stop :offset="`${fill(n) * 100}%`" stop-color="transparent" />
                </linearGradient>
            </defs>
            <path :d="STAR" :fill="`url(#${uid}-g${n})`" stroke="var(--ds-accent)" stroke-width="1.5" stroke-linejoin="round" />
        </svg>
    </span>
    <fieldset v-else class="inline-flex flex-col">
        <legend class="label">{{ label || t('reviews.your_rating') }}</legend>
        <div class="flex items-center gap-1">
            <label v-for="n in STARS" :key="n" class="cursor-pointer" :title="t('reviews.n_stars', { n })">
                <input v-model="model" type="radio" :name="`${uid}-rating`" :value="n" :required="required" class="peer sr-only" />
                <svg :width="size + 8" :height="size + 8" viewBox="0 0 24 24" aria-hidden="true" class="rounded peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-[var(--focus-ring)]">
                    <path :d="STAR" :fill="n <= model ? 'var(--ds-accent)' : 'transparent'" stroke="var(--ds-accent)" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
                <span class="sr-only">{{ t('reviews.n_stars', { n }) }}</span>
            </label>
        </div>
    </fieldset>
</template>
