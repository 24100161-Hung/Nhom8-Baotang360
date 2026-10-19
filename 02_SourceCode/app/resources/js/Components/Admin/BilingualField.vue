<script setup>
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';

/** Cặp trường song ngữ vi/en (v-model:vi, v-model:en); bản tiếng Việt có thể bắt buộc. */
defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    rows: { type: Number, default: 3 },
    errorVi: { type: [String, Array], default: null },
    errorEn: { type: [String, Array], default: null },
    required: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    maxlength: { type: [String, Number], default: undefined },
});
const vi = defineModel('vi', { type: [String, null], default: '' });
const en = defineModel('en', { type: [String, null], default: '' });
const { t } = useT();
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <FormField v-model="vi" :type="type" :rows="rows" :label="`${label} (${t('admin.common.lang_vi')})`" :error="errorVi" :required="required" :maxlength="maxlength" :hint="hint" />
        <FormField v-model="en" :type="type" :rows="rows" :label="`${label} (${t('admin.common.lang_en')})`" :error="errorEn" :required="required" :maxlength="maxlength" :hint="hint" />
    </div>
</template>
