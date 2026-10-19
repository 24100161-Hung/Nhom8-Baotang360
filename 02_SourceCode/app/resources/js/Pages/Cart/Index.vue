<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Icon from '@/Components/Icon.vue';
import OrderSummary from '@/Components/Shop/OrderSummary.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    items: { type: Array, default: () => [] },
    subtotal_vnd: { type: Number, default: 0 },
    calendar: { type: Array, default: () => [] },
    canCheckout: { type: Boolean, default: false },
});
const page = usePage();
const { t } = useT();
const { money, date } = useFormat();
const lr = useLocaleRoute();

const saving = reactive({});
const removing = ref(null);
const errors = computed(() => page.props.errors || {});
const isLoggedIn = computed(() => !!page.props.auth?.user);
// Chỉ cho đổi sang ngày còn mở bán
const openDays = computed(() => (props.calendar || []).filter((d) => d.status === 'open' && Number(d.remaining) > 0));

function update(item, data) {
    saving[item.id] = true;
    router.patch(lr(`/gio-hang/${item.id}`), data, { preserveScroll: true, onFinish: () => (saving[item.id] = false) });
}
function changeQty(item, delta) {
    const max = Number(item.package?.max_per_order) || 10;
    const q = Math.min(max, Math.max(1, Number(item.quantity) + delta));
    if (q !== Number(item.quantity)) update(item, { quantity: q });
}
function confirmRemove() {
    const item = removing.value;
    if (!item) return;
    router.delete(lr(`/gio-hang/${item.id}`), { preserveScroll: true, onFinish: () => (removing.value = null) });
}
</script>

<template>
    <PageMeta :title="t('cart.title')" :description="t('cart.intro')" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.tickets')" :title="t('cart.title')" :description="t('cart.intro')" />
        <p v-if="errors.quantity || errors.visit_date || errors.cart" class="mb-4 rounded-lg bg-danger-soft p-3 text-sm text-danger" role="alert">{{ errors.quantity || errors.visit_date || errors.cart }}</p>

        <EmptyState v-if="!items.length" icon="cart" :title="t('cart.empty')" :description="t('cart.empty_hint')">
            <Link :href="lr('/ve')" class="btn btn-primary"><Icon name="ticket" :size="18" />{{ t('cart.browse_tickets') }}</Link>
        </EmptyState>

        <div v-else class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <ul class="space-y-3">
                <li v-for="item in items" :key="item.id" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ item.package?.name }}</p>
                        <p class="text-sm text-muted">{{ money(item.unit_price_vnd) }} / {{ t('cart.per_ticket') }}</p>
                        <div v-if="item.visit_date" class="mt-2 max-w-xs">
                            <label :for="`date-${item.id}`" class="label text-xs">{{ t('tickets.visit_date') }}</label>
                            <select :id="`date-${item.id}`" class="input" :value="item.visit_date.slice(0, 10)" :disabled="saving[item.id]" @change="update(item, { visit_date: $event.target.value })">
                                <option :value="item.visit_date.slice(0, 10)">{{ date(item.visit_date) }}</option>
                                <option v-for="d in openDays.filter((x) => x.date.slice(0, 10) !== item.visit_date.slice(0, 10))" :key="d.date" :value="d.date.slice(0, 10)">
                                    {{ date(d.date) }} – {{ t('calendar.remaining', { n: d.remaining }) }}
                                </option>
                            </select>
                        </div>
                        <p v-else class="mt-1 text-xs text-muted">{{ t('cart.no_date') }}</p>
                    </div>
                    <div class="flex items-center gap-2" role="group" :aria-label="t('cart.quantity_for', { name: item.package?.name })">
                        <button type="button" class="btn btn-icon btn-sm" :disabled="saving[item.id] || item.quantity <= 1" :aria-label="t('cart.decrease')" @click="changeQty(item, -1)">−</button>
                        <span class="w-8 text-center font-semibold tabular-nums" aria-live="polite">{{ item.quantity }}</span>
                        <button type="button" class="btn btn-icon btn-sm" :disabled="saving[item.id] || item.quantity >= (item.package?.max_per_order || 10)" :aria-label="t('cart.increase')" @click="changeQty(item, 1)">+</button>
                    </div>
                    <p class="w-28 font-semibold tabular-nums sm:text-right">{{ money(item.line_total_vnd) }}</p>
                    <button type="button" class="btn btn-ghost btn-icon text-danger" :aria-label="`${t('common.delete')} ${item.package?.name || ''}`" @click="removing = item">
                        <Icon name="trash" :size="18" />
                    </button>
                </li>
            </ul>

            <OrderSummary :items="items" :subtotal="subtotal_vnd" :title="t('cart.summary')">
                <p class="mt-2 text-xs text-muted">{{ t('cart.discount_note') }}</p>
                <Link v-if="canCheckout" :href="lr('/thanh-toan')" class="btn btn-primary mt-4 w-full">{{ t('cart.checkout') }}<Icon name="arrowRight" :size="16" /></Link>
                <template v-else>
                    <p class="mt-4 rounded-lg bg-info-soft p-3 text-sm text-info" role="note">{{ isLoggedIn ? t('cart.cannot_checkout') : t('cart.login_to_checkout') }}</p>
                    <Link v-if="!isLoggedIn" :href="lr('/dang-nhap')" class="btn btn-primary mt-3 w-full">{{ t('nav.login') }}</Link>
                </template>
                <Link :href="lr('/ve')" class="btn btn-ghost mt-2 w-full">{{ t('cart.continue') }}</Link>
            </OrderSummary>
        </div>

        <ConfirmDialog
            :open="!!removing"
            :title="t('cart.remove_title')"
            :message="t('cart.remove_message', { name: removing?.package?.name || '' })"
            :confirm-text="t('common.delete')"
            danger
            @confirm="confirmRemove"
            @cancel="removing = null"
        />
    </div>
</template>
