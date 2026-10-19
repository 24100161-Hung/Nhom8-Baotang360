<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';
import { ORDER_STATUSES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    orders: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { money, dateTime } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.orders));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword'), placeholder: t('admin.orders.search_placeholder') },
    { name: 'status', type: 'select', label: t('common.status'), options: codeOptions(ORDER_STATUSES, te, 'order_status') },
    { name: 'from', type: 'date', label: t('common.from') },
    { name: 'to', type: 'date', label: t('common.to') },
]);
const columns = computed(() => [
    { key: 'code', label: t('admin.orders.code') },
    { key: 'contact_name', label: t('admin.orders.customer') },
    { key: 'items', label: t('admin.orders.items') },
    { key: 'total_vnd', label: t('common.total'), class: 'text-right' },
    { key: 'payment_method', label: t('admin.orders.method') },
    { key: 'status', label: t('common.status') },
    { key: 'created_at', label: t('common.created_at') },
]);
const itemsText = (items) => (items || []).map((i) => `${i.package_name} × ${i.quantity}`).join(', ');
</script>

<template>
    <Head :title="t('admin.orders.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.orders.title')" :description="t('admin.orders.description')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <DataTable :columns="columns" :rows="rows" row-key="code" :caption="t('admin.orders.title')" :empty-text="t('admin.orders.empty')">
        <template #cell-code="{ value }"><Link :href="lr(`/quan-tri/don-hang/${value}`)" class="font-mono text-xs font-semibold hover:text-primary">{{ value }}</Link></template>
        <template #cell-contact_name="{ row }">
            <span class="block font-medium">{{ row.contact_name || row.user?.name || '—' }}</span>
            <span class="block break-all text-xs text-muted">{{ row.contact_email || row.user?.email }}</span>
        </template>
        <template #cell-items="{ value }"><span class="line-clamp-2 text-xs">{{ itemsText(value) || '—' }}</span></template>
        <template #cell-total_vnd="{ row }">
            <span class="whitespace-nowrap font-semibold">{{ money(row.total_vnd) }}</span>
            <span v-if="row.discount_vnd" class="block text-xs text-success">−{{ money(row.discount_vnd) }}</span>
        </template>
        <template #cell-payment_method="{ value }">{{ value ? te('payment_method', value) : '—' }}</template>
        <template #cell-status="{ row }"><StatusBadge :status="row.status" group="order_status" :label="row.status_label" /></template>
        <template #cell-created_at="{ row }"><span class="whitespace-nowrap text-xs">{{ dateTime(row.created_at || row.paid_at) }}</span></template>
        <template #actions="{ row }">
            <Link :href="lr(`/quan-tri/don-hang/${row.code}`)" class="btn btn-ghost btn-sm btn-icon" :aria-label="`${t('common.view_detail')} ${row.code}`"><Icon name="eye" :size="18" /></Link>
        </template>
    </DataTable>
    <Pagination :paginator="orders" />
</template>
