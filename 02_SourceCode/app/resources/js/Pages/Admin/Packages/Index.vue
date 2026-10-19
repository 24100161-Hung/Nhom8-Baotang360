<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';

const props = defineProps({
    packages: { type: [Object, Array], default: () => [] },
});
const { t, te } = useT();
const { money, number } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.packages));
const isPaginated = computed(() => !Array.isArray(props.packages) && !!props.packages?.links);
const columns = computed(() => [
    { key: 'name', label: t('admin.common.name') },
    { key: 'package_type', label: t('common.type') },
    { key: 'price_vnd', label: t('admin.packages.price'), class: 'text-right' },
    { key: 'validity_days', label: t('admin.packages.validity_days'), class: 'text-right' },
    { key: 'max_per_order', label: t('admin.packages.max_per_order'), class: 'text-right' },
    { key: 'status', label: t('common.status') },
]);
const editUrl = (row) => lr(`/quan-tri/goi-ve/${row.id}/sua`);
</script>

<template>
    <Head :title="t('admin.packages.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.packages.title')" :description="t('admin.packages.description')">
        <template #actions>
            <Link :href="lr('/quan-tri/goi-ve/moi')" class="btn btn-primary"><Icon name="plus" :size="18" />{{ t('admin.packages.create') }}</Link>
        </template>
    </PageHeader>
    <DataTable :columns="columns" :rows="rows" :caption="t('admin.packages.title')" :empty-text="t('admin.packages.empty')">
        <template #cell-name="{ row }">
            <Link :href="editUrl(row)" class="font-semibold hover:text-primary">{{ row.name || row.name_vi || row.code }}</Link>
            <p class="text-xs text-muted"><code>{{ row.code }}</code></p>
            <span v-if="row.is_featured" class="badge badge-accent mt-1">{{ t('tickets.recommended') }}</span>
        </template>
        <template #cell-package_type="{ value }">{{ te('package_type', value) }}</template>
        <template #cell-price_vnd="{ value }"><span class="whitespace-nowrap font-semibold">{{ money(value) }}</span></template>
        <template #cell-validity_days="{ value }">{{ number(value) }}</template>
        <template #cell-max_per_order="{ value }">{{ number(value) }}</template>
        <template #cell-status="{ value }"><StatusBadge v-if="value" :status="value" /></template>
        <template #actions="{ row }">
            <Link :href="editUrl(row)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.edit')} ${row.name}`"><Icon name="edit" :size="18" /></Link>
            <DeleteAction :url="lr(`/quan-tri/goi-ve/${row.id}`)" :name="row.name || row.code" />
        </template>
    </DataTable>
    <Pagination v-if="isPaginated" :paginator="packages" />
</template>
