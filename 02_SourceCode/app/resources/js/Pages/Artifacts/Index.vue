<script setup>
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import ArtifactCard from '@/Components/ArtifactCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

const props = defineProps({
    artifacts: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    periods: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
    origins: { type: Array, default: () => [] },
    taxa: { type: Array, default: () => [] },
    scenes: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { number } = useFormat();

// Mục lọc: [{code,name,count}] hoặc chuỗi; hiển thị kèm số lượng nếu có
const opts = (list, group) =>
    (list || []).map((x) => {
        const item = typeof x === 'object' ? x : { code: x };
        const name = item.name || (group ? te(group, item.code) : item.code);
        return { value: item.code ?? item.slug, label: item.count != null ? `${name} (${number(item.count)})` : name };
    });
const SORTS = ['featured', 'newest', 'name', 'popular', 'rating'];

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('artifacts.search_placeholder') },
    { name: 'period', type: 'select', label: t('artifacts.period'), options: opts(props.periods) },
    { name: 'material', type: 'select', label: t('artifacts.material'), options: opts(props.materials) },
    { name: 'origin', type: 'select', label: t('artifacts.origin'), options: opts(props.origins) },
    { name: 'taxon', type: 'select', label: t('artifacts.taxon'), options: opts(props.taxa, 'taxon') },
    { name: 'scene', type: 'select', label: t('artifacts.room'), options: props.scenes.map((s) => ({ value: s.slug, label: s.title })) },
    { name: 'sort', type: 'select', label: t('common.sort'), placeholder: t('artifacts.sort_default'), options: SORTS.map((s) => ({ value: s, label: t(`artifacts.sort_${s}`) })) },
]);
const list = computed(() => props.artifacts?.data || []);
const total = computed(() => props.artifacts?.total ?? list.value.length);
</script>

<template>
    <PageMeta :title="t('artifacts.title')" fallback-key="artifacts.description" />
    <div class="container-page py-8">
        <PageHeader :title="t('artifacts.title')" :eyebrow="t('artifacts.eyebrow')" :description="t('artifacts.description')" />
        <FilterBar :filters="filters" :fields="fields" />
        <p class="mt-5 text-sm text-muted" aria-live="polite">{{ t('artifacts.result_count', { n: number(total) }) }}</p>
        <EmptyState v-if="!list.length" class="mt-4" icon="bone" :title="t('artifacts.empty')" :description="t('artifacts.empty_hint')" />
        <div v-else class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            <ArtifactCard v-for="(a, i) in list" :key="a.id || a.slug" :artifact="a" :heading-level="2" :eager="i < 2" />
        </div>
        <Pagination :paginator="artifacts" />
    </div>
</template>
