<script setup>
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import TourCard from '@/Components/TourCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { rowsOf } from '@/Utils/options';

const props = defineProps({
    tours: { type: [Object, Array], default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    themes: { type: Array, default: () => [] },
});
const { t, te } = useT();

const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('tours.search_placeholder') },
    { name: 'theme', type: 'select', label: t('tours.theme'), options: props.themes.map((x) => ({ value: x.slug ?? x.code, label: x.name })) },
    { name: 'difficulty', type: 'select', label: t('tours.difficulty'), options: ['de', 'vua', 'kho'].map((c) => ({ value: c, label: te('difficulty', c) })) },
    { name: 'mode', type: 'select', label: t('tours.mode'), options: ['free', 'guided', 'both'].map((c) => ({ value: c, label: te('tour_mode', c) })) },
]);
const list = computed(() => rowsOf(props.tours));
</script>

<template>
    <PageMeta :title="t('tours.title')" fallback-key="tours.intro" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('tours.eyebrow')" :title="t('tours.title')" :description="t('tours.intro')" />
        <FilterBar :filters="filters" :fields="fields" />
        <EmptyState v-if="!list.length" class="mt-6" icon="route" :title="t('tours.empty')" :description="t('tours.empty_hint')" />
        <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <TourCard v-for="(tour, i) in list" :key="tour.slug" :tour="tour" :heading-level="2" :eager="i < 2" />
        </div>
        <Pagination v-if="!Array.isArray(tours)" :paginator="tours" />
    </div>
</template>
