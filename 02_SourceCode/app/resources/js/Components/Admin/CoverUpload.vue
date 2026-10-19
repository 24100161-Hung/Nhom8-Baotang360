<script setup>
import { ref, computed, onBeforeUnmount, useId } from 'vue';
import { useT } from '@/Composables/useT';

/** Chọn ảnh bìa (jpg/png/webp) có xem trước; v-model = File|null. */
const props = defineProps({
    currentUrl: { type: String, default: null },
    error: { type: [String, Array], default: null },
    label: { type: String, default: '' },
});
const file = defineModel({ type: [Object, null], default: null });
const { t } = useT();
const id = `cover-${useId()}`;
const localUrl = ref(null);
const shown = computed(() => localUrl.value || props.currentUrl);

function onChange(e) {
    const f = e.target.files?.[0] || null;
    if (localUrl.value) URL.revokeObjectURL(localUrl.value);
    localUrl.value = f ? URL.createObjectURL(f) : null;
    file.value = f;
}
onBeforeUnmount(() => localUrl.value && URL.revokeObjectURL(localUrl.value));
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-[12rem_1fr] sm:items-start">
        <div class="aspect-[16/10] overflow-hidden rounded-lg border border-border bg-surface-2">
            <img v-if="shown" :src="shown" :alt="t('admin.common.cover_preview')" class="h-full w-full object-cover" />
        </div>
        <div>
            <label :for="id" class="label">{{ label || t('admin.common.cover') }}</label>
            <input :id="id" type="file" accept="image/jpeg,image/png,image/webp" class="input" :aria-describedby="`${id}-hint`" :aria-invalid="!!error" @change="onChange" />
            <p :id="`${id}-hint`" class="field-hint">{{ t('admin.common.cover_hint') }}</p>
            <p v-if="error" class="field-error" role="alert">{{ Array.isArray(error) ? error[0] : error }}</p>
        </div>
    </div>
</template>
