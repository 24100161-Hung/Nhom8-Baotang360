import { onBeforeUnmount, onMounted } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

// Gom sự kiện tham quan vào hàng đợi, gửi theo lô (mỗi 10 giây hoặc khi rời trang).
// Chỉ gửi mã định danh nội dung + thời lượng, không gửi PII (tên, email, IP do máy chủ tự thấy).
const FLUSH_INTERVAL_MS = 10_000;
const MAX_BATCH = 50;
const ALLOWED = new Set([
    'scene_enter', 'scene_leave', 'hotspot_click', 'tour_start', 'tour_step',
    'tour_complete', 'tour_abandon', 'artifact_view', 'audio_play',
]);
const FIELDS = ['scene_id', 'hotspot_id', 'tour_id', 'step', 'artifact_id', 'duration_ms', 'meta'];

const state = { queue: [], scene: null, timer: null, users: 0, endpoint: null, sessionKey: null, locale: 'vi', token: null };

function deviceType() {
    const w = window.innerWidth || 1024;
    if (w < 640) return 'mobile';
    return w < 1024 ? 'tablet' : 'desktop';
}

// Ưu tiên token mới nhất từ shared prop (sau đăng nhập session đổi token, thẻ meta có thể đã cũ)
function csrfToken() {
    return state.token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/** Ghép object lồng nhau thành cặp khóa kiểu PHP (events[0][meta][x]) cho body x-www-form-urlencoded. */
function appendParams(params, prefix, value) {
    if (value === null || value === undefined) return;
    if (typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => appendParams(params, `${prefix}[${k}]`, v));
        return;
    }
    params.append(prefix, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
}

function buildBody(events) {
    const params = new URLSearchParams();
    params.append('_token', csrfToken());
    if (state.sessionKey) params.append('session_key', state.sessionKey);
    params.append('locale', state.locale);
    params.append('device', deviceType());
    events.forEach((ev, i) => appendParams(params, `events[${i}]`, ev));
    return params;
}

/** Gửi lô hiện tại; ưu tiên sendBeacon (không bị hủy khi đóng tab), dự phòng axios. */
function flush() {
    if (!state.queue.length || !state.endpoint) return;
    const events = state.queue.splice(0, MAX_BATCH);
    const body = buildBody(events);
    const sent = typeof navigator !== 'undefined' && navigator.sendBeacon ? navigator.sendBeacon(state.endpoint, body) : false;
    if (!sent) {
        axios.post(state.endpoint, body, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }).catch(() => {
            // Mất sự kiện thống kê không ảnh hưởng người dùng: bỏ qua có chủ đích
        });
    }
    if (state.queue.length) flush();
}

function clean(type, payload) {
    const ev = { type };
    FIELDS.forEach((f) => {
        const v = payload?.[f];
        if (v !== undefined && v !== null && v !== '') ev[f] = v;
    });
    return ev;
}

function track(type, payload = {}) {
    if (!ALLOWED.has(type)) return;
    state.queue.push(clean(type, payload));
    if (state.queue.length >= MAX_BATCH) flush();
}

function leaveScene() {
    if (!state.scene) return;
    const duration = Math.max(0, Math.round(performance.now() - state.scene.since));
    track('scene_leave', { scene_id: state.scene.id, duration_ms: duration });
    state.scene = null;
}

/** Vào phòng: extra = {tour_id?, step?, meta?} (backend chỉ giữ meta trong danh sách trắng: from_scene, mode…). */
function enterScene(sceneId, extra = {}) {
    if (state.scene?.id === sceneId) return;
    const from = state.scene?.id;
    leaveScene();
    state.scene = { id: sceneId, since: performance.now() };
    const { meta = {}, ...rest } = extra || {};
    track('scene_enter', { scene_id: sceneId, ...rest, meta: { ...(from ? { from_scene: from } : {}), ...meta } });
}

function onHide() {
    if (document.visibilityState === 'hidden') {
        const current = state.scene;
        leaveScene();
        flush();
        // Quay lại tab: tính lượt vào mới cho cảnh đang xem
        if (current) state.resume = current.id;
    } else if (state.resume) {
        enterScene(state.resume, { meta: { source: 'resume' } });
        state.resume = null;
    }
}

function onPageHide() {
    leaveScene();
    flush();
}

/**
 * Bộ ghi hành vi tham quan dùng chung trong một trang (singleton theo module).
 * @param {{ sessionKey?: string, endpoint?: string }} options
 */
export function useVisitTracker(options = {}) {
    const page = usePage();
    const locale = page.props.locale || 'vi';
    state.locale = locale;
    state.endpoint = options.endpoint || `/${locale}/su-kien`;
    state.sessionKey = options.sessionKey || state.sessionKey;
    state.token = page.props.csrf_token || null;

    onMounted(() => {
        state.users += 1;
        if (state.users === 1) {
            state.timer = window.setInterval(flush, FLUSH_INTERVAL_MS);
            document.addEventListener('visibilitychange', onHide);
            window.addEventListener('pagehide', onPageHide);
        }
    });

    onBeforeUnmount(() => {
        state.users -= 1;
        if (state.users <= 0) {
            state.users = 0;
            leaveScene();
            flush();
            window.clearInterval(state.timer);
            document.removeEventListener('visibilitychange', onHide);
            window.removeEventListener('pagehide', onPageHide);
        }
    });

    return { track, enterScene, leaveScene, flush };
}
