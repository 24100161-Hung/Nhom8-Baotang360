<script setup>
import { ref, watch } from 'vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';

/** Hộp xác nhận kiểm duyệt có ô ghi chú; phát `confirm(note)`. */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmText: { type: String, default: '' },
    noteLabel: { type: String, default: '' },
    danger: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});
const emit = defineEmits(['update:open', 'confirm']);
const { t } = useT();
const note = ref('');
watch(() => props.open, (v) => v && (note.value = ''));
</script>

<template>
    <ConfirmDialog :open="open" :title="title" :message="message" :confirm-text="confirmText" :danger="danger" :processing="processing" @update:open="emit('update:open', $event)" @confirm="emit('confirm', note)">
        <FormField v-model="note" class="mt-3" type="textarea" :rows="3" :label="noteLabel || t('common.note')" />
    </ConfirmDialog>
</template>
