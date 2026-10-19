<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import SceneCard from '@/Components/SceneCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import MiniMap from '@/Components/Viewer/MiniMap.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';

const props = defineProps({
    scenes: { type: Array, default: () => [] },
    floorplan: { type: Object, default: null },
});
const { t } = useT();
const floor = ref('');

const floors = computed(() => [...new Set(props.scenes.map((s) => s.floor ?? 0))].sort((a, b) => a - b));
const shown = computed(() => (floor.value === '' ? props.scenes : props.scenes.filter((s) => String(s.floor ?? 0) === String(floor.value))));
const first = computed(() => props.scenes.find((s) => s.code === 'main_hall') || props.scenes[0] || null);
const openScene = (scene) => router.visit(scene.url);
</script>

<template>
    <PageMeta :title="t('scenes.title')" fallback-key="scenes.intro" :image="first?.preview_url" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.tour360')" :title="t('scenes.title')" :description="t('scenes.intro')">
            <template #actions>
                <Link v-if="first" :href="first.url" class="btn btn-accent"><Icon name="panorama" />{{ t('scenes.start_at', { name: first.title }) }}</Link>
            </template>
        </PageHeader>

        <div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
            <section :aria-label="t('scenes.list_label')">
                <div v-if="floors.length > 1" class="mb-4 flex flex-wrap gap-2" role="group" :aria-label="t('scenes.filter_floor')">
                    <button type="button" class="tab" :aria-pressed="floor === ''" @click="floor = ''">{{ t('common.all') }}</button>
                    <button v-for="f in floors" :key="f" type="button" class="tab" :aria-pressed="String(floor) === String(f)" @click="floor = f">{{ t('scenes.floor_n', { n: f }) }}</button>
                </div>
                <EmptyState v-if="!shown.length" icon="panorama" :title="t('scenes.empty')" />
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <SceneCard v-for="(s, i) in shown" :key="s.slug" :scene="s" :heading-level="2" :eager="i < 2" />
                </div>
            </section>
            <aside class="lg:sticky lg:top-20 lg:self-start">
                <h2 class="mb-2 font-body text-base font-bold">{{ t('museum.floorplan_title') }}</h2>
                <MiniMap :floorplan="floorplan" :scenes="scenes" :collapsible="false" static-map :dark="false" @select="openScene" />
                <p class="mt-2 text-xs text-muted">{{ t('museum.floorplan_hint') }}</p>
                <div class="card mt-4 space-y-2 p-4 text-sm">
                    <h2 class="font-body text-base font-bold">{{ t('scenes.how_title') }}</h2>
                    <p class="flex items-start gap-2"><Icon name="compass" :size="18" class="mt-0.5 text-primary" />{{ t('scenes.how_drag') }}</p>
                    <p class="flex items-start gap-2"><Icon name="info" :size="18" class="mt-0.5 text-primary" />{{ t('scenes.how_hotspot') }}</p>
                    <p class="flex items-start gap-2"><Icon name="route" :size="18" class="mt-0.5 text-primary" />{{ t('scenes.how_guided') }}</p>
                    <p class="flex items-start gap-2"><Icon name="volume" :size="18" class="mt-0.5 text-primary" />{{ t('scenes.how_audio') }}</p>
                </div>
            </aside>
        </div>
    </div>
</template>
