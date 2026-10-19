<script setup>
import { ref, computed, onMounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Sơ đồ tầng (SVG) với điểm các phòng: phòng hiện tại nổi bật, nón tầm nhìn (FOV) xoay theo yaw
 * và mở rộng theo hfov, đường lộ trình đã đi, phòng chưa thăm mờ đi. Bấm phòng → phát `select`.
 * Dùng cả ở trang danh sách phòng (staticMap: không có nón, không thu gọn).
 */
const props = defineProps({
    floorplan: { type: Object, default: null },
    scenes: { type: Array, default: () => [] },
    current: { type: String, default: null },
    yaw: { type: Number, default: 0 },
    hfov: { type: Number, default: 100 },
    northOffset: { type: Number, default: 0 },
    visited: { type: Array, default: () => [] },
    collapsible: { type: Boolean, default: true },
    staticMap: { type: Boolean, default: false },
    dark: { type: Boolean, default: true },
});
const emit = defineEmits(['select']);
const { t } = useT();

const MOBILE_BREAKPOINT = 640;
const expanded = ref(true);

const W = computed(() => Number(props.floorplan?.width) || 1000);
const H = computed(() => Number(props.floorplan?.height) || 700);
const unit = computed(() => Math.min(W.value, H.value));
const dotR = computed(() => unit.value * 0.026);
const coneR = computed(() => unit.value * 0.16);

const points = computed(() =>
    props.scenes
        .filter((s) => s.minimap && s.minimap.x !== null && s.minimap.x !== undefined)
        .map((s) => ({
            scene: s,
            x: Number(s.minimap.x) * W.value,
            y: Number(s.minimap.y) * H.value,
            isCurrent: s.slug === props.current,
            isVisited: props.visited.includes(s.slug) || s.slug === props.current,
        })),
);
const currentPoint = computed(() => points.value.find((p) => p.isCurrent) || null);

// Lộ trình: nối các phòng theo thứ tự đã thăm (bỏ lặp liên tiếp)
const routePath = computed(() => {
    const bySlug = new Map(points.value.map((p) => [p.scene.slug, p]));
    const order = [...props.visited, props.current].filter(Boolean).filter((s, i, arr) => s !== arr[i - 1]);
    const pts = order.map((s) => bySlug.get(s)).filter(Boolean);
    if (pts.length < 2) return '';
    return pts.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');
});

/** Hình quạt tầm nhìn: hướng = yaw − north_offset (độ, chiều kim đồng hồ từ hướng Bắc = lên trên). */
const conePath = computed(() => {
    const p = currentPoint.value;
    if (!p || props.staticMap) return '';
    const heading = ((props.yaw - props.northOffset) * Math.PI) / 180;
    const half = (Math.min(Math.max(props.hfov, 20), 160) * Math.PI) / 360;
    const r = coneR.value;
    const at = (a) => `${(p.x + r * Math.sin(a)).toFixed(1)},${(p.y - r * Math.cos(a)).toFixed(1)}`;
    return `M${p.x.toFixed(1)},${p.y.toFixed(1)} L${at(heading - half)} A${r.toFixed(1)},${r.toFixed(1)} 0 0 1 ${at(heading + half)} Z`;
});

const visitedCount = computed(() => points.value.filter((p) => p.isVisited).length);

function select(p) {
    if (!p.isCurrent) emit('select', p.scene);
}

onMounted(() => {
    if (props.collapsible && window.innerWidth < MOBILE_BREAKPOINT) expanded.value = false;
});
</script>

<template>
    <section
        class="minimap overflow-hidden rounded-xl"
        :class="dark ? 'minimap--dark' : 'minimap--light'"
        :aria-label="t('minimap.title')"
    >
        <div v-if="collapsible" class="flex items-center justify-between gap-2 px-3 py-2">
            <p class="flex items-center gap-2 text-xs font-semibold">
                <Icon name="map" :size="16" />{{ t('minimap.title') }}
                <span class="minimap__muted font-normal">{{ t('minimap.visited_count', { n: visitedCount, total: points.length }) }}</span>
            </p>
            <button
                type="button"
                class="minimap__toggle"
                :aria-expanded="expanded"
                aria-controls="minimap-body"
                :aria-label="expanded ? t('minimap.collapse') : t('minimap.expand')"
                @click="expanded = !expanded"
            >
                <Icon :name="expanded ? 'chevronDown' : 'chevronUp'" :size="16" />
            </button>
        </div>
        <div v-show="expanded || !collapsible" id="minimap-body">
            <svg :viewBox="`0 0 ${W} ${H}`" class="block h-auto w-full" role="group" :aria-label="t('minimap.svg_label')">
                <rect :width="W" :height="H" class="minimap__bg" />
                <image v-if="floorplan?.image_url" :href="floorplan.image_url" :width="W" :height="H" preserveAspectRatio="xMidYMid meet" class="minimap__plan" />
                <path v-if="routePath" :d="routePath" class="minimap__route" :stroke-width="unit * 0.008" fill="none" :stroke-dasharray="`${unit * 0.02} ${unit * 0.012}`" />
                <path v-if="conePath" :d="conePath" class="minimap__cone" />
                <g
                    v-for="p in points"
                    :key="p.scene.slug"
                    role="button"
                    :tabindex="p.isCurrent ? -1 : 0"
                    :aria-label="p.isCurrent ? t('minimap.current_room', { name: p.scene.title }) : t('minimap.go_room', { name: p.scene.title }) + (p.isVisited ? '' : ` (${t('minimap.not_visited')})`)"
                    :aria-current="p.isCurrent ? 'location' : undefined"
                    class="minimap__room"
                    :class="{ 'is-current': p.isCurrent, 'is-unvisited': !p.isVisited && !staticMap }"
                    @click="select(p)"
                    @keydown.enter.prevent="select(p)"
                    @keydown.space.prevent="select(p)"
                >
                    <circle :cx="p.x" :cy="p.y" :r="dotR * 2.2" class="minimap__hit" />
                    <circle v-if="p.isCurrent" :cx="p.x" :cy="p.y" :r="dotR * 1.7" class="minimap__halo" />
                    <circle :cx="p.x" :cy="p.y" :r="p.isCurrent ? dotR * 1.15 : dotR" class="minimap__dot" />
                    <text :x="p.x" :y="p.y + dotR * 2.6" text-anchor="middle" :font-size="unit * 0.038" class="minimap__label">{{ p.scene.title }}</text>
                </g>
            </svg>
            <ul v-if="!staticMap" class="flex flex-wrap gap-x-3 gap-y-1 px-3 pb-2 pt-1 text-[11px]" aria-hidden="true">
                <li class="flex items-center gap-1"><span class="minimap__legend is-current"></span>{{ t('minimap.legend_current') }}</li>
                <li class="flex items-center gap-1"><span class="minimap__legend"></span>{{ t('minimap.legend_visited') }}</li>
                <li class="flex items-center gap-1"><span class="minimap__legend is-unvisited"></span>{{ t('minimap.legend_unvisited') }}</li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.minimap--dark {
    background: var(--ds-viewer-panel);
    color: var(--ds-viewer-text);
    border: 1px solid var(--ds-viewer-line);
    --mm-bg: rgb(255 255 255 / 0.04);
    --mm-dot: #63c7a6;
    --mm-current: #f0be6e;
    --mm-label: #f2f5f3;
    --mm-muted: var(--ds-viewer-muted);
    --mm-stroke: rgb(10 16 14 / 0.9);
}
.minimap--light {
    background: var(--ds-surface);
    color: var(--ds-text);
    border: 1px solid var(--ds-border);
    --mm-bg: var(--ds-surface-2);
    --mm-dot: var(--ds-primary);
    --mm-current: var(--ds-accent);
    --mm-label: var(--ds-text);
    --mm-muted: var(--ds-muted);
    --mm-stroke: var(--ds-surface);
}
.minimap__muted { color: var(--mm-muted); }
.minimap__toggle {
    display: inline-flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid currentColor;
    opacity: 0.85;
    cursor: pointer;
}
.minimap__bg { fill: var(--mm-bg); }
.minimap__plan { opacity: 0.9; }
.minimap__route { stroke: var(--mm-current); opacity: 0.85; }
.minimap__cone { fill: var(--mm-current); fill-opacity: 0.35; stroke: var(--mm-current); stroke-opacity: 0.8; stroke-width: 1.5; }
.minimap__room { cursor: pointer; outline: none; }
.minimap__room.is-current { cursor: default; }
.minimap__hit { fill: transparent; }
.minimap__dot { fill: var(--mm-dot); stroke: var(--mm-stroke); stroke-width: 3; transition: r 0.2s ease; }
.minimap__room.is-current .minimap__dot { fill: var(--mm-current); }
.minimap__halo { fill: var(--mm-current); fill-opacity: 0.3; }
.minimap__room.is-unvisited { opacity: 0.45; }
.minimap__room:hover .minimap__dot, .minimap__room:focus-visible .minimap__dot { stroke: var(--mm-current); stroke-width: 5; }
.minimap__room:focus-visible .minimap__hit { stroke: var(--mm-current); stroke-width: 3; stroke-dasharray: 6 4; }
.minimap__label {
    fill: var(--mm-label);
    font-weight: 600;
    paint-order: stroke;
    stroke: var(--mm-stroke);
    stroke-width: 4px;
    pointer-events: none;
}
.minimap__legend { display: inline-block; width: 0.625rem; height: 0.625rem; border-radius: 999px; background: var(--mm-dot); }
.minimap__legend.is-current { background: var(--mm-current); }
.minimap__legend.is-unvisited { opacity: 0.45; }
</style>
