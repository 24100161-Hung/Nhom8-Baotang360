<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import FormField from '@/Components/FormField.vue';
import DataTable from '@/Components/DataTable.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    order: { type: Object, required: true },
    transitions: { type: Array, default: () => [] }, // ['paid','cancelled'] hoặc [{code,name}]
});
const { t, te } = useT();
const { money, dateTime, date } = useFormat();
const lr = useLocaleRoute();

const o = computed(() => props.order || {});
const options = computed(() => props.transitions.map((x) => (typeof x === 'object' ? { value: x.code, label: x.name || te('order_status', x.code) } : { value: x, label: te('order_status', x) })));
const form = useForm({ status: '', note: '' });
const submit = () => form.patch(lr(`/quan-tri/don-hang/${o.value.code}/trang-thai`), { preserveScroll: true, onSuccess: () => form.reset() });

const info = computed(() => [
    { label: t('admin.orders.customer'), value: o.value.contact_name },
    { label: t('common.email'), value: o.value.contact_email },
    { label: t('common.phone'), value: o.value.contact_phone },
    { label: t('admin.orders.invoice_address'), value: o.value.invoice_address },
    { label: t('admin.orders.method'), value: o.value.payment_method ? te('payment_method', o.value.payment_method) : null },
    { label: t('admin.orders.hold_expires'), value: o.value.hold_expires_at ? dateTime(o.value.hold_expires_at) : null },
    { label: t('payment.paid_at'), value: o.value.paid_at ? dateTime(o.value.paid_at) : null },
    { label: t('common.note'), value: o.value.note },
].filter((x) => x.value));
const paymentColumns = computed(() => [
    { key: 'txn_ref', label: t('payment.txn_ref') },
    { key: 'method', label: t('admin.orders.method') },
    { key: 'amount_vnd', label: t('payment.amount'), class: 'text-right' },
    { key: 'status', label: t('common.status') },
    { key: 'paid_at', label: t('payment.paid_at') },
]);
</script>

<template>
    <Head :title="t('admin.orders.show_title', { code: o.code })" />
    <Breadcrumbs :items="[{ label: t('admin.orders.title'), href: lr('/quan-tri/don-hang') }, { label: o.code }]" class="mb-3" />
    <PageHeader :title="t('admin.orders.show_title', { code: o.code })">
        <template #actions>
            <StatusBadge :status="o.status" group="order_status" :label="o.status_label" />
            <a v-if="o.invoice_url" :href="o.invoice_url" target="_blank" rel="noopener" class="btn btn-sm"><Icon name="printer" :size="16" />{{ t('admin.orders.invoice') }}</a>
        </template>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <section class="card p-4">
                <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.orders.items') }}</h2>
                <ul class="divide-y divide-border">
                    <li v-for="(item, i) in o.items || []" :key="i" class="py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-semibold">{{ item.package_name }} × {{ item.quantity }}</p>
                            <p class="font-semibold">{{ money(item.line_total_vnd) }}</p>
                        </div>
                        <p class="text-xs text-muted">{{ item.visit_date ? date(item.visit_date) : t('admin.orders.no_visit_date') }} · {{ money(item.unit_price_vnd) }}</p>
                        <ul v-if="item.tickets?.length" class="mt-2 flex flex-wrap gap-2">
                            <li v-for="tk in item.tickets" :key="tk.code" class="badge">
                                <code>{{ tk.code }}</code> · {{ te('ticket_status', tk.status) }}
                            </li>
                        </ul>
                    </li>
                </ul>
                <dl class="mt-3 space-y-1 border-t border-border pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ t('admin.orders.subtotal') }}</dt><dd>{{ money(o.subtotal_vnd) }}</dd></div>
                    <div v-if="o.discount_vnd" class="flex justify-between text-success"><dt>{{ t('admin.orders.discount') }}</dt><dd>−{{ money(o.discount_vnd) }}</dd></div>
                    <div class="flex justify-between text-base font-bold"><dt>{{ t('common.total') }}</dt><dd>{{ money(o.total_vnd) }}</dd></div>
                </dl>
            </section>
            <section>
                <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.orders.payments') }}</h2>
                <DataTable :columns="paymentColumns" :rows="o.payments || []" row-key="txn_ref" :caption="t('admin.orders.payments')">
                    <template #cell-txn_ref="{ value }"><code class="break-all text-xs">{{ value }}</code></template>
                    <template #cell-method="{ value }">{{ value ? te('payment_method', value) : '—' }}</template>
                    <template #cell-amount_vnd="{ value }">{{ money(value) }}</template>
                    <template #cell-status="{ value }"><StatusBadge :status="value" group="payment_status" /></template>
                    <template #cell-paid_at="{ value }"><span class="whitespace-nowrap text-xs">{{ value ? dateTime(value) : '—' }}</span></template>
                </DataTable>
            </section>
        </div>
        <aside class="space-y-6">
            <section class="card p-4">
                <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.orders.info') }}</h2>
                <dl class="space-y-2 text-sm">
                    <div v-for="x in info" :key="x.label"><dt class="text-xs font-semibold uppercase tracking-wider text-muted">{{ x.label }}</dt><dd class="break-words">{{ x.value }}</dd></div>
                </dl>
            </section>
            <section class="card p-4">
                <h2 class="mb-3 font-body text-lg font-bold">{{ t('admin.orders.change_status') }}</h2>
                <p v-if="!options.length" class="text-sm text-muted">{{ t('admin.orders.no_transitions') }}</p>
                <form v-else class="space-y-3" @submit.prevent="submit">
                    <FormField v-model="form.status" type="select" :label="t('admin.orders.new_status')" :options="options" :placeholder="t('admin.form.choose')" :error="form.errors.status" required />
                    <FormField v-model="form.note" type="textarea" :rows="2" :label="t('common.note')" :error="form.errors.note" maxlength="255" />
                    <button type="submit" class="btn btn-primary w-full" :disabled="!form.status || form.processing">{{ t('admin.orders.apply_status') }}</button>
                </form>
            </section>
        </aside>
    </div>
</template>
