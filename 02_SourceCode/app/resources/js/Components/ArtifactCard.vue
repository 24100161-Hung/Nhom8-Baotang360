<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import StarRating from '@/Components/StarRating.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Thẻ hiện vật (ArtifactCard): ảnh, tên phổ thông + tên khoa học, thời kỳ, nguồn gốc, phòng trưng bày. */
defineProps({
    artifact: { type: Object, required: true },
    headingLevel: { type: Number, default: 3 },
    eager: { type: Boolean, default: false },
});
const { t, te } = useT();
const { country } = useFormat();
</script>

<template>
    <article class="card card-hover flex h-full flex-col overflow-hidden">
        <Link :href="artifact.url" tabindex="-1" aria-hidden="true">
            <CoverImage :src="artifact.cover?.url" :alt="artifact.cover?.caption || artifact.title" icon="bone" ratio="aspect-square" :eager="eager">
                <span v-if="artifact.is_highlight" class="badge badge-accent absolute left-3 top-3 shadow">{{ t('artifacts.highlight') }}</span>
            </CoverImage>
        </Link>
        <div class="flex flex-1 flex-col gap-1 p-4">
            <p class="eyebrow">{{ te('taxon', artifact.taxon_group) || t('artifacts.title') }}</p>
            <component :is="`h${headingLevel}`" class="text-base font-bold leading-snug">
                <Link :href="artifact.url" class="hover:text-primary">{{ artifact.title }}</Link>
            </component>
            <p v-if="artifact.scientific_name" class="text-sm italic text-muted">{{ artifact.scientific_name }}</p>
            <p class="mt-auto pt-2 text-xs text-muted">
                <span v-if="artifact.period">{{ artifact.period.name }}</span>
                <span v-if="artifact.period && artifact.origin_country"> · </span>
                <span v-if="artifact.origin_country">{{ country(artifact.origin_country) }}</span>
            </p>
            <div class="flex items-center justify-between gap-2">
                <p v-if="artifact.scene" class="truncate text-xs font-medium text-accent-strong">{{ artifact.scene.title }}</p>
                <span v-if="artifact.rating_count" class="ml-auto inline-flex items-center gap-1 text-xs text-muted">
                    <StarRating :value="artifact.rating_avg" readonly :size="12" />({{ artifact.rating_count }})
                </span>
            </div>
        </div>
    </article>
</template>
