<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { buildSceneConfig, sceneKey, hotspotConfig, prefersReducedMotion } from '@/Components/Viewer/panoramaConfig';

/**
 * Khung xem 360° dùng Pannellum 2.5.7 (nạp động, chunk riêng). Cấu hình multires từ tile_config,
 * tự chuyển sang equirectangular (fallback_url) khi tiles chưa sẵn sàng hoặc lỗi.
 * Hotspot vẽ bằng createTooltipFunc (nút có nhãn, bấm được bằng bàn phím);
 * đổi scene bằng addScene + loadScene có hiệu ứng mờ dần.
 */
const props = defineProps({
    scene: { type: Object, required: true },
    // Góc nhìn mong muốn khi vào scene (từ hotspot đích hoặc bước tour); null → mặc định của scene
    initialView: { type: Object, default: null },
    highlightHotspotId: { type: [Number, String], default: null },
    autoRotate: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
    showControls: { type: Boolean, default: true },
    // Phần tử đưa vào toàn màn hình (cả lớp phủ MiniMap/tour); mặc định là chính khung xem
    fullscreenTarget: { type: null, default: null },
    // Admin xem trước: không xử lý hotspot đích, chỉ phát sự kiện
    preview: { type: Boolean, default: false },
});
const emit = defineEmits(['view-change', 'hotspot', 'navigate', 'ready', 'error', 'interact']);
const { t } = useT();

const root = ref(null);
const stage = ref(null);
const status = ref('loading'); // loading | ready | error
const errorText = ref('');
const isFullscreen = ref(false);
const view = ref({ pitch: 0, yaw: 0, hfov: 100 });
const usingFallback = ref(false);

let viewer = null;
let currentKey = null;
let rafId = null;
let lastEmit = { pitch: null, yaw: null, hfov: null };
const hotspotEls = new Map();

const ROTATE_SPEED = -2;
const ZOOM_STEP = 10;
const CHANGE_EPSILON = 0.3;

const northOffset = computed(() => Number(props.scene?.north_offset || 0));
const compassAngle = computed(() => -(view.value.yaw - northOffset.value));

// Pannellum chỉ nạp khi trang thực sự cần (Promise dùng chung cho mọi khung xem)
let pannellumPromise = null;
function loadPannellum() {
    pannellumPromise ??= Promise.all([
        import('pannellum/build/pannellum.js'),
        import('pannellum/build/pannellum.css'),
    ]).then(() => window.pannellum);
    return pannellumPromise;
}

function tooltipHandlers() {
    return {
        onCreate: (id, el) => {
            hotspotEls.set(String(id), el);
            el.classList.toggle('bt-hs--pulse', String(id) === String(props.highlightHotspotId ?? ''));
        },
        onClick: (hs) => onHotspot(hs),
        labels: { info: t('viewer.hotspot_info'), artifact: t('viewer.hotspot_artifact'), scene: t('viewer.hotspot_scene') },
    };
}

function configFor(scene, viewOverride, forceFallback = false) {
    const cfg = buildSceneConfig(scene, viewOverride, tooltipHandlers(), forceFallback);
    usingFallback.value = cfg.type === 'equirectangular';
    return cfg;
}

function onHotspot(hs) {
    emit('interact');
    emit('hotspot', hs);
    if (hs.type === 'scene' && hs.target && !props.preview) emit('navigate', hs.target, hs);
}

/** Đọc góc nhìn mỗi khung hình, chỉ phát sự kiện khi thay đổi đáng kể (MiniMap xoay nón FOV). */
function pollView() {
    // Multires có thể bắn 'load' đồng bộ ngay trong constructor (trước khi gắn handler) → dò isLoaded()
    if (viewer && status.value === 'loading' && typeof viewer.isLoaded === 'function' && viewer.isLoaded()) markReady();
    if (viewer && status.value === 'ready') {
        const next = { pitch: viewer.getPitch(), yaw: viewer.getYaw(), hfov: viewer.getHfov() };
        const changed = ['pitch', 'yaw', 'hfov'].some((k) => lastEmit[k] === null || Math.abs(next[k] - lastEmit[k]) > CHANGE_EPSILON);
        if (changed) {
            lastEmit = next;
            view.value = next;
            emit('view-change', next);
        }
    }
    rafId = window.requestAnimationFrame(pollView);
}

/** Đánh dấu panorama đã sẵn sàng (ẩn lớp phủ đang tải), chỉ phát 'ready' một lần mỗi lần tải. */
function markReady() {
    if (status.value === 'ready') return;
    status.value = 'ready';
    errorText.value = '';
    emit('ready', { sceneId: props.scene.id });
}

function bindEvents() {
    viewer.on('load', markReady);
    viewer.on('scenechange', () => {
        if (viewer?.isLoaded?.()) markReady();
    });
    viewer.on('error', (msg) => handleError(msg));
    viewer.on('mousedown', () => emit('interact'));
    viewer.on('touchstart', () => emit('interact'));
    viewer.on('fullscreenchange', (active) => (isFullscreen.value = active));
}

async function create(forceFallback = false) {
    status.value = 'loading';
    let pannellum;
    try {
        pannellum = await loadPannellum();
    } catch {
        return handleError(t('viewer.load_failed'));
    }
    if (!stage.value) return;
    destroy();
    currentKey = sceneKey(props.scene);
    viewer = pannellum.viewer(stage.value, {
        default: {
            firstScene: currentKey,
            sceneFadeDuration: prefersReducedMotion() ? 0 : 800,
            autoLoad: true,
            showControls: false,
            compass: false,
            keyboardZoom: true,
            mouseZoom: true,
            draggable: true,
            friction: 0.15,
            autoRotate: props.autoRotate && !prefersReducedMotion() && !props.compact ? ROTATE_SPEED : false,
            strings: { loadingLabel: t('viewer.loading'), bylineLabel: '' },
        },
        scenes: { [currentKey]: configFor(props.scene, props.initialView, forceFallback) },
    });
    bindEvents();
}

function handleError(msg) {
    // Tiles đa phân giải lỗi (chưa sinh xong/thiếu file) → thử lại bằng ảnh equirectangular
    if (!usingFallback.value && props.scene?.panorama?.fallback_url) {
        create(true);
        return;
    }
    status.value = 'error';
    errorText.value = typeof msg === 'string' && msg ? msg : t('viewer.load_failed');
    emit('error', errorText.value);
}

/** Chuyển sang scene mới trong cùng một viewer (mờ dần), thêm scene nếu chưa có. */
function switchScene(scene, targetView) {
    if (!viewer) return create();
    const key = sceneKey(scene);
    hotspotEls.clear();
    if (key !== currentKey) {
        try {
            viewer.removeScene(key);
        } catch {
            // scene chưa từng được thêm: bỏ qua
        }
    }
    viewer.addScene(key, configFor(scene, targetView));
    status.value = 'loading';
    // Góc nhìn đích đã nằm trong cấu hình scene (pitch/yaw/hfov)
    viewer.loadScene(key);
    currentKey = key;
}

/** Cập nhật hotspot của scene hiện tại (đổi ngôn ngữ, admin sửa hotspot) mà không tải lại ảnh. */
function refreshHotspots(scene) {
    if (!viewer || status.value !== 'ready') return;
    const handlers = tooltipHandlers();
    (viewer.getConfig().hotSpots || []).map((h) => h.id).forEach((id) => viewer.removeHotSpot(id));
    hotspotEls.clear();
    (scene.hotspots || []).forEach((hs) => viewer.addHotSpot(hotspotConfig(hs, handlers)));
}

function destroy() {
    hotspotEls.clear();
    if (viewer) {
        try {
            viewer.destroy();
        } catch {
            // viewer đã bị hủy
        }
    }
    viewer = null;
}

// ----- API cho trang cha (tour có hướng dẫn, StepsEditor) -----
function lookAt(pitch, yaw, hfov, animated = true) {
    if (!viewer) return;
    viewer.stopAutoRotate();
    const ms = animated && !prefersReducedMotion() ? 1000 : 0;
    viewer.lookAt(Number(pitch ?? viewer.getPitch()), Number(yaw ?? viewer.getYaw()), Number(hfov ?? viewer.getHfov()), ms);
}
function getView() {
    return viewer ? { pitch: viewer.getPitch(), yaw: viewer.getYaw(), hfov: viewer.getHfov() } : { ...view.value };
}
function zoom(delta) {
    if (!viewer) return;
    emit('interact');
    viewer.setHfov(viewer.getHfov() + delta, prefersReducedMotion() ? false : 300);
}
function resetNorth() {
    if (!viewer) return;
    emit('interact');
    viewer.setYaw(northOffset.value, prefersReducedMotion() ? false : 600);
}
function stopRotate() {
    viewer?.stopAutoRotate();
}
defineExpose({ lookAt, getView, stopRotate });

// ----- Toàn màn hình: đưa cả lớp phủ vào fullscreen -----
function fsElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
}
async function toggleFullscreen() {
    const target = props.fullscreenTarget || root.value;
    try {
        if (fsElement()) await (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        else await (target.requestFullscreen || target.webkitRequestFullscreen).call(target);
    } catch {
        // Trình duyệt (iOS Safari) không hỗ trợ fullscreen cho phần tử: bỏ qua
    }
}
function onFsChange() {
    isFullscreen.value = !!fsElement();
}
const fullscreenSupported = computed(() => typeof document !== 'undefined' && !!(document.fullscreenEnabled || document.webkitFullscreenEnabled));

function retry() {
    usingFallback.value = false;
    create();
}

watch(
    () => props.scene?.id,
    (id, oldId) => {
        if (id && id !== oldId) switchScene(props.scene, props.initialView);
    },
);
// Hotspot thay đổi trong cùng scene (đổi ngôn ngữ / admin lưu hotspot)
watch(
    () => JSON.stringify((props.scene?.hotspots || []).map((h) => [h.id, h.title, h.pitch, h.yaw, h.type])),
    (sig, old) => {
        if (old !== undefined && sig !== old && sceneKey(props.scene) === currentKey) refreshHotspots(props.scene);
    },
);
watch(
    () => props.highlightHotspotId,
    (id) => hotspotEls.forEach((el, key) => el.classList.toggle('bt-hs--pulse', key === String(id ?? ''))),
);

onMounted(async () => {
    document.addEventListener('fullscreenchange', onFsChange);
    document.addEventListener('webkitfullscreenchange', onFsChange);
    await nextTick();
    create();
    rafId = window.requestAnimationFrame(pollView);
});
onBeforeUnmount(() => {
    window.cancelAnimationFrame(rafId);
    document.removeEventListener('fullscreenchange', onFsChange);
    document.removeEventListener('webkitfullscreenchange', onFsChange);
    destroy();
});
</script>

<template>
    <div ref="root" class="bt-pano relative h-full w-full overflow-hidden bg-viewer-bg text-viewer-text">
        <div
            ref="stage"
            class="h-full w-full"
            tabindex="0"
            role="application"
            :aria-roledescription="t('viewer.roledescription')"
            :aria-label="t('viewer.aria', { title: scene.title })"
            aria-describedby="pano-help"
        ></div>
        <p id="pano-help" class="sr-only">{{ t('viewer.keyboard_help') }}</p>

        <div v-if="status === 'loading'" class="pointer-events-none absolute inset-0 flex items-center justify-center" role="status">
            <span class="flex items-center gap-2 rounded-full bg-viewer-panel px-4 py-2 text-sm">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-viewer-muted border-t-transparent" aria-hidden="true"></span>
                {{ t('viewer.loading') }}
            </span>
        </div>

        <div v-if="status === 'error'" class="absolute inset-0 flex items-center justify-center p-4" role="alert">
            <div class="max-w-sm rounded-xl bg-viewer-panel p-5 text-center">
                <Icon name="alert" :size="28" class="mx-auto mb-2" />
                <p class="font-semibold">{{ t('viewer.error_title') }}</p>
                <p class="mt-1 text-sm text-viewer-muted">{{ errorText }}</p>
                <button type="button" class="btn btn-accent btn-sm mt-4" @click="retry"><Icon name="refresh" :size="16" />{{ t('viewer.retry') }}</button>
            </div>
        </div>

        <p v-if="usingFallback && status === 'ready' && !compact" class="pointer-events-none absolute left-2 top-[7.5rem] max-w-[calc(100%-5.5rem)] rounded-xl sm:max-w-[calc(100%-1rem)] sm:rounded-full bg-viewer-panel px-3 py-1 text-xs text-viewer-muted sm:left-3 sm:top-[4.75rem]">
            {{ t('viewer.fallback_notice') }}
        </p>

        <div v-if="showControls" class="absolute right-3 top-[42%] flex -translate-y-1/2 flex-col gap-2 sm:top-1/2" :class="compact ? 'scale-90' : ''">
            <button type="button" class="pano-ctrl" :aria-label="t('viewer.zoom_in')" :title="t('viewer.zoom_in')" @click="zoom(-ZOOM_STEP)">
                <Icon name="zoomIn" />
            </button>
            <button type="button" class="pano-ctrl" :aria-label="t('viewer.zoom_out')" :title="t('viewer.zoom_out')" @click="zoom(ZOOM_STEP)">
                <Icon name="zoomOut" />
            </button>
            <button type="button" class="pano-ctrl" :aria-label="t('viewer.compass_reset')" :title="t('viewer.compass_reset')" @click="resetNorth">
                <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" :style="{ transform: `rotate(${compassAngle}deg)` }">
                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5" />
                    <path d="M12 4l3 8h-6z" fill="#E5484D" />
                    <path d="M12 20l-3-8h6z" fill="currentColor" opacity="0.7" />
                </svg>
            </button>
            <button v-if="fullscreenSupported && !compact" type="button" class="pano-ctrl" :aria-pressed="isFullscreen" :aria-label="isFullscreen ? t('viewer.exit_fullscreen') : t('viewer.fullscreen')" :title="isFullscreen ? t('viewer.exit_fullscreen') : t('viewer.fullscreen')" @click="toggleFullscreen">
                <Icon :name="isFullscreen ? 'minimize' : 'maximize'" />
            </button>
        </div>
        <slot />
    </div>
</template>

<style scoped>
.pano-ctrl {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 999px;
    background: var(--ds-viewer-panel);
    color: var(--ds-viewer-text);
    border: 1px solid var(--ds-viewer-line);
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.pano-ctrl:hover {
    background: rgb(255 255 255 / 0.16);
}
</style>
