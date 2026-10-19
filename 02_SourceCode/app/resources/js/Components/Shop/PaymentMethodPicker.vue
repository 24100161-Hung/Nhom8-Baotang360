<script setup>
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/** Chọn phương thức thanh toán sandbox (card | bank | ewallet) dạng radio thẻ lớn. */
defineProps({
    methods: { type: Array, default: () => ['card', 'bank', 'ewallet'] },
    error: { type: String, default: null },
});
const model = defineModel({ type: String, default: 'card' });
const { t, te } = useT();
const ICONS = { card: 'ticket', bank: 'landmark', ewallet: 'phone' };
</script>

<template>
    <fieldset>
        <legend class="label">{{ t('checkout.payment_method') }}</legend>
        <div class="grid gap-2 sm:grid-cols-3">
            <label
                v-for="m in methods"
                :key="m"
                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm font-medium"
                :class="model === m ? 'border-primary bg-primary-soft text-primary' : 'border-border bg-surface'"
            >
                <input v-model="model" type="radio" name="payment_method" :value="m" required class="h-4 w-4 accent-[var(--ds-primary)]" />
                <Icon :name="ICONS[m] || 'ticket'" :size="18" />
                <span>{{ te('payment_method', m) }}</span>
            </label>
        </div>
        <p v-if="error" class="field-error" role="alert">{{ error }}</p>
    </fieldset>
</template>
