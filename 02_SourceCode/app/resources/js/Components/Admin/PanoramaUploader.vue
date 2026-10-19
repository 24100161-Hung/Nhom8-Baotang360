<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/**
 * Tải ảnh equirectangular (jpg ≤ 40 MB) lên → backend gọi Python sinh tiles đa phân giải (chạy nền).
 * Hiện thanh tiến trình upload, sau đó hỏi trạng thái tiles mỗi 3 giây đến khi done/error.
 */
const props = defineProps({
    sceneId: { type: Number, required: true },
    initialState: { type: Object, default: null }, // {state, max_level, cube_resolution}
    currentUrl: { type: String, default: null },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const MAX_BYTES = 40 * 1024 * 1024;
const POLL_MS = 3000;
const file = ref(null);
const percent = ref(0);
const uploading = ref(false);
const error = ref('');
const status = ref(props.initialState || null);
let timer = null;

const base = computed(() => lr(`/quan-tri/khong-gian-360/${props.sceneId}`));
const state = computed(() => status.value?.state || 'none');
const TONE = { done: 'badge-success', running: 'badge-info', pending: 'badge-warning', error: 'badge-danger' };

function onFile(e) {
    error.value = '';
    const f = e.target.files?.[0] || null;
    if (f && f.size > MAX_BYTES) {
        error.value = t('admin.scenes.panorama_too_big');
        file.value = null;
        return;
    }
    file.value = f;
}

async function poll() {
    stopPoll();
    try {
        const { data } = await axios.get(`${base.value}/tiles`);
        status.value = data;
    } catch {
        error.value = t('admin.scenes.tiles_poll_failed');
        return;
    }
    if (['pending', 'running'].includes(state.value)) timer = window.setTimeout(poll, POLL_MS);
    else if (state.value === 'done') router.reload({ only: ['scene'] });
}
function stopPoll() {
    window.clearTimeout(timer);
}

async function upload() {
    if (!file.value) return;
    const body = new FormData();
    body.append('panorama', file.value);
    uploading.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(`${base.value}/panorama`, body, {
            onUploadProgress: (e) => (percent.value = e.total ? Math.round((e.loaded / e.total) * 100) : 0),
        });
        status.value = data?.tiles || data || { state: 'pending' };
        poll();
    } catch (e) {
        error.value = e.response?.data?.errors?.panorama?.[0] || e.response?.data?.message || t('admin.scenes.upload_failed');
    } finally {
        uploading.value = false;
    }
}

onMounted(() => {
    if (['pending', 'running'].includes(state.value)) poll();
});
onBeforeUnmount(stopPoll);
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold">{{ t('admin.scenes.tiles') }}:</span>
            <span class="badge" :class="TONE[state]" role="status">{{ te('tiles_state', state) }}</span>
            <span v-if="status?.max_level" class="text-muted">{{ t('admin.scenes.tiles_info', { level: status.max_level, cube: status.cube_resolution }) }}</span>
            <Icon v-if="['pending', 'running'].includes(state)" name="refresh" :size="16" class="animate-spin text-muted" />
            <a v-if="currentUrl" :href="currentUrl" target="_blank" rel="noopener" class="link ml-auto text-xs">{{ t('admin.scenes.open_original') }}</a>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label for="pano-file" class="label">{{ t('admin.scenes.panorama_file') }}</label>
                <input id="pano-file" type="file" accept="image/jpeg" class="input" aria-describedby="pano-hint" @change="onFile" />
                <p id="pano-hint" class="field-hint">{{ t('admin.scenes.panorama_hint') }}</p>
            </div>
            <button type="button" class="btn btn-primary" :disabled="!file || uploading" @click="upload">
                <Icon name="upload" :size="18" />{{ uploading ? t('admin.scenes.uploading') : t('admin.scenes.upload_panorama') }}
            </button>
        </div>
        <div v-if="uploading || percent" class="flex items-center gap-3">
            <progress :value="percent" max="100" class="h-2 flex-1" :aria-label="t('admin.scenes.upload_progress')">{{ percent }}%</progress>
            <span class="text-xs tabular-nums">{{ percent }}%</span>
        </div>
        <p v-if="error" class="field-error" role="alert">{{ error }}</p>
    </div>
</template>
