<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import ChartCard from '@/Components/ChartCard.vue';
import DataTable from '@/Components/DataTable.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

// Thống kê truy cập, lượt xem hiện vật, lượt tour, tìm kiếm, thiết bị; xuất CSV theo từng nhóm
const props = defineProps({
    visitsByDay: { type: Array, default: () => [] },
    artifactViews: { type: Array, default: () => [] },
    tourStarts: { type: Array, default: () => [] },
    searchTop: { type: Array, default: () => [] },
    deviceSplit: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { number, date, percent } = useFormat();
const lr = useLocaleRoute();

const RANGES = [7, 30, 90];
const days = computed(() => Number(props.filters?.days) || 30);
const setRange = (d) => router.get(lr('/quan-tri/thong-ke'), { days: d }, { preserveState: true, preserveScroll: true });
const exportUrl = (type) => `${lr('/quan-tri/thong-ke')}?export=${type}&days=${days.value}`;
const EXPORTS = ['visits', 'artifacts', 'tours', 'search'];

const tourColumns = computed(() => [
    { key: 'name', label: t('admin.common.name') },
    { key: 'starts', label: t('admin.stats.starts'), class: 'text-right' },
    { key: 'completes', label: t('admin.stats.completes'), class: 'text-right' },
    { key: 'completion_rate', label: t('admin.stats.completion_rate'), class: 'text-right' },
]);
const searchColumns = computed(() => [
    { key: 'query', label: t('admin.stats.query') },
    { key: 'count', label: t('admin.stats.count'), class: 'text-right' },
]);
const searchRows = computed(() => props.searchTop.map((s, i) => ({ id: i, query: s.query ?? s.term ?? s.q, count: s.count ?? s.total })));
const tourRows = computed(() => props.tourStarts.map((r, i) => ({ id: i, ...r })));
</script>

<template>
    <Head :title="t('admin.stats.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.stats.title')" :description="t('admin.stats.description')">
        <template #actions>
            <div class="flex gap-1" role="group" :aria-label="t('admin.stats.range')">
                <button v-for="d in RANGES" :key="d" type="button" class="tab" :aria-pressed="days === d" @click="setRange(d)">{{ t('admin.stats.days_n', { n: d }) }}</button>
            </div>
        </template>
    </PageHeader>

    <div class="mb-6 flex flex-wrap gap-2">
        <a v-for="x in EXPORTS" :key="x" :href="exportUrl(x)" class="btn btn-sm"><Icon name="download" :size="16" />{{ t(`admin.stats.export_${x}`) }}</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <ChartCard class="lg:col-span-2" :title="t('admin.stats.chart_visits')" type="line" :labels="visitsByDay.map((x) => date(x.date, { day: '2-digit', month: '2-digit' }))" :values="visitsByDay.map((x) => x.visits ?? x.count)" :series-label="t('admin.dashboard.kpi_visits')" />
        <ChartCard :title="t('admin.stats.chart_artifacts')" type="hbar" :labels="artifactViews.map((x) => x.title)" :values="artifactViews.map((x) => x.views)" :series-label="t('admin.common.views')" :height="320" />
        <ChartCard :title="t('admin.stats.chart_devices')" type="doughnut" :labels="deviceSplit.map((x) => x.label || te('device', x.device))" :values="deviceSplit.map((x) => x.sessions ?? x.count)" :height="320" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.stats.tours') }}</h2>
            <DataTable :columns="tourColumns" :rows="tourRows" :caption="t('admin.stats.tours')">
                <template #cell-starts="{ value }">{{ number(value) }}</template>
                <template #cell-completes="{ value }">{{ number(value) }}</template>
                <template #cell-completion_rate="{ value }"><span class="font-semibold">{{ percent(value, 1) }}</span></template>
            </DataTable>
        </section>
        <section>
            <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.stats.search_top') }}</h2>
            <DataTable :columns="searchColumns" :rows="searchRows" :caption="t('admin.stats.search_top')">
                <template #cell-count="{ value }">{{ number(value) }}</template>
            </DataTable>
        </section>
    </div>
</template>
