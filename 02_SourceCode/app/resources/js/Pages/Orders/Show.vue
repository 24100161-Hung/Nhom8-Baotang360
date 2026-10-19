<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Countdown from '@/Components/Countdown.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import PaymentMethodPicker from '@/Components/Shop/PaymentMethodPicker.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    order: { type: Object, default: () => ({ items: [], payments: [] }) },
    sandboxGateway: { type: [Boolean, String, Object], default: true },
});
const { t, te } = useT();
const { money, date, dateTime } = useFormat();
const lr = useLocaleRoute();

const o = computed(() => props.order || {});
const base = computed(() => lr(`/don-hang/${o.value.code}`));
const payForm = useForm({ payment_method: props.order?.payment_method || 'card' });
const cancelOpen = ref(false);
const cancelling = ref(false);
const tickets = computed(() => (o.value.items || []).flatMap((it) => (it.tickets || []).map((tk) => ({ ...tk, package_name: it.package_name }))));
const isPending = computed(() => o.value.status === 'pending');

const pay = () => payForm.post(`${base.value}/thanh-toan`, { preserveScroll: true });
function cancel() {
    cancelling.value = true;
    router.post(`${base.value}/huy`, {}, { preserveScroll: true, onFinish: () => { cancelling.value = false; cancelOpen.value = false; } });
}
// Hết thời gian giữ: tải lại để máy chủ cập nhật trạng thái expired
const onExpired = () => router.reload({ only: ['order'] });
</script>

<template>
    <Head :title="t('orders.title', { code: o.code || '' })" />
    <div class="container-page max-w-5xl py-8">
        <Breadcrumbs :items="[{ label: t('nav.dashboard'), href: lr('/tai-khoan') }, { label: o.code || t('orders.order') }]" class="mb-3" />
        <PageHeader :eyebrow="t('orders.order')" :title="t('orders.title', { code: o.code || '' })">
            <template #actions>
                <StatusBadge v-if="o.status" :status="o.status" group="order_status" :label="o.status_label" />
                <a v-if="o.invoice_url" :href="o.invoice_url" target="_blank" rel="noopener" class="btn"><Icon name="printer" :size="18" />{{ t('orders.print_invoice') }}</a>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-6">
                <section v-if="isPending" class="card space-y-4 p-4 md:p-5" aria-labelledby="pay-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="pay-title" class="font-body text-lg font-bold">{{ t('orders.pay_title') }}</h2>
                        <Countdown :seconds="o.seconds_left" :expires-at="o.hold_expires_at" @expired="onExpired" />
                    </div>
                    <p class="text-sm text-muted">{{ t('orders.pay_hint') }}</p>
                    <PaymentMethodPicker v-if="o.can_pay" v-model="payForm.payment_method" :error="payForm.errors.payment_method" />
                    <div class="flex flex-wrap gap-2">
                        <button v-if="o.can_pay" type="button" class="btn btn-primary" :disabled="payForm.processing" @click="pay">
                            <Icon name="lock" :size="18" />{{ payForm.processing ? t('common.loading') : t('orders.pay_now', { amount: money(o.total_vnd) }) }}
                        </button>
                        <button v-if="o.can_cancel" type="button" class="btn btn-danger" @click="cancelOpen = true">{{ t('orders.cancel') }}</button>
                    </div>
                    <p v-if="sandboxGateway" class="flex items-start gap-2 text-xs text-muted"><Icon name="shield" :size="16" />{{ t('payment.sandbox_note') }}</p>
                </section>
                <div v-else-if="o.can_cancel" class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <p class="text-sm text-muted">{{ t('orders.refund_hint') }}</p>
                    <button type="button" class="btn btn-danger" @click="cancelOpen = true">{{ t('orders.cancel_refund') }}</button>
                </div>

                <section aria-labelledby="tickets-title">
                    <h2 id="tickets-title" class="mb-3 font-body text-lg font-bold">{{ t('orders.tickets') }}</h2>
                    <EmptyState v-if="!tickets.length" icon="qr" :title="isPending ? t('orders.tickets_after_pay') : t('orders.no_tickets')" compact />
                    <ul v-else class="grid gap-4 sm:grid-cols-2">
                        <li v-for="tk in tickets" :key="tk.code" class="card flex gap-4 p-4">
                            <img v-if="tk.qr_url" :src="tk.qr_url" :alt="t('orders.qr_alt', { code: tk.code })" width="112" height="112" class="h-28 w-28 shrink-0 rounded-lg border border-border bg-white p-1" loading="lazy" />
                            <div class="min-w-0 flex-1 space-y-1 text-sm">
                                <p class="font-mono text-xs font-bold">{{ tk.code }}</p>
                                <p class="font-semibold">{{ tk.package_name }}</p>
                                <p class="text-muted">{{ tk.visit_date ? date(tk.visit_date) : t('cart.no_date') }}</p>
                                <p v-if="tk.holder_name" class="truncate text-muted">{{ tk.holder_name }}</p>
                                <StatusBadge :status="tk.status" group="ticket_status" />
                                <a v-if="tk.pdf_url" :href="tk.pdf_url" class="btn btn-sm mt-1" target="_blank" rel="noopener"><Icon name="download" :size="16" />{{ t('orders.download_pdf') }}</a>
                            </div>
                        </li>
                    </ul>
                </section>

                <section v-if="o.payments?.length" aria-labelledby="pay-history">
                    <h2 id="pay-history" class="mb-3 font-body text-lg font-bold">{{ t('orders.payments') }}</h2>
                    <div class="card overflow-x-auto">
                        <table class="table-ds">
                            <thead><tr><th scope="col">{{ t('payment.txn_ref') }}</th><th scope="col">{{ t('checkout.payment_method') }}</th><th scope="col">{{ t('common.status') }}</th><th scope="col" class="text-right">{{ t('payment.amount') }}</th><th scope="col">{{ t('payment.paid_at') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="p in o.payments" :key="p.txn_ref">
                                    <td><code class="break-all text-xs">{{ p.txn_ref }}</code></td>
                                    <td>{{ te('payment_method', p.method) }}</td>
                                    <td><StatusBadge :status="p.status" group="payment_status" /></td>
                                    <td class="text-right tabular-nums">{{ money(p.amount_vnd) }}</td>
                                    <td class="whitespace-nowrap">{{ p.paid_at ? dateTime(p.paid_at) : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <section class="card p-4 text-sm">
                    <h2 class="font-body text-base font-bold">{{ t('orders.items') }}</h2>
                    <ul class="mt-2 divide-y divide-border">
                        <li v-for="(it, i) in o.items || []" :key="i" class="flex justify-between gap-2 py-2">
                            <span class="min-w-0"><span class="block font-medium">{{ it.package_name }} × {{ it.quantity }}</span><span class="text-xs text-muted">{{ it.visit_date ? date(it.visit_date) : t('cart.no_date') }}</span></span>
                            <span class="tabular-nums">{{ money(it.line_total_vnd) }}</span>
                        </li>
                    </ul>
                    <dl class="mt-2 space-y-1 border-t border-border pt-2">
                        <div class="flex justify-between"><dt class="text-muted">{{ t('checkout.subtotal') }}</dt><dd>{{ money(o.subtotal_vnd) }}</dd></div>
                        <div v-if="o.discount_vnd" class="flex justify-between text-success"><dt>{{ t('checkout.member_discount') }}</dt><dd>−{{ money(o.discount_vnd) }}</dd></div>
                        <div class="flex justify-between font-bold"><dt>{{ t('checkout.total') }}</dt><dd class="text-primary">{{ money(o.total_vnd) }}</dd></div>
                    </dl>
                </section>
                <section class="card p-4 text-sm">
                    <h2 class="font-body text-base font-bold">{{ t('checkout.invoice_info') }}</h2>
                    <dl class="mt-2 space-y-1.5">
                        <div><dt class="text-xs text-muted">{{ t('checkout.contact_name') }}</dt><dd>{{ o.contact_name }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ t('common.email') }}</dt><dd class="break-all">{{ o.contact_email }}</dd></div>
                        <div><dt class="text-xs text-muted">{{ t('common.phone') }}</dt><dd>{{ o.contact_phone }}</dd></div>
                        <div v-if="o.invoice_address"><dt class="text-xs text-muted">{{ t('checkout.invoice_address') }}</dt><dd>{{ o.invoice_address }}</dd></div>
                        <div v-if="o.payment_method"><dt class="text-xs text-muted">{{ t('checkout.payment_method') }}</dt><dd>{{ te('payment_method', o.payment_method) }}</dd></div>
                        <div v-if="o.paid_at"><dt class="text-xs text-muted">{{ t('payment.paid_at') }}</dt><dd>{{ dateTime(o.paid_at) }}</dd></div>
                        <div v-if="o.note"><dt class="text-xs text-muted">{{ t('common.note') }}</dt><dd class="whitespace-pre-line">{{ o.note }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>

        <ConfirmDialog
            v-model:open="cancelOpen"
            :title="t('orders.cancel_title')"
            :message="isPending ? t('orders.cancel_message') : t('orders.refund_message')"
            :confirm-text="t('orders.cancel')"
            danger
            :processing="cancelling"
            @confirm="cancel"
        />
    </div>
</template>
