<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { VISIT_STATUSES, codeOptions } from '@/Components/Admin/options';

// Lưới 90 ngày: sức chứa, đã bán, trạng thái (mở bán / hết vé / đóng cửa); sửa nhanh từng ngày
const props = defineProps({
    days: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date, number } = useFormat();
const lr = useLocaleRoute();

const editing = ref(null);
const form = useForm({ date: '', capacity: 0, status: 'open' });
const TONE = { open: 'bg-success-soft', sold_out: 'bg-danger-soft', closed: 'bg-surface-2' };
const totals = computed(() => props.days.reduce((acc, d) => ({ capacity: acc.capacity + Number(d.capacity || 0), sold: acc.sold + Number(d.sold || 0) }), { capacity: 0, sold: 0 }));
const pct = (d) => (Number(d.capacity) ? Math.min(100, Math.round((Number(d.sold) / Number(d.capacity)) * 100)) : 0);
const weekday = (iso) => date(iso, { weekday: 'short' });

function edit(d) {
    editing.value = d.date;
    form.date = d.date.slice(0, 10);
    form.capacity = d.capacity;
    form.status = d.raw_status ?? (d.status === 'closed' ? 'closed' : 'open');
    form.clearErrors();
}
function save() {
    form.patch(lr('/quan-tri/lich-ve'), { preserveScroll: true, onSuccess: () => (editing.value = null) });
}
</script>

<template>
    <Head :title="t('admin.visitdays.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.visitdays.title')" :description="t('admin.visitdays.description')" />

    <p class="mb-4 text-sm text-muted">{{ t('admin.visitdays.totals', { sold: number(totals.sold), capacity: number(totals.capacity) }) }}</p>
    <ul class="mb-4 flex flex-wrap gap-3 text-xs">
        <li v-for="s in VISIT_STATUSES" :key="s" class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-border" :class="TONE[s]"></span>{{ te('visit_status', s) }}</li>
    </ul>

    <EmptyState v-if="!days.length" icon="calendar" :title="t('admin.visitdays.empty')" />
    <ul v-else class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
        <li v-for="d in days" :key="d.date" class="rounded-lg border border-border p-2 text-sm" :class="TONE[d.status]">
            <template v-if="editing === d.date">
                <form class="space-y-2" @submit.prevent="save">
                    <p class="font-semibold">{{ date(d.date) }}</p>
                    <FormField v-model="form.capacity" type="number" min="0" max="10000" step="1" required :label="t('admin.visitdays.capacity')" :error="form.errors.capacity" />
                    <FormField v-model="form.status" type="select" :label="t('common.status')" :options="codeOptions(['open', 'closed'], te, 'visit_status')" :error="form.errors.status" required />
                    <p v-if="form.errors.date" class="field-error">{{ form.errors.date }}</p>
                    <div class="flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-1" :disabled="form.processing">{{ t('common.save') }}</button>
                        <button type="button" class="btn btn-sm btn-icon" :aria-label="t('common.cancel')" @click="editing = null"><Icon name="close" :size="14" /></button>
                    </div>
                </form>
            </template>
            <template v-else>
                <div class="flex items-start justify-between gap-1">
                    <p>
                        <span class="block text-[11px] uppercase text-muted">{{ weekday(d.date) }}</span>
                        <span class="font-semibold">{{ date(d.date, { day: '2-digit', month: '2-digit' }) }}</span>
                    </p>
                    <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t('admin.visitdays.edit_day', { date: date(d.date) })" @click="edit(d)"><Icon name="edit" :size="14" /></button>
                </div>
                <p class="mt-1 text-xs">{{ te('visit_status', d.status) }}</p>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-border" role="img" :aria-label="t('admin.visitdays.sold_of', { sold: d.sold, capacity: d.capacity })">
                    <div class="h-full bg-primary" :style="{ width: `${pct(d)}%` }"></div>
                </div>
                <p class="mt-1 text-xs tabular-nums text-muted">{{ number(d.sold) }}/{{ number(d.capacity) }}</p>
            </template>
        </li>
    </ul>
</template>
