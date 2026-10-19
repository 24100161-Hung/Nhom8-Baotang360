<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import BilingualField from '@/Components/Admin/BilingualField.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { entityOptions } from '@/Utils/options';
import { HOTSPOT_TYPES, codeOptions, numOrNull, round2 } from '@/Components/Admin/options';

/** Form một hotspot (tạo/sửa). Vị trí pitch/yaw đồng bộ hai chiều với HotspotEditor qua v-model:position. */
const props = defineProps({
    sceneId: { type: Number, required: true },
    hotspot: { type: Object, required: true }, // {id?|null, ...}
    scenes: { type: Array, default: () => [] },
    artifacts: { type: Array, default: () => [] },
});
const position = defineModel('position', { type: Object, default: () => ({ pitch: 0, yaw: 0 }) });
const emit = defineEmits(['saved', 'cancel', 'change']);
const { t, te } = useT();
const lr = useLocaleRoute();

const h = props.hotspot;
const form = useForm({
    type: h.type || 'info',
    pitch: round2(h.pitch ?? 0),
    yaw: round2(h.yaw ?? 0),
    target_scene_id: h.target_scene_id ?? h.target?.scene_id ?? '',
    target_pitch: h.target_pitch ?? h.target?.pitch ?? '',
    target_yaw: h.target_yaw ?? h.target?.yaw ?? '',
    target_hfov: h.target_hfov ?? h.target?.hfov ?? '',
    artifact_id: h.artifact_id ?? h.artifact?.id ?? '',
    icon: h.icon || '',
    title_vi: h.title_vi ?? h.title ?? '',
    title_en: h.title_en ?? '',
    text_vi: h.text_vi ?? h.text ?? '',
    text_en: h.text_en ?? '',
    sort_order: h.sort_order ?? 0,
});
const isEdit = computed(() => !!h.id);
const base = computed(() => lr(`/quan-tri/khong-gian-360/${props.sceneId}/hotspot`));
const targetOptions = computed(() => entityOptions(props.scenes.filter((s) => s.id !== props.sceneId)));
const artifactOptions = computed(() => entityOptions(props.artifacts));

// Đồng bộ vị trí: kéo trên ảnh → form; sửa ô số → ảnh
watch(position, (p) => {
    form.pitch = round2(p.pitch);
    form.yaw = round2(p.yaw);
}, { deep: true });
watch(() => [form.pitch, form.yaw], ([pitch, yaw]) => {
    if (Number(pitch) !== position.value.pitch || Number(yaw) !== position.value.yaw) position.value = { pitch: Number(pitch) || 0, yaw: Number(yaw) || 0 };
});
watch(() => ({ ...form.data() }), (d) => emit('change', d), { deep: true });

function submit() {
    form.transform((d) => ({
        ...d,
        target_scene_id: d.type === 'scene' ? numOrNull(d.target_scene_id) : null,
        target_pitch: d.type === 'scene' ? numOrNull(d.target_pitch) : null,
        target_yaw: d.type === 'scene' ? numOrNull(d.target_yaw) : null,
        target_hfov: d.type === 'scene' ? numOrNull(d.target_hfov) : null,
        artifact_id: numOrNull(d.artifact_id),
        icon: d.icon || null,
    }));
    const opts = { preserveScroll: true, onSuccess: () => emit('saved') };
    if (isEdit.value) form.put(`${base.value}/${h.id}`, opts);
    else form.post(base.value, opts);
}
</script>

<template>
    <form class="space-y-3" @submit.prevent="submit">
        <div class="grid gap-3 sm:grid-cols-3">
            <FormField v-model="form.type" type="select" :label="t('common.type')" :options="codeOptions(HOTSPOT_TYPES, te, 'hotspot_type')" :error="form.errors.type" required />
            <FormField v-model="form.pitch" type="number" step="any" min="-90" max="90" :label="t('admin.hotspots.pitch')" :error="form.errors.pitch" required />
            <FormField v-model="form.yaw" type="number" step="any" min="-180" max="180" :label="t('admin.hotspots.yaw')" :error="form.errors.yaw" required />
        </div>
        <BilingualField v-model:vi="form.title_vi" v-model:en="form.title_en" :label="t('admin.common.title')" :error-vi="form.errors.title_vi" :error-en="form.errors.title_en" required />
        <BilingualField v-model:vi="form.text_vi" v-model:en="form.text_en" type="textarea" :rows="3" :label="t('admin.hotspots.text')" :error-vi="form.errors.text_vi" :error-en="form.errors.text_en" />

        <div v-if="form.type === 'scene'" class="grid gap-3 rounded-lg bg-surface-2 p-3 sm:grid-cols-4">
            <FormField v-model="form.target_scene_id" class="sm:col-span-4" type="select" :label="t('admin.hotspots.target_scene')" :options="targetOptions" :placeholder="t('admin.form.choose')" :error="form.errors.target_scene_id" required />
            <FormField v-model="form.target_pitch" type="number" step="any" min="-90" max="90" :label="t('admin.hotspots.target_pitch')" :error="form.errors.target_pitch" />
            <FormField v-model="form.target_yaw" type="number" step="any" min="-180" max="180" :label="t('admin.hotspots.target_yaw')" :error="form.errors.target_yaw" />
            <FormField v-model="form.target_hfov" type="number" step="any" min="30" max="120" :label="t('admin.hotspots.target_hfov')" :error="form.errors.target_hfov" />
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <FormField v-if="artifactOptions.length" v-model="form.artifact_id" type="select" class="sm:col-span-2" :label="t('admin.hotspots.artifact')" :options="artifactOptions" :placeholder="t('common.none')" :required="form.type === 'artifact'" :error="form.errors.artifact_id" />
            <FormField v-else v-model="form.artifact_id" type="number" min="1" step="1" class="sm:col-span-2" :label="t('admin.hotspots.artifact_id')" :required="form.type === 'artifact'" :error="form.errors.artifact_id" />
            <FormField v-model="form.sort_order" type="number" min="0" max="1000" step="1" :label="t('admin.common.sort_order')" :error="form.errors.sort_order" />
            <FormField v-model="form.icon" class="sm:col-span-3" :label="t('admin.hotspots.icon')" :hint="t('admin.hotspots.icon_hint')" :error="form.errors.icon" maxlength="40" pattern="[a-z0-9_\-]+" :title="t('admin.hotspots.icon_hint')" />
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <DeleteAction v-if="isEdit" :url="`${base}/${h.id}`" :name="form.title_vi || `#${h.id}`" :small="false" />
            <button type="button" class="btn" @click="emit('cancel')">{{ t('common.cancel') }}</button>
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ form.processing ? t('common.saving') : isEdit ? t('common.save') : t('admin.hotspots.add') }}</button>
        </div>
    </form>
</template>
