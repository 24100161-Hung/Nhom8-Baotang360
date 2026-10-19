<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { Link, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/**
 * Thanh tour có hướng dẫn: tên tour, bước hiện tại/tổng, % tiến độ, Quay lại/Tiếp theo, lời dẫn,
 * tự chuyển bước (auto_advance sau dwell_seconds, có đếm ngược và tạm dừng), Thoát tour, màn hình hoàn thành.
 * Tiến trình gửi POST tours.progress (JSON). Chế độ tự do: chỉ hiện nút chọn tour để bắt đầu.
 * Việc điều hướng (đổi cảnh/xoay góc nhìn) do trang Viewer xử lý qua sự kiện `go`.
 */
const props = defineProps({
    tour: { type: Object, default: null },
    tours: { type: Array, default: () => [] },
    mode: { type: String, default: 'free' },
    currentStep: { type: Number, default: null },
    sceneSlug: { type: String, default: null },
    sceneReady: { type: Boolean, default: false },
});
const emit = defineEmits(['go', 'exit', 'resume']);
const { t } = useT();
const { duration } = useFormat();
const lr = useLocaleRoute();

const guided = computed(() => props.mode === 'guided' && !!props.tour?.steps?.length);
const steps = computed(() => props.tour?.steps || []);
const total = computed(() => steps.value.length);
const index = computed(() => Math.min(Math.max((props.currentStep || 1) - 1, 0), Math.max(total.value - 1, 0)));
const step = computed(() => steps.value[index.value] || null);
const isLast = computed(() => index.value >= total.value - 1);
const progress = ref(props.tour?.progress || null);
const completedCount = computed(() => {
    const done = progress.value?.completed_steps;
    return Array.isArray(done) ? Math.max(done.length, index.value) : index.value;
});
const percent = computed(() => {
    if (!total.value) return 0;
    if (finished.value) return 100;
    return Math.round(((index.value + 1) / total.value) * 100);
});
const showNarration = ref(true);
const finished = ref(false);
const busy = ref(false);
const error = ref('');
const pickerOpen = ref(false);

// ----- Đếm ngược tự chuyển bước -----
const remaining = ref(0);
const paused = ref(false);
let timer = null;
const autoActive = computed(() => guided.value && !finished.value && step.value?.auto_advance && Number(step.value?.dwell_seconds) > 0);

function stopTimer() {
    window.clearInterval(timer);
    timer = null;
}
function startTimer() {
    stopTimer();
    if (!autoActive.value || !props.sceneReady || step.value?.scene?.slug !== props.sceneSlug) return;
    remaining.value = Number(step.value.dwell_seconds);
    timer = window.setInterval(() => {
        if (paused.value) return;
        remaining.value -= 1;
        if (remaining.value <= 0) {
            stopTimer();
            next();
        }
    }, 1000);
}

async function send(action, stepNumber) {
    if (!props.tour) return;
    error.value = '';
    try {
        const { data } = await axios.post(lr(`/tour/${props.tour.slug}/tien-trinh`), { step: stepNumber, action });
        if (data?.progress) progress.value = data.progress;
    } catch {
        // Không chặn tham quan khi mất kết nối: chỉ báo lỗi nhẹ, bước vẫn chuyển
        error.value = t('tour_bar.sync_failed');
    }
}

async function next() {
    if (busy.value || !guided.value) return;
    busy.value = true;
    stopTimer();
    if (isLast.value) {
        await send('complete', index.value + 1);
        finished.value = true;
    } else {
        const target = index.value + 2;
        send('next', target);
        emit('go', target);
    }
    busy.value = false;
}
function prev() {
    if (busy.value || index.value === 0) return;
    stopTimer();
    const target = index.value;
    send('prev', target);
    emit('go', target);
}
async function exitTour() {
    stopTimer();
    if (!finished.value) await send('abandon', index.value + 1);
    finished.value = false;
    emit('exit');
}
function restart() {
    finished.value = false;
    send('next', 1);
    emit('go', 1);
}
function startTour(tour) {
    router.post(lr(`/tour/${tour.slug}/bat-dau`));
}

watch(() => [props.currentStep, props.sceneSlug, props.sceneReady, props.mode], startTimer, { immediate: true });
watch(
    () => props.tour?.progress,
    (p) => (progress.value = p || progress.value),
);
watch(
    () => props.tour?.slug,
    () => (finished.value = false),
);
onBeforeUnmount(stopTimer);
defineExpose({ openPicker: () => (pickerOpen.value = true) });
</script>

<template>
    <!-- Chế độ có hướng dẫn -->
    <section v-if="guided && !finished" class="tour-bar w-full max-w-3xl rounded-2xl p-3 sm:p-4" :aria-label="t('tour_bar.region', { name: tour.name })">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold uppercase tracking-wider text-viewer-muted">{{ tour.name }}</p>
                <h2 class="truncate font-body text-base font-bold text-viewer-text sm:text-lg" aria-live="polite">
                    {{ t('tour_bar.step_of', { n: index + 1, total }) }} · {{ step?.title || step?.scene?.title }}
                </h2>
            </div>
            <button type="button" class="tour-bar__ghost" @click="exitTour"><Icon name="close" :size="16" /><span class="hidden sm:inline">{{ t('tour_bar.exit') }}</span><span class="sr-only sm:hidden">{{ t('tour_bar.exit') }}</span></button>
        </div>

        <div class="mt-2 flex items-center gap-3">
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/15" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" :aria-label="t('tour_bar.progress_label')" :aria-valuetext="t('tour_bar.progress_text', { done: completedCount, total, percent })">
                <div class="h-full rounded-full bg-accent transition-[width] duration-500" :style="{ width: `${percent}%` }"></div>
            </div>
            <span class="text-xs font-semibold tabular-nums text-viewer-text">{{ index + 1 }}/{{ total }} · {{ percent }}%</span>
        </div>

        <div v-if="step?.narration" class="mt-2">
            <p v-show="showNarration" id="tour-narration" class="max-h-28 overflow-y-auto whitespace-pre-line text-sm leading-relaxed text-viewer-text sm:max-h-36">{{ step.narration }}</p>
            <button type="button" class="mt-1 text-xs font-semibold text-viewer-muted underline underline-offset-2" aria-controls="tour-narration" :aria-expanded="showNarration" @click="showNarration = !showNarration">
                {{ showNarration ? t('tour_bar.hide_narration') : t('tour_bar.show_narration') }}
            </button>
        </div>

        <p v-if="error" class="mt-2 text-xs text-warning" role="status">{{ error }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="tour-bar__ghost" :disabled="index === 0 || busy" @click="prev">
                <Icon name="chevronLeft" :size="16" />{{ t('tour_bar.prev') }}
            </button>
            <p v-if="autoActive && remaining > 0" class="flex items-center gap-2 text-xs text-viewer-muted" aria-live="off">
                <span class="tabular-nums">{{ t('tour_bar.auto_in', { s: remaining }) }}</span>
                <button type="button" class="underline underline-offset-2" :aria-pressed="paused" @click="paused = !paused">{{ paused ? t('tour_bar.resume') : t('tour_bar.pause') }}</button>
            </p>
            <button type="button" class="btn btn-accent ml-auto" :disabled="busy" @click="next">
                {{ isLast ? t('tour_bar.finish') : t('tour_bar.next') }}<Icon :name="isLast ? 'flag' : 'chevronRight'" :size="16" />
            </button>
        </div>
    </section>

    <!-- Hoàn thành tour -->
    <Teleport to="body">
        <div v-if="finished && tour" class="fixed inset-0 z-[1300] flex items-center justify-center bg-overlay p-4">
            <div class="card max-h-[90dvh] w-full max-w-lg overflow-y-auto p-6 text-center" role="dialog" aria-modal="true" aria-labelledby="tour-done-title">
                <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-accent-soft text-accent-strong"><Icon name="award" :size="28" /></span>
                <h2 id="tour-done-title" class="text-2xl font-bold">{{ t('tour_bar.done_title') }}</h2>
                <p class="mt-2 text-muted">{{ t('tour_bar.done_text', { name: tour.name, n: total }) }}</p>
                <div v-if="tour.similar?.length" class="mt-5 text-left">
                    <h3 class="mb-2 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('tour_bar.similar') }}</h3>
                    <ul class="space-y-2">
                        <li v-for="s in tour.similar" :key="s.slug">
                            <Link :href="s.url" class="flex items-center gap-3 rounded-lg border border-border p-2 hover:bg-surface-2">
                                <img v-if="s.cover_url" :src="s.cover_url" alt="" class="h-12 w-16 rounded object-cover" loading="lazy" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold">{{ s.name }}</span>
                                    <span class="text-xs text-muted">{{ duration(s.duration_minutes) }} · {{ t('tours.steps_n', { n: s.steps_count }) }}</span>
                                </span>
                                <Icon name="chevronRight" :size="16" />
                            </Link>
                        </li>
                    </ul>
                </div>
                <div class="mt-6 flex flex-wrap justify-center gap-2">
                    <button type="button" class="btn" @click="restart"><Icon name="refresh" :size="16" />{{ t('tour_bar.restart') }}</button>
                    <button type="button" class="btn btn-primary" @click="exitTour">{{ t('tour_bar.free_explore') }}</button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Chế độ tự do: nút bắt đầu tour -->
    <div v-if="!guided" class="flex flex-wrap items-center justify-center gap-2">
        <button v-if="tour" type="button" class="btn btn-accent shadow-lg" @click="emit('resume')">
            <Icon name="play" :size="16" />{{ t('tour_bar.resume_tour', { name: tour.name }) }}
        </button>
        <button type="button" class="btn btn-accent shadow-lg" aria-haspopup="dialog" @click="pickerOpen = true">
            <Icon name="route" :size="16" />{{ t('tour_bar.start_guided') }}
        </button>
    </div>
    <Teleport to="body">
        <div v-if="pickerOpen" class="fixed inset-0 z-[1300] flex items-end justify-center bg-overlay p-0 sm:items-center sm:p-4" @click.self="pickerOpen = false" @keydown.esc="pickerOpen = false">
            <div class="card max-h-[85dvh] w-full max-w-lg overflow-y-auto rounded-b-none p-5 sm:rounded-b-[var(--radius)]" role="dialog" aria-modal="true" aria-labelledby="tour-pick-title">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 id="tour-pick-title" class="text-xl font-bold">{{ t('tour_bar.pick_title') }}</h2>
                    <button type="button" class="btn btn-ghost btn-icon" :aria-label="t('common.close')" @click="pickerOpen = false"><Icon name="close" /></button>
                </div>
                <ul v-if="tours.length" class="space-y-2">
                    <li v-for="item in tours" :key="item.slug" class="flex items-center gap-3 rounded-lg border border-border p-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ item.name }}</p>
                            <p class="text-xs text-muted">{{ duration(item.duration_minutes) }} · {{ t('tours.steps_n', { n: item.steps_count }) }} · {{ $te('difficulty', item.difficulty) }}</p>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" @click="startTour(item)">{{ t('tours.start') }}</button>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted">{{ t('tour_bar.no_tours') }}</p>
                <Link :href="lr('/tour')" class="link mt-4 inline-block text-sm">{{ t('tour_bar.all_tours') }}</Link>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.tour-bar {
    background: var(--ds-viewer-panel);
    color: var(--ds-viewer-text);
    border: 1px solid var(--ds-viewer-line);
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgb(0 0 0 / 0.45);
}
.tour-bar__ghost {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    min-height: 2.5rem;
    padding: 0.375rem 0.875rem;
    border-radius: 999px;
    border: 1px solid var(--ds-viewer-line);
    color: var(--ds-viewer-text);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
}
.tour-bar__ghost:hover:not(:disabled) { background: rgb(255 255 255 / 0.12); }
.tour-bar__ghost:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
