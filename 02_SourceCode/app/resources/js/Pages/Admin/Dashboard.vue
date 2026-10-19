<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import ChartCard from '@/Components/ChartCard.vue';
import DataTable from '@/Components/DataTable.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

// Tổng quan quản trị: 8 KPI + 4 biểu đồ; curator nhận null cho số liệu doanh thu/đơn → ẩn
const props = defineProps({
    kpis: { type: Object, default: () => ({}) },
    charts: { type: Object, default: () => ({}) },
    recentAudit: { type: Array, default: () => [] },
    analyticsUp: { type: Boolean, default: true },
});
const { t, te } = useT();
const { number, money, dateTime, date, percent } = useFormat();

const k = computed(() => props.kpis || {});
const has = (key) => k.value[key] !== null && k.value[key] !== undefined;
const cards = computed(() =>
    [
        { key: 'scenes', label: t('admin.dashboard.kpi_scenes'), value: number(k.value.scenes ?? 0), icon: 'panorama', tone: 'primary' },
        { key: 'users', label: t('admin.dashboard.kpi_users'), value: number(k.value.users ?? 0), icon: 'users', tone: 'info' },
        { key: 'orders', label: t('admin.dashboard.kpi_orders'), value: number(k.value.orders), icon: 'cart', tone: 'accent' },
        { key: 'revenue_vnd', label: t('admin.dashboard.kpi_revenue'), value: money(k.value.revenue_vnd ?? 0) || '0', icon: 'ticket', tone: 'success' },
        { key: 'cancel_rate', label: t('admin.dashboard.kpi_cancel_rate'), value: percent(k.value.cancel_rate, 1), icon: 'alert', tone: 'accent' },
        { key: 'visits', label: t('admin.dashboard.kpi_visits'), value: number(k.value.visits ?? 0), icon: 'eye', tone: 'info' },
        { key: 'hotspot_clicks', label: t('admin.dashboard.kpi_hotspot_clicks'), value: number(k.value.hotspot_clicks ?? 0), icon: 'pin', tone: 'primary' },
        { key: 'pending_orders', label: t('admin.dashboard.kpi_pending_orders'), value: number(k.value.pending_orders), icon: 'clock', tone: 'accent' },
    ].filter((c) => ['scenes', 'users', 'visits', 'hotspot_clicks'].includes(c.key) || has(c.key)),
);
const showSales = computed(() => has('revenue_vnd') || has('orders'));

const short = (iso) => date(iso, { day: '2-digit', month: '2-digit' });
const revenue = computed(() => props.charts?.revenue7d || []);
const byStatus = computed(() => props.charts?.ordersByStatus || []);
const visits = computed(() => props.charts?.visitsByDay || []);
const topScenes = computed(() => props.charts?.topScenes || []);

const auditColumns = computed(() => [
    { key: 'created_at', label: t('admin.audit.time') },
    { key: 'user', label: t('admin.audit.user') },
    { key: 'action', label: t('admin.audit.action') },
    { key: 'subject', label: t('admin.audit.subject') },
]);
const userName = (u) => (u && typeof u === 'object' ? u.name || u.email : u) || t('admin.audit.system');
const subjectText = (s) => (s && typeof s === 'object' ? [te('subject_type', s.type), s.title || s.id].filter(Boolean).join(' – ') : s) || '—';
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.dashboard.title')" :description="t('admin.dashboard.intro')" />

    <p v-if="!analyticsUp" class="mb-6 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="alert">
        <Icon name="alert" :size="18" />{{ t('admin.dashboard.analytics_down') }}
    </p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard v-for="c in cards" :key="c.key" :label="c.label" :value="c.value" :icon="c.icon" :tone="c.tone" />
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <ChartCard v-if="showSales" :title="t('admin.dashboard.chart_revenue')" type="line" :labels="revenue.map((x) => short(x.date))" :values="revenue.map((x) => x.revenue_vnd)" :series-label="t('admin.dashboard.kpi_revenue')" />
        <ChartCard v-if="showSales" :title="t('admin.dashboard.chart_orders')" type="doughnut" :labels="byStatus.map((x) => x.label || te('order_status', x.status))" :values="byStatus.map((x) => x.count)" />
        <ChartCard :title="t('admin.dashboard.chart_visits')" type="line" :labels="visits.map((x) => short(x.date))" :values="visits.map((x) => x.visits ?? x.count)" :series-label="t('admin.dashboard.kpi_visits')" />
        <ChartCard :title="t('admin.dashboard.chart_top_scenes')" type="hbar" :labels="topScenes.map((x) => x.title)" :values="topScenes.map((x) => x.enters)" :series-label="t('admin.dashboard.enters')" :height="300" />
    </div>

    <section class="mt-6">
        <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.dashboard.recent_audit') }}</h2>
        <DataTable :columns="auditColumns" :rows="recentAudit || []" :caption="t('admin.dashboard.recent_audit')">
            <template #cell-created_at="{ value }"><span class="whitespace-nowrap">{{ dateTime(value) }}</span></template>
            <template #cell-user="{ value }">{{ userName(value) }}</template>
            <template #cell-action="{ value }"><code class="text-xs">{{ value }}</code></template>
            <template #cell-subject="{ value }">{{ subjectText(value) }}</template>
        </DataTable>
    </section>
</template>
