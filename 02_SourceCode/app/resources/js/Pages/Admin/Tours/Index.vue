<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, rowsOf } from '@/Utils/options';
import { DIFFICULTIES, TOUR_MODES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    tours: { type: [Object, Array], default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { duration, number } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.tours));
const isPaginated = computed(() => !Array.isArray(props.tours) && !!props.tours?.links);
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: codeOptions(STATUS_CODES, te, 'status') },
    { name: 'difficulty', type: 'select', label: t('admin.tours.difficulty'), options: codeOptions(DIFFICULTIES, te, 'difficulty') },
    { name: 'mode', type: 'select', label: t('admin.tours.mode'), options: codeOptions(TOUR_MODES, te, 'tour_mode') },
]);
const columns = computed(() => [
    { key: 'name', label: t('admin.common.name') },
    { key: 'duration_minutes', label: t('admin.tours.duration') },
    { key: 'difficulty', label: t('admin.tours.difficulty') },
    { key: 'steps_count', label: t('admin.tours.steps'), class: 'text-right' },
    { key: 'rating_avg', label: t('reviews.title'), class: 'text-right' },
    { key: 'status', label: t('common.status') },
]);
const editUrl = (row) => lr(`/quan-tri/tour/${row.id}/sua`);
</script>

<template>
    <Head :title="t('admin.tours.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.tours.title')" :description="t('admin.tours.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/tour/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.tours.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.tours.title')" :empty-text="t('admin.tours.empty')">
        <template #cell-name="{ row }">
            <Link :href="editUrl(row)" class="font-semibold hover:text-primary">{{ row.name || row.name_vi || row.slug }}</Link>
            <p class="text-xs text-muted">{{ row.theme?.name || '' }} <span v-if="row.mode">· {{ te('tour_mode', row.mode) }}</span></p>
            <span v-if="row.is_featured" class="badge badge-accent mt-1">{{ t('tours.featured') }}</span>
        </template>
        <template #cell-duration_minutes="{ value }"><span class="whitespace-nowrap">{{ duration(value) || '—' }}</span></template>
        <template #cell-difficulty="{ value }">{{ value ? te('difficulty', value) : '—' }}</template>
        <template #cell-steps_count="{ value }">{{ number(value ?? 0) }}</template>
        <template #cell-rating_avg="{ row }">{{ row.rating_count ? `${Number(row.rating_avg).toFixed(1)} (${row.rating_count})` : '—' }}</template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #actions="{ row }">
            <Link :href="editUrl(row)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.name}`"><Icon name="edit" :size="18" /></Link>
            <DeleteAction :url="lr(`/quan-tri/tour/${row.id}`)" :name="row.name || row.slug" />
        </template>
    </DataTable>
    <Pagination v-if="isPaginated" :paginator="tours" />
</template>
