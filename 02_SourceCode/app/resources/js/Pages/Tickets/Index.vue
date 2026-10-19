<script setup>
import { computed, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PackageCard from '@/Components/PackageCard.vue';
import VisitCalendar from '@/Components/VisitCalendar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import PackageCompare from '@/Components/Shop/PackageCompare.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    packages: { type: Array, default: () => [] },
    calendar: { type: Array, default: () => [] },
    membership: { type: Object, default: null },
    // % giảm cho hội viên Premium trên vé lượt (backend: discountPercent)
    discountPercent: { type: Number, default: 20 },
});
const page = usePage();
const { t, te } = useT();
const { money, date } = useFormat();
const lr = useLocaleRoute();

const list = computed(() => props.packages || []);
const form = useForm({
    package_id: list.value.find((p) => p.is_featured)?.id ?? list.value[0]?.id ?? null,
    visit_date: '',
    quantity: 1,
});
const selected = computed(() => list.value.find((p) => p.id === form.package_id) || null);
const isSingle = computed(() => selected.value?.package_type !== 'membership');
const maxQty = computed(() => Number(selected.value?.max_per_order) || 10);
// Hội viên Premium còn hạn được giảm 20% vé lượt (Regular); số liệu cuối cùng do máy chủ tính
const isMember = computed(() => !!(props.membership?.is_active || page.props.auth?.user?.membership?.is_active));
const hasDiscount = computed(() => isMember.value && isSingle.value);
const unitPrice = computed(() => Number(selected.value?.price_vnd || 0));
const estimate = computed(() => {
    const gross = unitPrice.value * Number(form.quantity || 0);
    return hasDiscount.value ? Math.round(gross * (1 - props.discountPercent / 100)) : gross;
});
const canSubmit = computed(() => !!selected.value && (!isSingle.value || !!form.visit_date) && form.quantity >= 1);

watch(maxQty, (m) => {
    if (form.quantity > m) form.quantity = m;
});

function submit() {
    form.transform((d) => ({ ...d, visit_date: isSingle.value ? d.visit_date : null }))
        .post(lr('/gio-hang'), { preserveScroll: true, onSuccess: () => form.reset('quantity') });
}
</script>

<template>
    <PageMeta :title="t('tickets.title')" :description="t('tickets.intro')" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.tickets')" :title="t('tickets.title')" :description="t('tickets.intro')">
            <template #actions>
                <Link :href="lr('/gio-hang')" class="btn"><Icon name="cart" :size="18" />{{ t('nav.cart') }} ({{ page.props.cart?.count || 0 }})</Link>
            </template>
        </PageHeader>

        <p v-if="isMember" class="mb-6 flex items-start gap-2 rounded-lg bg-success-soft p-3 text-sm text-success" role="status">
            <Icon name="award" :size="18" />{{ t('tickets.member_notice', { date: membership?.ends_at ? date(membership.ends_at) : '' }) }}
        </p>

        <EmptyState v-if="!list.length" icon="ticket" :title="t('tickets.empty')" />
        <template v-else>
            <section aria-labelledby="pkg-title">
                <h2 id="pkg-title" class="sr-only">{{ t('tickets.packages') }}</h2>
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <PackageCard v-for="p in list" :key="p.id" :pkg="p" :selected="form.package_id === p.id">
                        <template #actions>
                            <button type="button" class="btn w-full" :class="form.package_id === p.id ? 'btn-primary' : ''" :aria-pressed="form.package_id === p.id" @click="form.package_id = p.id">
                                {{ form.package_id === p.id ? t('tickets.selected') : t('tickets.choose') }}
                            </button>
                        </template>
                    </PackageCard>
                </div>
            </section>

            <section class="mt-10" aria-labelledby="compare-title">
                <h2 id="compare-title" class="section-title mb-4">{{ t('tickets.compare_title') }}</h2>
                <PackageCompare :packages="list" />
            </section>

            <section class="mt-10 grid gap-6 lg:grid-cols-[1fr_22rem]" aria-labelledby="buy-title">
                <div class="card p-4 md:p-5">
                    <h2 id="buy-title" class="font-body text-lg font-bold">{{ t('tickets.calendar_title') }}</h2>
                    <p class="mb-4 text-sm text-muted">{{ isSingle ? t('tickets.calendar_hint') : t('tickets.membership_no_date') }}</p>
                    <EmptyState v-if="!calendar.length" icon="calendar" :title="t('tickets.calendar_empty')" compact />
                    <VisitCalendar v-else-if="isSingle" v-model="form.visit_date" :days="calendar" />
                    <p v-if="form.errors.visit_date" class="field-error" role="alert">{{ form.errors.visit_date }}</p>
                </div>

                <form class="card h-fit space-y-4 p-4 md:p-5" @submit.prevent="submit">
                    <h2 class="font-body text-lg font-bold">{{ t('tickets.add_title') }}</h2>
                    <fieldset>
                        <legend class="label">{{ t('tickets.package') }}</legend>
                        <div class="space-y-2">
                            <label v-for="p in list" :key="p.id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-border p-2.5 text-sm" :class="form.package_id === p.id ? 'border-primary bg-primary-soft' : ''">
                                <input v-model="form.package_id" type="radio" name="package_id" :value="p.id" required class="h-4 w-4 accent-[var(--ds-primary)]" />
                                <span class="flex-1 font-medium">{{ p.name }}</span>
                                <span class="tabular-nums">{{ money(p.price_vnd) }}</span>
                            </label>
                        </div>
                        <p v-if="form.errors.package_id" class="field-error" role="alert">{{ form.errors.package_id }}</p>
                    </fieldset>
                    <div v-if="isSingle" class="text-sm">
                        <p class="label">{{ t('tickets.visit_date') }}</p>
                        <p :class="form.visit_date ? 'font-semibold' : 'text-muted'">{{ form.visit_date ? date(form.visit_date) : t('tickets.pick_date_first') }}</p>
                    </div>
                    <FormField v-model.number="form.quantity" type="number" :min="1" :max="maxQty" :label="t('tickets.quantity')" :hint="t('tickets.max_per_order', { n: maxQty })" :error="form.errors.quantity" required step="1" />
                    <dl class="space-y-1 border-t border-border pt-3 text-sm">
                        <div v-if="hasDiscount" class="flex justify-between text-success"><dt>{{ t('checkout.member_discount') }}</dt><dd>−{{ discountPercent }}%</dd></div>
                        <div class="flex items-baseline justify-between">
                            <dt class="font-semibold">{{ t('tickets.estimate') }}</dt>
                            <dd class="font-display text-2xl font-bold text-primary">{{ money(estimate) }}</dd>
                        </div>
                    </dl>
                    <button type="submit" class="btn btn-primary w-full" :disabled="form.processing || !canSubmit">
                        <Icon name="cart" :size="18" />{{ form.processing ? t('common.saving') : t('tickets.add_to_cart') }}
                    </button>
                    <p v-if="selected" class="text-xs text-muted">{{ te('package_type', selected.package_type) }} · {{ t('tickets.validity', { n: selected.validity_days }) }}</p>
                </form>
            </section>
        </template>
    </div>
</template>
