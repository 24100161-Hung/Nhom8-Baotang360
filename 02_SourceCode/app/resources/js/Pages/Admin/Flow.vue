<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import DataTable from '@/Components/DataTable.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

// Phân tích luồng tham quan (Python): phễu drop-off từng tour, ma trận chuyển cảnh, tỉ lệ rời phòng
const props = defineProps({
    report: { type: Object, default: null },
    analyticsUp: { type: Boolean, default: true },
});
const { t } = useT();
const { number, dateTime, percent, msToSec } = useFormat();
const lr = useLocaleRoute();
const form = useForm({});
const run = () => form.post(lr('/quan-tri/phan-tich-luong'), { preserveScroll: true });

// drop_rate/exit_rate có thể là 0..1 hoặc 0..100: chuẩn hóa về phần trăm
const pct = (v) => (v == null ? '—' : percent(Number(v) <= 1 ? Number(v) * 100 : v, 1));
const dropoff = computed(() => props.report?.dropoff || []);
const nameOf = (x) => (x && typeof x === 'object' ? x.title || x.slug : x);
const flow = computed(() => (props.report?.scene_flow || []).map((f) => ({ from: nameOf(f.from), to: nameOf(f.to), count: Number(f.count) || 0 })));
const exits = computed(() => (props.report?.scene_exit || []).map((r, i) => ({ id: i, ...r })));
const sceneNames = computed(() => [...new Set(flow.value.flatMap((f) => [f.from, f.to]))]);
const maxCount = computed(() => Math.max(1, ...flow.value.map((f) => Number(f.count) || 0)));
const cell = (from, to) => flow.value.find((f) => f.from === from && f.to === to)?.count || 0;
const heat = (n) => (n ? { background: `color-mix(in srgb, var(--ds-primary) ${Math.round(15 + (n / maxCount.value) * 75)}%, transparent)`, color: n / maxCount.value > 0.5 ? 'var(--ds-on-primary)' : undefined } : {});
const isWorst = (tour, step) => tour.worst_step === step.order || tour.worst_step?.order === step.order;
const topFlows = computed(() => [...flow.value].sort((a, b) => b.count - a.count).slice(0, 15).map((f, i) => ({ id: i, ...f })));
const exitColumns = computed(() => [
    { key: 'scene', label: t('admin.flow.scene') },
    { key: 'enters', label: t('admin.flow.enters'), class: 'text-right' },
    { key: 'avg_dwell_ms', label: t('admin.flow.avg_dwell'), class: 'text-right' },
    { key: 'exit_rate', label: t('admin.flow.exit_rate'), class: 'text-right' },
]);
const flowColumns = computed(() => [
    { key: 'from', label: t('admin.flow.from') },
    { key: 'to', label: t('admin.flow.to') },
    { key: 'count', label: t('admin.stats.count'), class: 'text-right' },
]);
const recs = computed(() => props.report?.recommendations_sample || []);
</script>

<template>
    <Head :title="t('admin.flow.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.flow.title')" :description="t('admin.flow.description')">
        <template #actions>
            <button type="button" class="btn btn-primary" :disabled="form.processing" @click="run">
                <Icon name="refresh" :size="18" :class="form.processing ? 'animate-spin' : ''" />{{ form.processing ? t('admin.quality.running') : t('admin.flow.run') }}
            </button>
        </template>
    </PageHeader>
    <p v-if="!analyticsUp" class="mb-4 flex items-start gap-2 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="alert"><Icon name="alert" :size="18" />{{ t('admin.flow.analytics_down') }}</p>

    <EmptyState v-if="!report" icon="sliders" :title="t('admin.flow.no_report')" :description="t('admin.flow.no_report_hint')" />
    <template v-else>
        <p class="mb-4 text-sm text-muted">{{ t('admin.quality.generated_at', { time: dateTime(report.generated_at) }) }}</p>

        <section class="mb-8">
            <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.flow.dropoff') }}</h2>
            <p v-if="!dropoff.length" class="text-sm text-muted">{{ t('common.no_data') }}</p>
            <div class="grid gap-4 xl:grid-cols-2">
                <article v-for="tour in dropoff" :key="tour.tour?.slug || tour.tour" class="card overflow-hidden">
                    <h3 class="border-b border-border px-4 py-3 font-body text-base font-bold">{{ tour.tour?.name || tour.tour }}</h3>
                    <div class="overflow-x-auto">
                        <table class="table-ds">
                            <caption class="sr-only">{{ t('admin.flow.dropoff') }} – {{ tour.tour?.name || tour.tour }}</caption>
                            <thead><tr><th scope="col">#</th><th scope="col">{{ t('admin.common.title') }}</th><th scope="col" class="text-right">{{ t('admin.flow.reached') }}</th><th scope="col" class="text-right">{{ t('admin.flow.dropped') }}</th><th scope="col" class="text-right">{{ t('admin.flow.drop_rate') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="s in tour.steps || []" :key="s.order" :class="isWorst(tour, s) ? 'bg-danger-soft text-danger' : ''">
                                    <td>{{ s.order }}</td>
                                    <td>{{ s.title || '—' }}<span v-if="isWorst(tour, s)" class="badge badge-danger ml-2">{{ t('admin.flow.worst') }}</span></td>
                                    <td class="text-right tabular-nums">{{ number(s.reached) }}</td>
                                    <td class="text-right tabular-nums">{{ number(s.dropped) }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ pct(s.drop_rate) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="mb-8">
            <h2 class="mb-1 font-body text-lg font-bold">{{ t('admin.flow.matrix') }}</h2>
            <p class="mb-3 text-sm text-muted">{{ t('admin.flow.matrix_hint') }}</p>
            <p v-if="!flow.length" class="text-sm text-muted">{{ t('common.no_data') }}</p>
            <div v-else class="card overflow-x-auto">
                <table class="table-ds text-xs">
                    <caption class="sr-only">{{ t('admin.flow.matrix') }}</caption>
                    <thead><tr><th scope="col">{{ t('admin.flow.from') }} \ {{ t('admin.flow.to') }}</th><th v-for="to in sceneNames" :key="to" scope="col" class="text-center">{{ to }}</th></tr></thead>
                    <tbody>
                        <tr v-for="from in sceneNames" :key="from">
                            <th scope="row" class="whitespace-nowrap">{{ from }}</th>
                            <td v-for="to in sceneNames" :key="to" class="text-center tabular-nums" :style="heat(cell(from, to))">{{ cell(from, to) || '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 lg:w-1/2">
                <DataTable :columns="flowColumns" :rows="topFlows" :caption="t('admin.flow.top_flows')">
                    <template #cell-count="{ value }">{{ number(value) }}</template>
                </DataTable>
            </div>
        </section>

        <section class="mb-8">
            <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.flow.scene_exit') }}</h2>
            <DataTable :columns="exitColumns" :rows="exits" :caption="t('admin.flow.scene_exit')">
                <template #cell-scene="{ value }">{{ value?.title || value }}</template>
                <template #cell-enters="{ value }">{{ number(value) }}</template>
                <template #cell-avg_dwell_ms="{ value }">{{ msToSec(value) }}</template>
                <template #cell-exit_rate="{ value }">{{ pct(value) }}</template>
            </DataTable>
        </section>

        <section v-if="recs.length">
            <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.flow.recommendations') }}</h2>
            <ul class="grid gap-3 md:grid-cols-2">
                <li v-for="(r, i) in recs" :key="i" class="card p-4 text-sm">
                    <p class="font-semibold">{{ r.tour?.name || r.tour || r.slug }}</p>
                    <ul class="mt-1 list-disc pl-5 text-muted">
                        <li v-for="(x, j) in r.results || r.similar || []" :key="j">{{ x.name || x.slug }} <span v-if="x.score != null">({{ Number(x.score).toFixed(2) }})</span></li>
                    </ul>
                </li>
            </ul>
        </section>
    </template>
</template>
