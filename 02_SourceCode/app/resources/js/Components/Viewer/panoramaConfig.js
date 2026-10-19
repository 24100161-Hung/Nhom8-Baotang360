import { iconSvg } from '@/Utils/icons';

// Dựng cấu hình Pannellum từ SceneViewer (hợp đồng trang): multires khi có tile_config, ngược lại equirectangular.

const ICONS = { info: 'info', artifact: 'bone', scene: 'arrowUp' };
const DEFAULT_HFOV = 100;
const MIN_HFOV = 40;
const MAX_HFOV = 120;

export const sceneKey = (scene) => `s${scene?.id ?? scene?.slug ?? 'x'}`;
export const hotspotKey = (hs) => `h${hs.id}`;

export function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
}

/** tile_config có thể là {cubeResolution,...} hoặc nguyên config.json của generate.py ({multiRes: {...}}). */
function multiResOf(panorama) {
    const raw = panorama?.config || null;
    const cfg = raw?.multiRes || raw;
    if (!cfg || !cfg.cubeResolution || !panorama?.basePath) return null;
    return {
        basePath: panorama.basePath,
        path: cfg.path || '/%l/%s%y_%x',
        fallbackPath: cfg.fallbackPath || '/fallback/%s',
        extension: cfg.extension || 'jpg',
        tileResolution: Number(cfg.tileResolution) || 512,
        maxLevel: Number(cfg.maxLevel) || 1,
        cubeResolution: Number(cfg.cubeResolution),
    };
}

/** Phần tử nút hotspot: icon theo loại + nhãn; là <button> để dùng được bằng bàn phím. */
function createTooltip(div, args) {
    const { hs, handlers } = args;
    const type = ['info', 'artifact', 'scene'].includes(hs.type) ? hs.type : 'info';
    div.classList.add('bt-hs', `bt-hs--${type}`);
    div.setAttribute('data-hotspot-id', String(hs.id));
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'bt-hs__btn';
    const kind = handlers.labels?.[type] || type;
    btn.setAttribute('aria-label', `${kind}: ${hs.title || ''}`.trim());
    btn.innerHTML = iconSvg(ICONS[type] || 'info', 20, 2.2);
    const label = document.createElement('span');
    label.className = 'bt-hs__label';
    label.textContent = hs.title || kind;
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        handlers.onClick?.(hs);
    });
    // Không để pannellum hiểu nhấn hotspot là bắt đầu kéo khung nhìn
    btn.addEventListener('mousedown', (e) => e.stopPropagation());
    btn.addEventListener('pointerdown', (e) => e.stopPropagation());
    btn.addEventListener('touchstart', (e) => e.stopPropagation(), { passive: true });
    div.appendChild(btn);
    div.appendChild(label);
    handlers.onCreate?.(hs.id, div);
}

export function hotspotConfig(hs, handlers) {
    return {
        id: hotspotKey(hs),
        pitch: Number(hs.pitch) || 0,
        yaw: Number(hs.yaw) || 0,
        type: 'info',
        cssClass: 'bt-hs-root',
        createTooltipFunc: createTooltip,
        createTooltipArgs: { hs, handlers },
    };
}

/**
 * Cấu hình một scene Pannellum.
 * @param {object} scene SceneViewer
 * @param {{pitch,yaw,hfov}|null} view góc nhìn ban đầu (ghi đè mặc định của scene)
 * @param {object} handlers { onCreate(id, el), onClick(hs), labels }
 * @param {boolean} forceFallback bỏ qua multires, dùng ảnh equirectangular
 */
export function buildSceneConfig(scene, view, handlers, forceFallback = false) {
    const panorama = scene?.panorama || {};
    const multiRes = !forceFallback && panorama.type !== 'equirectangular' ? multiResOf(panorama) : null;
    const hfov = Number(view?.hfov ?? scene?.hfov_default ?? DEFAULT_HFOV) || DEFAULT_HFOV;
    const base = {
        pitch: Number(view?.pitch ?? scene?.pitch_default ?? 0) || 0,
        yaw: Number(view?.yaw ?? scene?.yaw_default ?? 0) || 0,
        hfov,
        minHfov: MIN_HFOV,
        maxHfov: MAX_HFOV,
        northOffset: Number(scene?.north_offset || 0),
        hotSpots: (scene?.hotspots || []).map((hs) => hotspotConfig(hs, handlers)),
    };
    if (multiRes) return { ...base, type: 'multires', multiRes };
    return { ...base, type: 'equirectangular', panorama: panorama.fallback_url || scene?.preview_url || '' };
}
