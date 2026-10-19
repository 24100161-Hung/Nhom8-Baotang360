<script setup>
import { ref, computed, useId } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { renderMarkdown } from '@/Components/Admin/markdown';

/** Soạn markdown đơn giản: thanh công cụ chèn cú pháp + tab xem trước (render an toàn, không thư viện). */
const props = defineProps({
    label: { type: String, required: true },
    error: { type: [String, Array], default: null },
    rows: { type: Number, default: 14 },
    required: { type: Boolean, default: false },
    maxlength: { type: [String, Number], default: undefined },
});
const model = defineModel({ type: [String, null], default: '' });
const { t } = useT();
const id = `md-${useId()}`;
const area = ref(null);
const tab = ref('write');
const html = computed(() => renderMarkdown(model.value));

const TOOLS = [
    { key: 'bold', label: 'B', wrap: ['**', '**'] },
    { key: 'italic', label: 'I', wrap: ['*', '*'] },
    { key: 'heading', label: 'H', line: '## ' },
    { key: 'list', icon: 'list', line: '- ' },
    { key: 'quote', label: '❝', line: '> ' },
    { key: 'link', icon: 'external', wrap: ['[', '](https://)'] },
];

/** Chèn cú pháp quanh vùng chọn (wrap) hoặc đầu mỗi dòng được chọn (line). */
function apply(tool) {
    const el = area.value;
    if (!el) return;
    const value = model.value || '';
    const start = el.selectionStart;
    const end = el.selectionEnd;
    const sel = value.slice(start, end) || t(`admin.md.sample_${tool.key}`);
    const text = tool.wrap
        ? `${tool.wrap[0]}${sel}${tool.wrap[1]}`
        : sel.split('\n').map((l) => `${tool.line}${l}`).join('\n');
    model.value = value.slice(0, start) + text + value.slice(end);
    requestAnimationFrame(() => {
        el.focus();
        el.setSelectionRange(start, start + text.length);
    });
}
const errorText = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error));
</script>

<template>
    <div>
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
            <label :for="id" class="label mb-0">{{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span></label>
            <div class="flex gap-1" role="tablist" :aria-label="label">
                <button type="button" role="tab" class="tab py-1" :aria-selected="tab === 'write'" @click="tab = 'write'">{{ t('admin.md.write') }}</button>
                <button type="button" role="tab" class="tab py-1" :aria-selected="tab === 'preview'" @click="tab = 'preview'">{{ t('admin.md.preview') }}</button>
            </div>
        </div>
        <div v-show="tab === 'write'">
            <div class="flex flex-wrap gap-1 rounded-t-lg border border-b-0 border-border bg-surface-2 p-1" role="toolbar" :aria-label="t('admin.md.toolbar')">
                <button v-for="tool in TOOLS" :key="tool.key" type="button" class="btn btn-ghost btn-sm min-w-8 px-2" :aria-label="t(`admin.md.${tool.key}`)" :title="t(`admin.md.${tool.key}`)" @click="apply(tool)">
                    <Icon v-if="tool.icon" :name="tool.icon" :size="16" />
                    <span v-else :class="tool.key === 'italic' ? 'italic' : 'font-bold'">{{ tool.label }}</span>
                </button>
            </div>
            <textarea :id="id" ref="area" v-model="model" class="input rounded-t-none font-mono text-sm" :rows="rows" :required="required" :maxlength="maxlength" :aria-invalid="!!errorText" :aria-describedby="`${id}-hint`"></textarea>
            <p :id="`${id}-hint`" class="field-hint">{{ t('admin.md.hint') }}</p>
        </div>
        <div v-show="tab === 'preview'" class="prose-museum min-h-40 rounded-lg border border-border bg-surface p-4" role="tabpanel">
            <div v-if="html" v-html="html"></div>
            <p v-else class="text-sm text-muted">{{ t('admin.md.empty') }}</p>
        </div>
        <p v-if="errorText" class="field-error" role="alert">{{ errorText }}</p>
    </div>
</template>
