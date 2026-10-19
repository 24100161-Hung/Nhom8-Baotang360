<script setup>
import { ref, computed } from 'vue';
import FormField from '@/Components/FormField.vue';
import BilingualField from '@/Components/Admin/BilingualField.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PanoramaViewer from '@/Components/Viewer/PanoramaViewer.vue';
import { useT } from '@/Composables/useT';
import { entityOptions } from '@/Utils/options';
import { round2 } from '@/Components/Admin/options';

/**
 * Soạn các bước tour: chọn phòng (+ hotspot của phòng), góc nhìn pitch/yaw/hfov lấy từ khung xem trước,
 * thời gian dừng, tự chuyển, tiêu đề + lời dẫn vi/en; sắp xếp lên/xuống. v-model = mảng bước.
 */
const props = defineProps({
    scenes: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});
const steps = defineModel({ type: Array, default: () => [] });
const { t } = useT();
const active = ref(0);
const pano = ref(null);

const sceneOptions = computed(() => entityOptions(props.scenes));
const sceneOf = (id) => props.scenes.find((s) => String(s.id) === String(id)) || null;
const hotspotOptions = (id) => (sceneOf(id)?.hotspots || []).map((h) => ({ value: h.id, label: h.title || h.title_vi || `#${h.id}` }));
const activeScene = computed(() => {
    const sc = sceneOf(steps.value[active.value]?.scene_id);
    return sc && (sc.panorama || sc.preview_url) ? sc : null;
});
const err = (i, f) => props.errors[`steps.${i}.${f}`];
const hasErr = (i) => Object.keys(props.errors).some((k) => k.startsWith(`steps.${i}.`));

const blank = () => ({
    scene_id: props.scenes[0]?.id ?? '', hotspot_id: '', pitch: 0, yaw: 0, hfov: 100, dwell_seconds: 20, auto_advance: false,
    title_vi: '', title_en: '', narration_vi: '', narration_en: '',
});
function add() {
    steps.value = [...steps.value, blank()];
    active.value = steps.value.length - 1;
}
function remove(i) {
    steps.value = steps.value.filter((_, idx) => idx !== i);
    active.value = Math.max(0, Math.min(active.value, steps.value.length - 1));
}
function move(i, delta) {
    const j = i + delta;
    if (j < 0 || j >= steps.value.length) return;
    const next = [...steps.value];
    [next[i], next[j]] = [next[j], next[i]];
    steps.value = next;
    active.value = j;
}
function update(i, patch) {
    steps.value = steps.value.map((s, idx) => (idx === i ? { ...s, ...patch } : s));
}
function captureView(i) {
    const view = pano.value?.getView();
    if (view) update(i, { pitch: round2(view.pitch), yaw: round2(view.yaw), hfov: Math.round(view.hfov) });
}
function onHotspotPick(i, id) {
    const hs = sceneOf(steps.value[i].scene_id)?.hotspots?.find((h) => String(h.id) === String(id));
    update(i, hs ? { hotspot_id: id, pitch: round2(hs.pitch), yaw: round2(hs.yaw) } : { hotspot_id: id });
    if (hs) pano.value?.lookAt(hs.pitch, hs.yaw);
}
function preview(i) {
    active.value = i;
    const s = steps.value[i];
    window.setTimeout(() => pano.value?.lookAt(s.pitch, s.yaw, s.hfov), 50);
}
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div class="space-y-3">
            <EmptyState v-if="!steps.length" icon="route" :title="t('admin.steps.empty')" compact />
            <ol class="space-y-3">
                <li v-for="(step, i) in steps" :key="i" class="rounded-xl border p-3" :class="[active === i ? 'border-primary' : 'border-border', hasErr(i) ? 'border-danger' : '']">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="badge badge-primary">{{ t('admin.steps.step_n', { n: i + 1 }) }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold">{{ step.title_vi || sceneOf(step.scene_id)?.title || '' }}</span>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :aria-label="t('admin.steps.preview')" :title="t('admin.steps.preview')" @click="preview(i)"><Icon name="eye" :size="16" /></button>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="i === 0" :aria-label="t('admin.steps.move_up')" @click="move(i, -1)"><Icon name="arrowUp" :size="16" /></button>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon" :disabled="i === steps.length - 1" :aria-label="t('admin.steps.move_down')" @click="move(i, 1)"><Icon name="arrowDown" :size="16" /></button>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon text-danger" :aria-label="t('admin.steps.remove', { n: i + 1 })" @click="remove(i)"><Icon name="trash" :size="16" /></button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField :model-value="step.scene_id" type="select" :label="t('admin.steps.scene')" :options="sceneOptions" :placeholder="t('admin.form.choose')" :error="err(i, 'scene_id')" required @update:model-value="(v) => { update(i, { scene_id: v, hotspot_id: '' }); active = i; }" />
                        <FormField :model-value="step.hotspot_id" type="select" :label="t('admin.steps.hotspot')" :options="hotspotOptions(step.scene_id)" :placeholder="t('common.none')" :error="err(i, 'hotspot_id')" @update:model-value="(v) => onHotspotPick(i, v)" />
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <FormField :model-value="step.pitch" type="number" step="any" min="-90" max="90" :label="t('admin.hotspots.pitch')" :error="err(i, 'pitch')" @update:model-value="(v) => update(i, { pitch: v })" />
                        <FormField :model-value="step.yaw" type="number" step="any" min="-180" max="180" :label="t('admin.hotspots.yaw')" :error="err(i, 'yaw')" @update:model-value="(v) => update(i, { yaw: v })" />
                        <FormField :model-value="step.hfov" type="number" step="any" min="30" max="120" label="HFOV" :error="err(i, 'hfov')" @update:model-value="(v) => update(i, { hfov: v })" />
                        <FormField :model-value="step.dwell_seconds" type="number" min="0" max="3600" step="1" :label="t('admin.steps.dwell')" :error="err(i, 'dwell_seconds')" @update:model-value="(v) => update(i, { dwell_seconds: v })" />
                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" :checked="!!step.auto_advance" @change="update(i, { auto_advance: $event.target.checked })" />{{ t('admin.steps.auto_advance') }}
                            </label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm mt-2" :disabled="active !== i || !activeScene" @click="captureView(i)"><Icon name="camera" :size="16" />{{ t('admin.steps.capture') }}</button>
                    <div class="mt-3 space-y-3">
                        <BilingualField :vi="step.title_vi" :en="step.title_en" :label="t('admin.common.title')" :error-vi="err(i, 'title_vi')" :error-en="err(i, 'title_en')" required maxlength="190" @update:vi="(v) => update(i, { title_vi: v })" @update:en="(v) => update(i, { title_en: v })" />
                        <BilingualField :vi="step.narration_vi" :en="step.narration_en" type="textarea" :rows="3" :label="t('admin.steps.narration')" :error-vi="err(i, 'narration_vi')" :error-en="err(i, 'narration_en')" maxlength="5000" @update:vi="(v) => update(i, { narration_vi: v })" @update:en="(v) => update(i, { narration_en: v })" />
                    </div>
                </li>
            </ol>
            <button type="button" class="btn" :disabled="!scenes.length" @click="add"><Icon name="plus" :size="18" />{{ t('admin.steps.add') }}</button>
            <p v-if="errors.steps" class="field-error" role="alert">{{ errors.steps }}</p>
        </div>
        <aside class="xl:sticky xl:top-20 xl:self-start">
            <p class="label">{{ t('admin.steps.preview_title', { n: steps.length ? active + 1 : 0 }) }}</p>
            <div class="h-64 overflow-hidden rounded-lg border border-border sm:h-80">
                <PanoramaViewer v-if="activeScene" ref="pano" :key="activeScene.id" :scene="activeScene" :initial-view="steps[active] ? { pitch: steps[active].pitch, yaw: steps[active].yaw, hfov: steps[active].hfov } : null" :highlight-hotspot-id="steps[active]?.hotspot_id || null" preview compact :auto-rotate="false" />
                <EmptyState v-else icon="panorama" :title="t('admin.steps.no_preview')" compact class="h-full" />
            </div>
            <p class="field-hint">{{ t('admin.steps.preview_hint') }}</p>
        </aside>
    </div>
</template>
