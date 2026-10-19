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
import { codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    scenes: { type: [Object, Array], default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { number } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.scenes));
const isPaginated = computed(() => !Array.isArray(props.scenes) && !!props.scenes?.links);
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: codeOptions(STATUS_CODES, te, 'status') },
]);
const columns = computed(() => [
    { key: 'title', label: t('admin.common.title') },
    { key: 'floor', label: t('admin.scenes.floor'), class: 'text-right' },
    { key: 'hotspot_count', label: t('admin.scenes.hotspots'), class: 'text-right' },
    { key: 'audio_locales', label: t('admin.scenes.audio') },
    { key: 'tiles_state', label: t('admin.scenes.tiles') },
    { key: 'status', label: t('common.status') },
    { key: 'view_count', label: t('admin.common.views'), class: 'text-right' },
]);
const TILE_TONE = { done: 'badge-success', running: 'badge-info', pending: 'badge-warning', error: 'badge-danger' };
const editUrl = (row) => lr(`/quan-tri/khong-gian-360/${row.id}/sua`);
</script>

<template>
    <Head :title="t('admin.scenes.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.scenes.title')" :description="t('admin.scenes.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/khong-gian-360/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.scenes.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.scenes.title')" :empty-text="t('admin.scenes.empty')">
        <template #cell-title="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.preview_url" :src="row.preview_url" alt="" class="h-10 w-16 shrink-0 rounded object-cover" loading="lazy" />
                <div class="min-w-0">
                    <Link :href="editUrl(row)" class="font-semibold hover:text-primary">{{ row.title || row.title_vi || row.slug }}</Link>
                    <p class="text-xs text-muted"><code>{{ row.code || row.slug }}</code></p>
                    <span v-if="row.is_simulated" class="badge badge-warning mt-1">{{ t('scenes.simulated') }}</span>
                </div>
            </div>
        </template>
        <template #cell-floor="{ value }">{{ value ?? '—' }}</template>
        <template #cell-hotspot_count="{ value }">{{ number(value ?? 0) }}</template>
        <template #cell-audio_locales="{ value }">
            <span v-if="value?.length" class="flex flex-wrap gap-1"><span v-for="l in value" :key="l" class="badge">{{ l.toUpperCase() }}</span></span>
            <span v-else class="text-xs text-muted">—</span>
        </template>
        <template #cell-tiles_state="{ value }"><span class="badge" :class="TILE_TONE[value]">{{ te('tiles_state', value || 'none') }}</span></template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #cell-view_count="{ value }">{{ number(value ?? 0) }}</template>
        <template #actions="{ row }">
            <a v-if="row.url" :href="row.url" target="_blank" rel="noopener" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('admin.common.view_public')} ${row.title}`"><Icon name="eye" :size="18" /></a>
            <Link :href="editUrl(row)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.title}`"><Icon name="edit" :size="18" /></Link>
            <DeleteAction :url="lr(`/quan-tri/khong-gian-360/${row.id}`)" :name="row.title || row.slug" />
        </template>
    </DataTable>
    <Pagination v-if="isPaginated" :paginator="scenes" />
</template>
