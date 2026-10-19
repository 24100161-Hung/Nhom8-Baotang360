<script setup>
import { computed } from 'vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Bảng so sánh quyền lợi các gói vé: gộp benefits của mọi gói, ✓/— theo từng gói. */
const props = defineProps({
    packages: { type: Array, default: () => [] },
});
const { t } = useT();
const { money } = useFormat();
const rows = computed(() => [...new Set(props.packages.flatMap((p) => p.benefits || []))]);
const has = (p, b) => (p.benefits || []).includes(b);
</script>

<template>
    <div v-if="packages.length && rows.length" class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-ds">
                <caption class="sr-only">{{ t('tickets.compare_title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ t('tickets.benefit') }}</th>
                        <th v-for="p in packages" :key="p.id" scope="col" class="text-center">{{ p.name }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in rows" :key="b">
                        <th scope="row" class="font-normal normal-case tracking-normal text-text">{{ b }}</th>
                        <td v-for="p in packages" :key="p.id" class="text-center">
                            <span v-if="has(p, b)" class="font-bold text-success" :aria-label="t('tickets.included')">✓</span>
                            <span v-else class="text-muted" :aria-label="t('tickets.not_included')">—</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="font-normal normal-case tracking-normal text-text">{{ t('tickets.price') }}</th>
                        <td v-for="p in packages" :key="p.id" class="text-center font-semibold">{{ money(p.price_vnd) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
