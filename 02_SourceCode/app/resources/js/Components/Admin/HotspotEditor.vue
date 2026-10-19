<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import HotspotForm from '@/Components/Admin/HotspotForm.vue';
import PanoramaViewer from '@/Components/Viewer/PanoramaViewer.vue';
import { useT } from '@/Composables/useT';
import { round2 } from '@/Components/Admin/options';

/**
 * Soạn hotspot trên ảnh equirectangular: bấm vào ảnh → yaw = (x/width)*360 − 180, pitch = 90 − (y/height)*180;
 * kéo thả điểm để chỉnh, phím mũi tên dịch 1°. Xem trước nhanh bằng PanoramaViewer thu nhỏ.
 */
const props = defineProps({
    scene: { type: Object, required: true },
    scenes: { type: Array, default: () => [] },
    artifacts: { type: Array, default: () => [] },
});
const { t, te } = useT();

const imageUrl = computed(() => props.scene.panorama?.fallback_url || props.scene.panorama_url || props.scene.preview_url || null);
const hotspots = computed(() => props.scene.hotspots || []);
const selected = ref(null); // hotspot đang sửa (bản sao) hoặc bản nháp mới {id: null}
const position = ref({ pitch: 0, yaw: 0 });
const draftTitle = ref('');
const stage = ref(null);
const pano = ref(null);
const dragging = ref(false);
const formKey = ref(0);

const toPercent = (hs) => ({ left: `${((Number(hs.yaw) + 180) / 360) * 100}%`, top: `${((90 - Number(hs.pitch)) / 180) * 100}%` });

/** Tọa độ con trỏ trên ảnh → pitch/yaw (giới hạn trong khung). */
function coordsOf(e) {
    const rect = stage.value.getBoundingClientRect();
    const x = Math.min(Math.max(e.clientX - rect.left, 0), rect.width);
    const y = Math.min(Math.max(e.clientY - rect.top, 0), rect.height);
    return { yaw: round2((x / rect.width) * 360 - 180), pitch: round2(90 - (y / rect.height) * 180) };
}

function select(hs) {
    selected.value = { ...hs };
    position.value = { pitch: Number(hs.pitch) || 0, yaw: Number(hs.yaw) || 0 };
    draftTitle.value = hs.title_vi || hs.title || '';
    formKey.value += 1;
    nextTick(() => pano.value?.lookAt(position.value.pitch, position.value.yaw));
}
function onStageClick(e) {
    if (dragging.value || e.target.closest('[data-marker]')) return;
    select({ id: null, type: 'info', ...coordsOf(e) });
}
function startDrag(hs, e) {
    if (selected.value?.id !== hs.id) select(hs);
    dragging.value = true;
    e.target.setPointerCapture?.(e.pointerId);
}
function onMove(e) {
    if (dragging.value) position.value = coordsOf(e);
}
function endDrag() {
    if (!dragging.value) return;
    window.setTimeout(() => (dragging.value = false), 0);
    pano.value?.lookAt(position.value.pitch, position.value.yaw);
}
function nudge(e) {
    const step = { ArrowLeft: [0, -1], ArrowRight: [0, 1], ArrowUp: [1, 0], ArrowDown: [-1, 0] }[e.key];
    if (!step || !selected.value) return;
    e.preventDefault();
    const pitch = Math.min(90, Math.max(-90, position.value.pitch + step[0]));
    let yaw = position.value.yaw + step[1];
    if (yaw > 180) yaw -= 360;
    if (yaw < -180) yaw += 360;
    position.value = { pitch: round2(pitch), yaw: round2(yaw) };
}
function onMarkerClick(hs) {
    if (!isSelected(hs)) select(hs);
}
function close() {
    selected.value = null;
}

// Cảnh xem trước: hotspot đã lưu + vị trí đang chỉnh của hotspot được chọn
const previewScene = computed(() => {
    const sel = selected.value;
    const list = hotspots.value.filter((h) => !sel || h.id !== sel.id);
    const live = sel ? [{ ...sel, id: sel.id ?? 'draft', title: draftTitle.value || t('admin.hotspots.new'), pitch: position.value.pitch, yaw: position.value.yaw, target: null }] : [];
    return { ...props.scene, hotspots: [...list, ...live] };
});
const markers = computed(() => previewScene.value.hotspots);
const isSelected = (hs) => selected.value && (hs.id === selected.value.id || (hs.id === 'draft' && !selected.value.id));

watch(() => props.scene.hotspots, () => {
    if (selected.value?.id === null) return;
    if (selected.value && !hotspots.value.some((h) => h.id === selected.value.id)) close();
});
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted">{{ t('admin.hotspots.help') }}</p>
        <div v-if="imageUrl" class="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div>
                <div
                    ref="stage"
                    class="relative w-full cursor-crosshair select-none overflow-hidden rounded-lg border border-border bg-surface-2"
                    style="aspect-ratio: 2 / 1; touch-action: none"
                    @click="onStageClick"
                    @pointermove="onMove"
                    @pointerup="endDrag"
                    @pointercancel="endDrag"
                >
                    <img :src="imageUrl" :alt="t('admin.hotspots.image_alt', { name: scene.title || scene.slug })" class="pointer-events-none h-full w-full object-cover" draggable="false" />
                    <button
                        v-for="hs in markers"
                        :key="hs.id"
                        type="button"
                        data-marker
                        class="hs-marker absolute flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-white text-white shadow"
                        :class="[`is-${hs.type}`, isSelected(hs) ? 'is-active' : '']"
                        :style="isSelected(hs) ? toPercent(position) : toPercent(hs)"
                        :aria-label="`${te('hotspot_type', hs.type)}: ${hs.title || hs.title_vi || '#' + hs.id}`"
                        :aria-pressed="isSelected(hs)"
                        @pointerdown.stop="startDrag(hs, $event)"
                        @click.stop="onMarkerClick(hs)"
                        @keydown="nudge"
                    >
                        <Icon :name="hs.type === 'scene' ? 'arrowUp' : hs.type === 'artifact' ? 'bone' : 'info'" :size="14" :stroke-width="2.4" />
                    </button>
                </div>
                <p class="mt-1 text-xs text-muted">{{ t('admin.hotspots.coords', { pitch: position.pitch, yaw: position.yaw }) }}</p>
            </div>
            <div class="h-64 overflow-hidden rounded-lg border border-border xl:h-auto xl:min-h-64">
                <PanoramaViewer ref="pano" :scene="previewScene" :highlight-hotspot-id="selected ? selected.id ?? 'draft' : null" preview compact :auto-rotate="false" @hotspot="(h) => h.id !== 'draft' && select(hotspots.find((x) => x.id === h.id) || h)" />
            </div>
        </div>
        <EmptyState v-else icon="image" :title="t('admin.hotspots.no_image')" :description="t('admin.hotspots.no_image_hint')" compact />

        <section v-if="selected" class="rounded-xl border border-primary p-4" :aria-label="t('admin.hotspots.form_title')">
            <h3 class="mb-3 font-body text-base font-bold">{{ selected.id ? t('admin.hotspots.edit', { name: selected.title_vi || selected.title || `#${selected.id}` }) : t('admin.hotspots.new') }}</h3>
            <HotspotForm
                :key="formKey"
                v-model:position="position"
                :scene-id="scene.id"
                :hotspot="selected"
                :scenes="scenes"
                :artifacts="artifacts"
                @change="(d) => { draftTitle = d.title_vi; selected = { ...selected, type: d.type }; }"
                @saved="close"
                @cancel="close"
            />
        </section>

        <div>
            <h3 class="mb-2 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('admin.hotspots.list', { n: hotspots.length }) }}</h3>
            <p v-if="!hotspots.length" class="text-sm text-muted">{{ t('admin.hotspots.empty') }}</p>
            <ul v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="hs in hotspots" :key="hs.id">
                    <button type="button" class="flex w-full items-start gap-2 rounded-lg border border-border p-2 text-left text-sm hover:bg-surface-2" :class="selected?.id === hs.id ? 'border-primary bg-primary-soft' : ''" @click="select(hs)">
                        <span class="badge">{{ te('hotspot_type', hs.type) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ hs.title_vi || hs.title || `#${hs.id}` }}</span>
                            <span class="text-xs text-muted">{{ t('admin.hotspots.coords', { pitch: hs.pitch, yaw: hs.yaw }) }}<template v-if="hs.target"> → {{ hs.target.title }}</template><template v-if="hs.artifact"> · {{ hs.artifact.title }}</template></span>
                        </span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.hs-marker { background: var(--ds-info); cursor: grab; }
.hs-marker.is-scene { background: var(--ds-primary); }
.hs-marker.is-artifact { background: var(--ds-accent-strong); }
.hs-marker.is-active { outline: 3px solid var(--ds-accent); outline-offset: 2px; z-index: 2; cursor: grabbing; }
</style>
