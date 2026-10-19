<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import DepthDots from '@/Components/Admin/DepthDots.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, rowsOf, entityOptions } from '@/Utils/options';
import { TAXA, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    artifacts: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    scenes: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { date, number } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.artifacts));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('admin.artifacts.search_placeholder') },
    { name: 'status', type: 'select', label: t('common.status'), options: codeOptions(STATUS_CODES, te, 'status') },
    { name: 'taxon', type: 'select', label: t('artifacts.taxon'), options: codeOptions(props.enums.taxa || TAXA, te, 'taxon') },
    { name: 'scene', type: 'select', label: t('admin.artifacts.scene'), options: entityOptions(props.scenes) },
]);
const columns = computed(() => [
    { key: 'title_vi', label: t('admin.common.title') },
    { key: 'catalog_number', label: t('artifacts.catalog_number') },
    { key: 'taxon_group', label: t('artifacts.taxon') },
    { key: 'scene', label: t('admin.artifacts.scene') },
    { key: 'status', label: t('common.status') },
    { key: 'depths', label: t('admin.common.depths') },
    { key: 'view_count', label: t('admin.common.views'), class: 'text-right' },
    { key: 'updated_at', label: t('common.updated_at') },
]);
const editUrl = (row) => lr(`/quan-tri/hien-vat/${row.id}/sua`);
</script>

<template>
    <Head :title="t('admin.artifacts.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.artifacts.title')" :description="t('admin.artifacts.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/hien-vat/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.artifacts.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.artifacts.title')" :empty-text="t('admin.artifacts.empty')">
        <template #cell-title_vi="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.cover?.url || row.cover_url" :src="row.cover?.url || row.cover_url" alt="" class="h-10 w-10 shrink-0 rounded object-cover" loading="lazy" />
                <div class="min-w-0">
                    <Link :href="editUrl(row)" class="font-semibold hover:text-primary">{{ row.title_vi || row.title || row.slug }}</Link>
                    <p v-if="row.scientific_name" class="text-xs italic text-muted">{{ row.scientific_name }}</p>
                    <span v-if="row.is_highlight" class="badge badge-accent mt-1">{{ t('artifacts.highlight') }}</span>
                </div>
            </div>
        </template>
        <template #cell-catalog_number="{ value }"><code class="text-xs">{{ value || '—' }}</code></template>
        <template #cell-taxon_group="{ value }">{{ value ? te('taxon', value) : '—' }}</template>
        <template #cell-scene="{ value }">{{ (value && (value.title || value)) || '—' }}</template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #cell-depths="{ row }">
            <div class="flex flex-col gap-1">
                <DepthDots :depths="row.depths_vi" locale="vi" />
                <DepthDots :depths="row.depths_en" locale="en" />
            </div>
        </template>
        <template #cell-view_count="{ value }">{{ number(value ?? 0) }}</template>
        <template #cell-updated_at="{ value }"><span class="whitespace-nowrap">{{ date(value) }}</span></template>
        <template #actions="{ row }">
            <Link :href="editUrl(row)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.title_vi || row.slug}`"><Icon name="edit" :size="18" /></Link>
            <DeleteAction :url="lr(`/quan-tri/hien-vat/${row.id}`)" :name="row.title_vi || row.slug" />
        </template>
    </DataTable>
    <Pagination :paginator="artifacts" />
</template>
