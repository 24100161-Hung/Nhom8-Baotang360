<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FormField from '@/Components/FormField.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import OrderSummary from '@/Components/Shop/OrderSummary.vue';
import PaymentMethodPicker from '@/Components/Shop/PaymentMethodPicker.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    items: { type: Array, default: () => [] },
    subtotal_vnd: { type: Number, default: 0 },
    discount_vnd: { type: Number, default: 0 },
    total_vnd: { type: Number, default: null },
    methods: { type: Array, default: () => ['card', 'bank', 'ewallet'] },
    user: { type: Object, default: null },
    // Nhãn phương thức [{code, name}] và số phút giữ vé từ backend
    methodOptions: { type: Array, default: () => [] },
    holdMinutes: { type: Number, default: 15 },
});
const { t } = useT();
const lr = useLocaleRoute();

const form = useForm({
    contact_name: props.user?.name || '',
    contact_email: props.user?.email || '',
    contact_phone: props.user?.phone || '',
    invoice_address: '',
    note: '',
    payment_method: props.methods?.[0] || 'card',
});
const total = computed(() => props.total_vnd ?? props.subtotal_vnd - props.discount_vnd);
const submit = () => form.post(lr('/thanh-toan'), { preserveScroll: true });
</script>

<template>
    <Head :title="t('checkout.title')" />
    <div class="container-page py-8">
        <Breadcrumbs :items="[{ label: t('nav.cart'), href: lr('/gio-hang') }, { label: t('checkout.title') }]" class="mb-3" />
        <PageHeader :title="t('checkout.title')" :description="t('checkout.intro')" />

        <EmptyState v-if="!items.length" icon="cart" :title="t('cart.empty')">
            <Link :href="lr('/ve')" class="btn btn-primary">{{ t('cart.browse_tickets') }}</Link>
        </EmptyState>

        <form v-else class="grid gap-6 lg:grid-cols-[1fr_22rem]" @submit.prevent="submit">
            <div class="space-y-6">
                <section class="card p-4 md:p-5">
                    <h2 class="font-body text-lg font-bold">{{ t('checkout.invoice_info') }}</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <FormField v-model="form.contact_name" class="sm:col-span-2" :label="t('checkout.contact_name')" autocomplete="name" :error="form.errors.contact_name" required minlength="2" maxlength="120" />
                        <FormField v-model="form.contact_email" type="email" :label="t('common.email')" autocomplete="email" :error="form.errors.contact_email" required maxlength="190" />
                        <FormField v-model="form.contact_phone" type="tel" :label="t('common.phone')" autocomplete="tel" :error="form.errors.contact_phone" required pattern="\+?[0-9]{9,15}" maxlength="16" inputmode="tel" :title="t('validation_hint.phone')" />
                        <FormField v-model="form.invoice_address" class="sm:col-span-2" :label="`${t('checkout.invoice_address')} (${t('common.optional')})`" autocomplete="street-address" :error="form.errors.invoice_address" maxlength="255" />
                        <FormField v-model="form.note" class="sm:col-span-2" type="textarea" :rows="3" :label="`${t('common.note')} (${t('common.optional')})`" :error="form.errors.note" maxlength="500" />
                    </div>
                </section>
                <section class="card p-4 md:p-5">
                    <PaymentMethodPicker v-model="form.payment_method" :methods="methods" :error="form.errors.payment_method" />
                    <p class="mt-3 flex items-start gap-2 text-xs text-muted"><Icon name="shield" :size="16" />{{ t('payment.sandbox_note') }}</p>
                </section>
            </div>

            <OrderSummary :items="items" :subtotal="subtotal_vnd" :discount="discount_vnd" :total="total">
                <p v-if="form.errors.cart || form.errors.visit_date || form.errors.package_id || form.errors.quantity" class="field-error mt-3" role="alert">{{ form.errors.cart || form.errors.visit_date || form.errors.package_id || form.errors.quantity }}</p>
                <button type="submit" class="btn btn-primary mt-4 w-full" :disabled="form.processing">
                    <Icon name="lock" :size="18" />{{ form.processing ? t('common.loading') : t('checkout.place_order') }}
                </button>
                <p class="mt-2 text-xs text-muted">{{ t('checkout.hold_note', { minutes: holdMinutes }) }}</p>
            </OrderSummary>
        </form>
    </div>
</template>
