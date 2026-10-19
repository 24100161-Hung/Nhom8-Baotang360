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
import { STATUS_CODES, rowsOf, entityOptions } from '@/Utils/options';
import { codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    posts: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date, number } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.posts));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: codeOptions(STATUS_CODES, te, 'status') },
    { name: 'category', type: 'select', label: t('admin.posts.category'), options: entityOptions(props.categories) },
]);
const columns = computed(() => [
    { key: 'title', label: t('admin.common.title') },
    { key: 'category', label: t('admin.posts.category') },
    { key: 'author', label: t('admin.posts.author') },
    { key: 'published_at', label: t('admin.posts.published_at') },
    { key: 'view_count', label: t('admin.common.views'), class: 'text-right' },
    { key: 'status', label: t('common.status') },
]);
const editUrl = (row) => lr(`/quan-tri/blog/${row.id}/sua`);
</script>

<template>
    <Head :title="t('admin.posts.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.posts.title')" :description="t('admin.posts.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/blog/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.posts.create') }}</Link>
        </template>
    </PageHeader>
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.posts.title')" :empty-text="t('admin.posts.empty')">
        <template #cell-title="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.cover_url" :src="row.cover_url" alt="" class="h-10 w-16 shrink-0 rounded object-cover" loading="lazy" />
                <div class="min-w-0">
                    <Link :href="editUrl(row)" class="font-semibold hover:text-primary">{{ row.title || row.title_vi || row.slug }}</Link>
                    <span v-if="row.is_featured" class="badge badge-accent ml-1">{{ t('tours.featured') }}</span>
                </div>
            </div>
        </template>
        <template #cell-category="{ value }">{{ value?.name || (value?.code ? te('post_category', value.code) : '—') }}</template>
        <template #cell-author="{ value }">{{ value?.name || '—' }}</template>
        <template #cell-published_at="{ value }"><span class="whitespace-nowrap">{{ value ? date(value) : '—' }}</span></template>
        <template #cell-view_count="{ value }">{{ number(value ?? 0) }}</template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #actions="{ row }">
            <Link :href="editUrl(row)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.title}`"><Icon name="edit" :size="18" /></Link>
            <DeleteAction :url="lr(`/quan-tri/blog/${row.id}`)" :name="row.title || row.slug" />
        </template>
    </DataTable>
    <Pagination :paginator="posts" />
</template>
