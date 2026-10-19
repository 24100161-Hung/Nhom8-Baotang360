<script setup>
import { computed } from 'vue';
import { useT } from '@/Composables/useT';

/** Chọn vị trí phòng trên sơ đồ tầng: bấm ảnh → minimap_x/y trong khoảng 0..1 (4 chữ số). */
const props = defineProps({
    floorplan: { type: Object, default: null },
    others: { type: Array, default: () => [] }, // các phòng khác [{title, minimap:{x,y}}]
});
const x = defineModel('x', { type: [Number, String, null], default: null });
const y = defineModel('y', { type: [Number, String, null], default: null });
const { t } = useT();
const ratio = computed(() => `${Number(props.floorplan?.width) || 1000} / ${Number(props.floorplan?.height) || 700}`);
const round4 = (v) => Math.round(v * 10000) / 10000;

function pick(e) {
    const r = e.currentTarget.getBoundingClientRect();
    x.value = round4(Math.min(Math.max((e.clientX - r.left) / r.width, 0), 1));
    y.value = round4(Math.min(Math.max((e.clientY - r.top) / r.height, 0), 1));
}
const has = computed(() => x.value !== null && x.value !== '' && y.value !== null && y.value !== '');
</script>

<template>
    <div v-if="floorplan?.image_url">
        <p class="label">{{ t('admin.scenes.minimap_pick') }}</p>
        <div class="relative w-full cursor-crosshair overflow-hidden rounded-lg border border-border bg-surface-2" :style="{ aspectRatio: ratio }" @click="pick">
            <img :src="floorplan.image_url" :alt="t('minimap.svg_label')" class="pointer-events-none h-full w-full object-contain" />
            <span
                v-for="(o, i) in others.filter((s) => s.minimap)"
                :key="i"
                class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-muted opacity-70"
                :style="{ left: `${o.minimap.x * 100}%`, top: `${o.minimap.y * 100}%` }"
                :title="o.title"
                aria-hidden="true"
            ></span>
            <span v-if="has" class="absolute h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-accent shadow" :style="{ left: `${x * 100}%`, top: `${y * 100}%` }" aria-hidden="true"></span>
        </div>
        <p class="field-hint">{{ t('admin.scenes.minimap_pick_hint') }}</p>
    </div>
</template>
