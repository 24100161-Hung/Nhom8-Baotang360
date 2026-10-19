<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Thẻ gói vé (PackageItem): giá, thời hạn, quyền lợi; slot `actions` cho nút mua/chọn. */
defineProps({
    pkg: { type: Object, required: true },
    headingLevel: { type: Number, default: 3 },
    selected: { type: Boolean, default: false },
});
const { t } = useT();
const { money, number } = useFormat();
</script>

<template>
    <article
        class="card relative flex h-full flex-col p-5"
        :class="[pkg.is_featured ? 'border-2 border-accent' : '', selected ? 'ring-2 ring-primary' : '']"
    >
        <span v-if="pkg.is_featured" class="badge badge-accent absolute -top-3 left-5 shadow">{{ t('tickets.recommended') }}</span>
        <p class="eyebrow">{{ pkg.package_type === 'membership' ? t('tickets.type_membership') : t('tickets.type_single') }}</p>
        <component :is="`h${headingLevel}`" class="mt-1 text-2xl font-bold">{{ pkg.name }}</component>
        <p class="mt-1 text-sm text-muted">{{ pkg.description }}</p>
        <p class="mt-4 flex items-baseline gap-1">
            <span class="font-display text-3xl font-bold text-primary">{{ money(pkg.price_vnd) }}</span>
            <span class="text-sm text-muted">/ {{ pkg.package_type === 'membership' ? t('tickets.per_year') : t('tickets.per_visit') }}</span>
        </p>
        <p class="mt-1 text-xs text-muted">{{ t('tickets.validity', { n: number(pkg.validity_days) }) }}</p>
        <ul class="mt-4 space-y-2 text-sm">
            <li v-for="(b, i) in pkg.benefits || []" :key="i" class="flex items-start gap-2">
                <Icon name="check" :size="16" class="mt-0.5 text-success" />{{ b }}
            </li>
        </ul>
        <div v-if="$slots.actions" class="mt-auto pt-5"><slot name="actions" /></div>
    </article>
</template>
