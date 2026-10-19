<script setup>
import { computed } from 'vue';
import { useT } from '@/Composables/useT';

/**
 * Lịch chọn ngày tham quan (VisitDay[] ~60 ngày): còn vé / sắp hết / hết vé / đóng cửa.
 * Lưới theo tháng, tuần bắt đầu thứ Hai; ngày không chọn được bị vô hiệu. v-model = 'YYYY-MM-DD'.
 */
const props = defineProps({
    days: { type: Array, default: () => [] },
    lowThreshold: { type: Number, default: 20 },
});
const model = defineModel({ type: String, default: '' });
const { t, locale } = useT();

const intl = computed(() => (locale.value === 'en' ? 'en-GB' : 'vi-VN'));
const byDate = computed(() => new Map(props.days.map((d) => [d.date.slice(0, 10), d])));

function parse(iso) {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d);
}
const keyOf = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const months = computed(() => {
    if (!props.days.length) return [];
    const sorted = [...byDate.value.keys()].sort();
    const first = parse(sorted[0]);
    const last = parse(sorted[sorted.length - 1]);
    const out = [];
    for (let cur = new Date(first.getFullYear(), first.getMonth(), 1); cur <= last; cur = new Date(cur.getFullYear(), cur.getMonth() + 1, 1)) {
        const lead = (cur.getDay() + 6) % 7;
        const daysInMonth = new Date(cur.getFullYear(), cur.getMonth() + 1, 0).getDate();
        const cells = Array.from({ length: lead }, () => null);
        for (let d = 1; d <= daysInMonth; d += 1) {
            const date = new Date(cur.getFullYear(), cur.getMonth(), d);
            cells.push({ date, key: keyOf(date), info: byDate.value.get(keyOf(date)) || null });
        }
        out.push({ key: keyOf(cur), label: new Intl.DateTimeFormat(intl.value, { month: 'long', year: 'numeric' }).format(cur), cells });
    }
    return out;
});

// 01/01/2024 là thứ Hai: lấy tên viết tắt 7 ngày theo locale
const weekdays = computed(() =>
    Array.from({ length: 7 }, (_, i) => new Intl.DateTimeFormat(intl.value, { weekday: 'short' }).format(new Date(2024, 0, 1 + i))),
);

function stateOf(info) {
    if (!info) return 'none';
    if (info.status === 'closed') return 'closed';
    if (info.status === 'sold_out' || Number(info.remaining) <= 0) return 'sold_out';
    return Number(info.remaining) <= props.lowThreshold ? 'low' : 'open';
}
const selectable = (cell) => ['open', 'low'].includes(stateOf(cell.info));
function label(cell) {
    const s = stateOf(cell.info);
    const d = new Intl.DateTimeFormat(intl.value, { weekday: 'long', day: 'numeric', month: 'long' }).format(cell.date);
    if (s === 'none') return `${d}: ${t('calendar.unavailable')}`;
    if (s === 'closed') return `${d}: ${t('calendar.closed')}`;
    if (s === 'sold_out') return `${d}: ${t('calendar.sold_out')}`;
    return `${d}: ${t('calendar.remaining', { n: cell.info.remaining })}`;
}
</script>

<template>
    <div>
        <div class="grid gap-6 md:grid-cols-2">
            <section v-for="m in months" :key="m.key" :aria-label="m.label">
                <h3 class="mb-2 font-body text-sm font-bold capitalize">{{ m.label }}</h3>
                <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-muted" aria-hidden="true">
                    <span v-for="w in weekdays" :key="w">{{ w }}</span>
                </div>
                <div class="mt-1 grid grid-cols-7 gap-1" role="radiogroup" :aria-label="t('calendar.choose_date')">
                    <template v-for="(cell, i) in m.cells" :key="cell ? cell.key : `e${i}`">
                        <span v-if="!cell" aria-hidden="true"></span>
                        <button
                            v-else
                            type="button"
                            role="radio"
                            class="vc-day"
                            :class="`is-${stateOf(cell.info)}`"
                            :aria-checked="model === cell.key"
                            :aria-disabled="!selectable(cell)"
                            :aria-label="label(cell)"
                            :tabindex="selectable(cell) ? 0 : -1"
                            @click="selectable(cell) && (model = cell.key)"
                        >
                            <span class="text-sm font-semibold">{{ cell.date.getDate() }}</span>
                            <span v-if="cell.info && stateOf(cell.info) !== 'closed'" class="vc-sub">{{ stateOf(cell.info) === 'sold_out' ? t('calendar.sold_out_short') : cell.info.remaining }}</span>
                        </button>
                    </template>
                </div>
            </section>
        </div>
        <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs">
            <li class="flex items-center gap-1.5"><span class="vc-dot is-open"></span>{{ t('calendar.legend_open') }}</li>
            <li class="flex items-center gap-1.5"><span class="vc-dot is-low"></span>{{ t('calendar.legend_low') }}</li>
            <li class="flex items-center gap-1.5"><span class="vc-dot is-sold_out"></span>{{ t('calendar.legend_sold_out') }}</li>
            <li class="flex items-center gap-1.5"><span class="vc-dot is-closed"></span>{{ t('calendar.legend_closed') }}</li>
        </ul>
    </div>
</template>

<style scoped>
.vc-day {
    display: flex;
    min-height: 2.75rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    border: 1px solid var(--ds-border);
    background: var(--ds-surface);
    line-height: 1.1;
    cursor: pointer;
}
.vc-sub { font-size: 10px; color: var(--ds-muted); }
.vc-day.is-open { background: var(--ds-success-soft); border-color: transparent; }
.vc-day.is-low { background: var(--ds-warning-soft); border-color: transparent; }
.vc-day.is-sold_out { background: var(--ds-danger-soft); color: var(--ds-danger); cursor: not-allowed; text-decoration: line-through; }
.vc-day.is-closed, .vc-day.is-none { background: var(--ds-surface-2); color: var(--ds-muted); cursor: not-allowed; opacity: 0.7; }
.vc-day[aria-checked='true'] { background: var(--ds-primary); color: var(--ds-on-primary); border-color: var(--ds-primary); }
.vc-day[aria-checked='true'] .vc-sub { color: var(--ds-on-primary); }
.vc-day:not([aria-disabled='true']):hover { border-color: var(--ds-primary); }
.vc-dot { width: 0.75rem; height: 0.75rem; border-radius: 0.25rem; border: 1px solid var(--ds-border); }
.vc-dot.is-open { background: var(--ds-success-soft); }
.vc-dot.is-low { background: var(--ds-warning-soft); }
.vc-dot.is-sold_out { background: var(--ds-danger-soft); }
.vc-dot.is-closed { background: var(--ds-surface-2); }
</style>
