<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/** Thẻ phòng (SceneCard): ảnh xem trước, tầng, số hotspot/hiện vật, ngôn ngữ audio. */
defineProps({
    scene: { type: Object, required: true },
    headingLevel: { type: Number, default: 3 },
    eager: { type: Boolean, default: false },
    visited: { type: Boolean, default: false },
});
const { t } = useT();
</script>

<template>
    <article class="card card-hover group flex h-full flex-col overflow-hidden">
        <Link :href="scene.url" tabindex="-1" aria-hidden="true" class="relative block">
            <CoverImage :src="scene.preview_url" :alt="scene.title" icon="panorama" ratio="aspect-[16/9]" :eager="eager">
                <span class="absolute left-3 top-3 badge border-transparent bg-hero-scrim/80 text-on-hero">
                    <Icon name="panorama" :size="14" />360°
                </span>
                <span v-if="visited" class="badge badge-success absolute right-3 top-3">{{ t('scenes.visited') }}</span>
            </CoverImage>
        </Link>
        <div class="flex flex-1 flex-col gap-1.5 p-4">
            <p class="eyebrow">{{ t('scenes.floor_n', { n: scene.floor ?? 0 }) }}<template v-if="scene.code"> · {{ scene.code }}</template></p>
            <component :is="`h${headingLevel}`" class="text-lg font-bold leading-snug">
                <Link :href="scene.url" class="hover:text-primary">{{ scene.title }}</Link>
            </component>
            <p class="line-clamp-2 text-sm text-muted">{{ scene.summary }}</p>
            <p class="mt-auto flex flex-wrap gap-x-3 gap-y-1 pt-2 text-xs text-muted">
                <span class="inline-flex items-center gap-1"><Icon name="pin" :size="14" />{{ t('scenes.hotspot_count', { n: scene.hotspot_count ?? 0 }) }}</span>
                <span class="inline-flex items-center gap-1"><Icon name="bone" :size="14" />{{ t('scenes.artifact_count', { n: scene.artifact_count ?? 0 }) }}</span>
                <span v-if="scene.audio_locales?.length" class="inline-flex items-center gap-1"><Icon name="volume" :size="14" />{{ scene.audio_locales.map((l) => l.toUpperCase()).join(' · ') }}</span>
            </p>
            <span v-if="scene.is_simulated" class="badge badge-warning mt-1 self-start">{{ t('scenes.simulated') }}</span>
        </div>
    </article>
</template>
