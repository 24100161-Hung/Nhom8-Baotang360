<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import Icon from '@/Components/Icon.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AudioGuide from '@/Components/AudioGuide.vue';
import PanoramaViewer from '@/Components/Viewer/PanoramaViewer.vue';
import MiniMap from '@/Components/Viewer/MiniMap.vue';
import HotspotPanel from '@/Components/Viewer/HotspotPanel.vue';
import GuidedTourBar from '@/Components/Viewer/GuidedTourBar.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitTracker } from '@/Composables/useVisitTracker';

const props = defineProps({
    scene: { type: Object, required: true },
    allScenes: { type: Array, default: () => [] },
    floorplan: { type: Object, default: null },
    tour: { type: Object, default: null },
    currentStep: { type: Number, default: null },
    mode: { type: String, default: 'free' },
    visited: { type: Array, default: () => [] },
    sessionKey: { type: String, default: null },
    // Danh sách tour để chọn khi đang ở chế độ tự do (yêu cầu bổ sung hợp đồng)
    tours: { type: Array, default: () => [] },
});
const { t } = useT();
const lr = useLocaleRoute();
const tracker = useVisitTracker({ sessionKey: props.sessionKey });

const shell = ref(null);
const pano = ref(null);
const tourBar = ref(null);
const view = ref({ pitch: Number(props.scene.pitch_default || 0), yaw: Number(props.scene.yaw_default || 0), hfov: Number(props.scene.hfov_default || 100) });
const panelHotspot = ref(null);
const sceneReady = ref(false);
const roomsOpen = ref(false);
const infoOpen = ref(false);
const audioOpen = ref(false);
const stepNo = ref(props.currentStep || 1);
const visitedLocal = ref([...props.visited]);

const guided = computed(() => props.mode === 'guided' && !!props.tour?.steps?.length);
const step = computed(() => (guided.value ? props.tour.steps[stepNo.value - 1] || null : null));
const highlightId = computed(() => (step.value && step.value.scene?.slug === props.scene.slug ? step.value.hotspot_id : null));
const stepView = (s) => (s ? { pitch: s.pitch, yaw: s.yaw, hfov: s.hfov } : null);

// Liên kết "Xem trong 360°" từ trang hiện vật mang ?pitch=&yaw= để nhìn thẳng vào hiện vật
function queryView() {
    if (typeof window === 'undefined') return null;
    const q = new URLSearchParams(window.location.search);
    if (!q.has('yaw')) return null;
    return { pitch: Number(q.get('pitch') || 0), yaw: Number(q.get('yaw')), hfov: q.has('hfov') ? Number(q.get('hfov')) : undefined };
}

// Góc nhìn khi vào scene: bước tour (nếu đúng phòng), tham số URL, hoặc góc của hotspot đích
const pendingView = ref(step.value && step.value.scene?.slug === props.scene.slug ? stepView(step.value) : queryView());

function sceneUrl(slug, query = {}) {
    const params = new URLSearchParams();
    if (query.tour) params.set('tour', query.tour);
    if (query.step) params.set('step', String(query.step));
    if (query.mode) params.set('mode', query.mode);
    const qs = params.toString();
    return lr(`/tham-quan-360/${slug}${qs ? `?${qs}` : ''}`);
}
function currentQuery(overrides = {}) {
    return {
        tour: props.tour?.slug || null,
        step: props.tour ? stepNo.value : null,
        mode: props.mode,
        ...overrides,
    };
}

/** Đổi phòng: partial reload (giữ viewer, MiniMap, audio) và cập nhật URL. */
function goToScene(slug, { view: target = null, query = {} } = {}) {
    panelHotspot.value = null;
    roomsOpen.value = false;
    if (slug === props.scene.slug) {
        if (target) pano.value?.lookAt(target.pitch, target.yaw, target.hfov);
        router.visit(sceneUrl(slug, currentQuery(query)), { preserveState: true, preserveScroll: true, replace: true, only: ['currentStep', 'mode', 'tour'] });
        return;
    }
    pendingView.value = target;
    sceneReady.value = false;
    router.visit(sceneUrl(slug, currentQuery(query)), {
        preserveState: true,
        preserveScroll: true,
        only: ['scene', 'visited', 'currentStep', 'mode', 'tour'],
    });
}

function onNavigate(target) {
    const v = target.pitch !== null && target.pitch !== undefined ? { pitch: target.pitch, yaw: target.yaw, hfov: target.hfov } : null;
    goToScene(target.slug, { view: v });
}

function onHotspot(hs) {
    tracker.track('hotspot_click', { scene_id: props.scene.id, hotspot_id: hs.id, meta: { type: hs.type } });
    if (hs.type === 'scene') return;
    panelHotspot.value = hs;
    if (hs.artifact?.id) tracker.track('artifact_view', { artifact_id: hs.artifact.id, scene_id: props.scene.id, meta: { source: 'hotspot' } });
}

function onAudioPlay(info) {
    tracker.track('audio_play', { scene_id: props.scene.id, meta: { audio_locale: info.locale, mode: info.mode } });
}

function onReady() {
    sceneReady.value = true;
    tracker.enterScene(props.scene.id, guided.value ? { tour_id: props.tour.id, step: stepNo.value, meta: { mode: 'guided' } } : { meta: { mode: 'free' } });
}

// ----- Tour có hướng dẫn -----
function goStep(n) {
    const target = props.tour?.steps?.[n - 1];
    if (!target) return;
    stepNo.value = n;
    goToScene(target.scene.slug, { view: stepView(target), query: { step: n, mode: 'guided' } });
}
function exitTour() {
    goToScene(props.scene.slug, { query: { mode: 'free' } });
}
function resumeTour() {
    goStep(stepNo.value || 1);
}
function setMode(next) {
    if (next === props.mode) return;
    if (next === 'free') return exitTour();
    if (props.tour) return resumeTour();
    tourBar.value?.openPicker();
}

watch(
    () => props.currentStep,
    (n) => {
        if (n) stepNo.value = n;
    },
);
watch(
    () => props.visited,
    (v) => (visitedLocal.value = [...v]),
);
watch(
    () => props.scene.slug,
    (slug, old) => {
        if (old && !visitedLocal.value.includes(old)) visitedLocal.value = [...visitedLocal.value, old];
        infoOpen.value = false;
    },
);

function onKey(e) {
    if (e.key === 'Escape' && roomsOpen.value) roomsOpen.value = false;
}
onMounted(() => {
    document.addEventListener('keydown', onKey);
    audioOpen.value = window.innerWidth >= 1024;
});
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <PageMeta :title="t('viewer.page_title', { name: scene.title })" :description="scene.summary" :image="scene.preview_url" />
    <h1 class="sr-only">{{ t('viewer.page_title', { name: scene.title }) }}</h1>

    <div ref="shell" class="relative h-[calc(100dvh-1.25rem)] w-full overflow-hidden bg-viewer-bg text-viewer-text">
        <PanoramaViewer
            ref="pano"
            class="absolute inset-0"
            :scene="scene"
            :initial-view="pendingView"
            :highlight-hotspot-id="highlightId"
            :auto-rotate="!guided"
            :fullscreen-target="shell"
            @view-change="view = $event"
            @hotspot="onHotspot"
            @navigate="onNavigate"
            @ready="onReady"
        />

        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between gap-2 p-2 sm:p-3">
            <!-- Thanh trên: quay lại, tên phòng, chế độ, chọn phòng, ngôn ngữ -->
            <header class="flex flex-wrap items-start gap-2">
                <div class="viewer-chip pointer-events-auto flex min-w-0 max-w-full items-center gap-2 py-1 pl-1 pr-3 sm:max-w-md">
                    <Link :href="lr('/tham-quan-360')" class="viewer-icon-btn" :aria-label="t('viewer.back_to_rooms')" :title="t('viewer.back_to_rooms')">
                        <Icon name="chevronLeft" />
                    </Link>
                    <div class="min-w-0">
                        <p class="truncate text-[11px] font-semibold uppercase tracking-wider text-viewer-muted">
                            Naturalis · {{ t('scenes.floor_n', { n: scene.floor ?? 0 }) }}
                        </p>
                        <p class="truncate font-display text-base font-bold sm:text-lg">{{ scene.title }}</p>
                    </div>
                    <button type="button" class="viewer-icon-btn" :aria-expanded="infoOpen" aria-controls="scene-info" :aria-label="t('viewer.room_info')" :title="t('viewer.room_info')" @click="infoOpen = !infoOpen">
                        <Icon name="info" :size="18" />
                    </button>
                </div>

                <div class="pointer-events-auto ml-auto flex flex-wrap items-center justify-end gap-2">
                    <div class="viewer-chip flex p-1" role="group" :aria-label="t('viewer.mode_label')">
                        <button type="button" class="viewer-seg" :aria-pressed="!guided" @click="setMode('free')">{{ t('viewer.mode_free') }}</button>
                        <button type="button" class="viewer-seg" :aria-pressed="guided" @click="setMode('guided')">{{ t('viewer.mode_guided') }}</button>
                    </div>
                    <div class="relative">
                        <button type="button" class="viewer-chip viewer-chip-btn" aria-haspopup="true" :aria-expanded="roomsOpen" aria-controls="room-menu" @click="roomsOpen = !roomsOpen">
                            <Icon name="door" :size="18" /><span class="hidden sm:inline">{{ t('viewer.choose_room') }}</span><span class="sr-only sm:hidden">{{ t('viewer.choose_room') }}</span>
                        </button>
                        <ul v-if="roomsOpen" id="room-menu" class="viewer-chip absolute right-0 z-20 mt-2 max-h-[60dvh] w-64 overflow-y-auto p-1">
                            <li v-for="s in allScenes" :key="s.slug">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm hover:bg-white/10"
                                    :aria-current="s.slug === scene.slug ? 'location' : undefined"
                                    @click="goToScene(s.slug)"
                                >
                                    <img v-if="s.preview_url" :src="s.preview_url" alt="" class="h-9 w-14 shrink-0 rounded object-cover" loading="lazy" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold">{{ s.title }}</span>
                                        <span class="text-xs text-viewer-muted">
                                            {{ visitedLocal.includes(s.slug) || s.slug === scene.slug ? t('minimap.legend_visited') : t('minimap.not_visited') }}
                                        </span>
                                    </span>
                                    <Icon v-if="s.slug === scene.slug" name="pin" :size="16" />
                                </button>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="viewer-chip viewer-chip-btn" :aria-expanded="audioOpen" aria-controls="viewer-audio" @click="audioOpen = !audioOpen">
                        <Icon name="volume" :size="18" /><span class="hidden sm:inline">{{ t('audio.title') }}</span><span class="sr-only sm:hidden">{{ t('audio.title') }}</span>
                    </button>
                    <div class="viewer-chip"><LocaleSwitch /></div>
                </div>

                <section v-if="infoOpen" id="scene-info" class="viewer-chip pointer-events-auto w-full max-w-md p-4 text-sm" :aria-label="t('viewer.room_info')">
                    <p v-if="scene.summary" class="leading-relaxed">{{ scene.summary }}</p>
                    <div v-if="scene.description_html" class="viewer-prose mt-2 max-h-56 overflow-y-auto leading-relaxed" v-html="scene.description_html"></div>
                    <p class="mt-2 text-xs text-viewer-muted">
                        {{ t('scenes.hotspot_count', { n: scene.hotspot_count ?? scene.hotspots?.length ?? 0 }) }} · {{ t('scenes.artifact_count', { n: scene.artifact_count ?? 0 }) }}
                    </p>
                    <p v-if="scene.is_simulated" class="mt-2 rounded bg-white/10 px-2 py-1 text-xs">{{ t('scenes.simulated_notice') }}</p>
                    <p v-if="scene.sources?.length" class="mt-2 text-xs text-viewer-muted">{{ t('common.sources') }}: {{ Array.isArray(scene.sources) ? scene.sources.join('; ') : scene.sources }}</p>
                </section>
            </header>

            <!-- Hàng dưới: audio, tour, minimap -->
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-end">
                <div v-show="audioOpen" id="viewer-audio" class="pointer-events-auto w-full sm:w-72 lg:w-80">
                    <AudioGuide :audios="scene.audios || []" :reset-key="scene.id" :title="t('viewer.audio_title', { name: scene.title })" dark @play="onAudioPlay" />
                    <p v-if="!scene.audios?.length" class="viewer-chip p-3 text-xs text-viewer-muted">{{ t('audio.none') }}</p>
                </div>
                <div class="pointer-events-auto flex min-w-0 flex-1 justify-center">
                    <GuidedTourBar
                        ref="tourBar"
                        :tour="tour"
                        :tours="tours"
                        :mode="mode"
                        :current-step="stepNo"
                        :scene-slug="scene.slug"
                        :scene-ready="sceneReady"
                        @go="goStep"
                        @exit="exitTour"
                        @resume="resumeTour"
                    />
                </div>
                <div class="pointer-events-auto ml-auto w-56 sm:w-64 lg:w-72">
                    <MiniMap
                        :floorplan="floorplan"
                        :scenes="allScenes"
                        :current="scene.slug"
                        :yaw="view.yaw"
                        :hfov="view.hfov"
                        :north-offset="Number(scene.north_offset || 0)"
                        :visited="visitedLocal"
                        @select="(s) => goToScene(s.slug)"
                    />
                </div>
            </div>
        </div>

        <HotspotPanel :hotspot="panelHotspot" @close="panelHotspot = null" @navigate="onNavigate" />
    </div>
</template>

<style scoped>
.viewer-chip {
    background: var(--ds-viewer-panel);
    color: var(--ds-viewer-text);
    border: 1px solid var(--ds-viewer-line);
    border-radius: 999px;
    backdrop-filter: blur(10px);
}
ul.viewer-chip, section.viewer-chip {
    border-radius: 1rem;
}
.viewer-chip :deep(.btn-ghost) {
    color: var(--ds-viewer-text);
}
.viewer-chip :deep(.btn-ghost:hover) {
    background: rgb(255 255 255 / 0.12);
}
.viewer-chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    min-height: 2.5rem;
    padding: 0.375rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
}
.viewer-chip-btn:hover { background: rgb(30 40 37 / 0.95); }
.viewer-icon-btn {
    display: inline-flex;
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    cursor: pointer;
}
.viewer-icon-btn:hover { background: rgb(255 255 255 / 0.12); }
.viewer-seg {
    min-height: 2rem;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
}
.viewer-seg[aria-pressed='true'] {
    background: #f0be6e;
    color: #1a1206;
}
.viewer-prose :deep(p + p) { margin-top: 0.5em; }
.viewer-prose :deep(a) { text-decoration: underline; }
</style>
