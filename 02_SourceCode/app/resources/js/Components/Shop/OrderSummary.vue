<script setup>
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Tóm tắt dòng vé + tạm tính, giảm hội viên, tổng (giỏ hàng, thanh toán, đơn hàng). */
defineProps({
    items: { type: Array, default: () => [] },
    subtotal: { type: Number, default: 0 },
    discount: { type: Number, default: 0 },
    total: { type: Number, default: null },
    title: { type: String, default: '' },
});
const { t } = useT();
const { money, date } = useFormat();
const nameOf = (it) => it.package?.name || it.package_name || '';
</script>

<template>
    <section class="card p-4 md:p-5" :aria-label="title || t('checkout.summary')">
        <h2 class="font-body text-lg font-bold">{{ title || t('checkout.summary') }}</h2>
        <ul class="mt-3 divide-y divide-border text-sm">
            <li v-for="(it, i) in items" :key="it.id ?? i" class="flex items-start justify-between gap-3 py-2">
                <div class="min-w-0">
                    <p class="font-semibold">{{ nameOf(it) }} × {{ it.quantity }}</p>
                    <p class="text-xs text-muted">{{ it.visit_date ? date(it.visit_date) : t('cart.no_date') }} · {{ money(it.unit_price_vnd) }}</p>
                </div>
                <span class="shrink-0 font-semibold tabular-nums">{{ money(it.line_total_vnd) }}</span>
            </li>
        </ul>
        <dl class="mt-3 space-y-1 border-t border-border pt-3 text-sm">
            <div class="flex justify-between gap-2"><dt class="text-muted">{{ t('checkout.subtotal') }}</dt><dd class="tabular-nums">{{ money(subtotal) }}</dd></div>
            <div v-if="discount" class="flex justify-between gap-2 text-success"><dt>{{ t('checkout.member_discount') }}</dt><dd class="tabular-nums">−{{ money(discount) }}</dd></div>
            <div class="flex items-baseline justify-between gap-2 pt-1">
                <dt class="font-semibold">{{ t('checkout.total') }}</dt>
                <dd class="font-display text-2xl font-bold text-primary tabular-nums">{{ money(total ?? subtotal - discount) }}</dd>
            </div>
        </dl>
        <slot />
    </section>
</template>
