<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import StarRating from '@/Components/StarRating.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Bảng thông tin tại điểm nóng (panel không chặn, trượt từ phải; trên di động là bottom sheet).
 * info: ảnh, mô tả, thời kỳ, nguồn gốc (+ nút tới hiện vật nếu gắn); artifact: thẻ hiện vật; scene: nút chuyển phòng.
 */
const props = defineProps({
    hotspot: { type: Object, default: null },
});
const emit = defineEmits(['close', 'navigate']);
const { t, te } = useT();
const { country } = useFormat();
const closeBtn = ref(null);
let lastFocus = null;

const hs = computed(() => props.hotspot);
const artifact = computed(() => hs.value?.artifact || null);
const image = computed(() => hs.value?.image_url || artifact.value?.cover?.url || null);
const imageAlt = computed(() => artifact.value?.cover?.caption || hs.value?.title || '');
const facts = computed(() => {
    const a = artifact.value;
    if (!a) return [];
    return [
        { label: t('artifacts.scientific_name'), value: a.scientific_name, italic: true },
        { label: t('artifacts.period'), value: a.period?.name },
        { label: t('artifacts.origin'), value: country(a.origin_country) },
        { label: t('artifacts.material'), value: a.material },
        { label: t('artifacts.taxon'), value: a.taxon_group ? te('taxon', a.taxon_group) : null },
        { label: t('artifacts.catalog_number'), value: a.catalog_number },
    ].filter((f) => f.value);
});

function close() {
    emit('close');
}
function onKey(e) {
    if (e.key === 'Escape') close();
}

watch(
    () => props.hotspot?.id,
    (id) => {
        if (id) {
            lastFocus ??= document.activeElement;
            document.addEventListener('keydown', onKey);
            nextTick(() => closeBtn.value?.focus());
        } else {
            document.removeEventListener('keydown', onKey);
            lastFocus?.focus?.();
            lastFocus = null;
        }
    },
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Transition
        enter-from-class="translate-y-full sm:translate-y-0 sm:translate-x-full opacity-0"
        enter-active-class="transition duration-300 ease-out"
        leave-to-class="translate-y-full sm:translate-y-0 sm:translate-x-full opacity-0"
        leave-active-class="transition duration-200 ease-in"
    >
        <aside
            v-if="hs"
            class="hs-panel fixed inset-x-0 bottom-0 z-[1150] flex max-h-[75dvh] flex-col sm:inset-x-auto sm:bottom-auto sm:right-0 sm:top-0 sm:h-full sm:max-h-none sm:w-[24rem]"
            role="dialog"
            aria-modal="false"
            aria-labelledby="hs-panel-title"
        >
            <header class="flex items-start gap-3 border-b border-viewer-line p-4">
                <span class="hs-panel__badge" :class="`is-${hs.type}`">
                    <Icon :name="hs.type === 'scene' ? 'door' : hs.type === 'artifact' ? 'bone' : 'info'" :size="18" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-viewer-muted">{{ t(`viewer.hotspot_${hs.type}`) }}</p>
                    <h2 id="hs-panel-title" class="font-display text-xl font-bold leading-snug text-viewer-text">{{ artifact?.title || hs.title }}</h2>
                </div>
                <button ref="closeBtn" type="button" class="hs-panel__close" :aria-label="t('common.close')" @click="close">
                    <Icon name="close" :size="18" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
                <img v-if="image" :src="image" :alt="imageAlt" class="mb-4 aspect-[4/3] w-full rounded-lg object-cover" loading="lazy" decoding="async" />

                <p v-if="hs.type === 'artifact' && artifact?.summary" class="text-sm leading-relaxed">{{ artifact.summary }}</p>
                <p v-if="hs.text" class="whitespace-pre-line text-sm leading-relaxed" :class="hs.type === 'artifact' ? 'mt-3 text-viewer-muted' : ''">{{ hs.text }}</p>
                <p v-if="hs.type === 'info' && !hs.text && artifact?.summary" class="text-sm leading-relaxed">{{ artifact.summary }}</p>

                <dl v-if="facts.length" class="mt-4 grid grid-cols-2 gap-3 rounded-lg border border-viewer-line p-3 text-sm">
                    <div v-for="f in facts" :key="f.label" class="min-w-0">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-viewer-muted">{{ f.label }}</dt>
                        <dd class="mt-0.5 break-words" :class="f.italic ? 'italic' : ''">{{ f.value }}</dd>
                    </div>
                </dl>

                <div v-if="artifact?.rating_count" class="mt-3 flex items-center gap-2 text-sm">
                    <StarRating :value="artifact.rating_avg" readonly :size="16" />
                    <span class="text-viewer-muted">{{ t('reviews.count', { n: artifact.rating_count }) }}</span>
                </div>
            </div>

            <footer class="flex flex-wrap gap-2 border-t border-viewer-line p-4">
                <Link v-if="artifact?.url" :href="artifact.url" class="btn btn-accent flex-1">
                    {{ t('viewer.artifact_detail') }}<Icon name="arrowRight" :size="16" />
                </Link>
                <button v-if="hs.type === 'scene' && hs.target" type="button" class="btn btn-accent flex-1" @click="emit('navigate', hs.target, hs)">
                    <Icon name="door" :size="16" />{{ t('viewer.go_to_room', { name: hs.target.title }) }}
                </button>
                <button type="button" class="btn flex-1 border-viewer-line bg-transparent text-viewer-text hover:bg-white/10" @click="close">{{ t('viewer.back_to_view') }}</button>
            </footer>
        </aside>
    </Transition>
</template>

<style scoped>
.hs-panel {
    background: var(--ds-viewer-panel);
    color: var(--ds-viewer-text);
    backdrop-filter: blur(10px);
    border-top: 1px solid var(--ds-viewer-line);
    border-radius: 1rem 1rem 0 0;
    box-shadow: 0 -10px 30px rgb(0 0 0 / 0.4);
}
@media (min-width: 640px) {
    .hs-panel {
        border-top: 0;
        border-left: 1px solid var(--ds-viewer-line);
        border-radius: 0;
    }
}
.hs-panel__badge {
    display: inline-flex;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #fff;
    background: #24577f;
}
.hs-panel__badge.is-artifact { background: #9a5a06; }
.hs-panel__badge.is-scene { background: #1d5c4b; }
.hs-panel__close {
    display: inline-flex;
    width: 2.5rem;
    height: 2.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid var(--ds-viewer-line);
    cursor: pointer;
}
.hs-panel__close:hover { background: rgb(255 255 255 / 0.12); }
</style>
