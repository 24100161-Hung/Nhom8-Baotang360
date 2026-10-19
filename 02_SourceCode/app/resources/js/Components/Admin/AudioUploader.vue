<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { AUDIO_LOCALES } from '@/Components/Admin/options';

/** Audio guide của phòng theo ngôn ngữ vi/en/de/nl: danh sách + tải MP3 (≤ 15 MB) kèm transcript. */
const props = defineProps({
    sceneId: { type: Number, required: true },
    audios: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { clock } = useFormat();
const lr = useLocaleRoute();

const base = computed(() => lr(`/quan-tri/khong-gian-360/${props.sceneId}/audio`));
const missing = computed(() => AUDIO_LOCALES.filter((l) => !props.audios.some((a) => a.locale === l)));
const form = useForm({ locale: missing.value[0] || 'vi', file: null, transcript: '', caption: '' });
const localeOptions = AUDIO_LOCALES.map((c) => ({ value: c, label: te('locale', c) }));

function onFile(e) {
    form.file = e.target.files?.[0] || null;
}
function submit() {
    form.post(base.value, { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('file', 'transcript', 'caption') });
}
</script>

<template>
    <div class="space-y-4">
        <p v-if="missing.length" class="rounded-lg bg-warning-soft p-3 text-sm text-warning" role="status">
            {{ t('admin.scenes.audio_missing', { list: missing.map((l) => l.toUpperCase()).join(', ') }) }}
        </p>
        <EmptyState v-if="!audios.length" icon="volume" :title="t('admin.scenes.audio_empty')" compact />
        <ul v-else class="grid gap-3 md:grid-cols-2">
            <li v-for="a in audios" :key="a.id" class="rounded-lg border border-border p-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold">
                        <span class="badge badge-primary mr-1">{{ (a.locale || '').toUpperCase() }}</span>
                        {{ a.mode === 'tts' || !a.url ? t('audio.mode_tts') : t('audio.mode_file') }}
                        <span v-if="a.duration_sec" class="text-muted"> · {{ clock(a.duration_sec) }}</span>
                    </p>
                    <DeleteAction :url="`${base}/${a.id}`" :name="`${t('audio.title')} ${(a.locale || '').toUpperCase()}`" />
                </div>
                <audio v-if="a.url" :src="a.url" controls preload="none" class="mt-2 w-full" :aria-label="`${t('audio.title')} ${a.locale}`"></audio>
                <p v-if="a.transcript" class="mt-2 line-clamp-3 text-xs text-muted">{{ a.transcript }}</p>
            </li>
        </ul>

        <form class="rounded-xl border border-dashed border-border p-4" @submit.prevent="submit">
            <h3 class="mb-3 font-body text-base font-bold">{{ t('admin.scenes.audio_upload_title') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField v-model="form.locale" type="select" :label="t('admin.media.locale')" :options="localeOptions" :error="form.errors.locale" required />
                <FormField v-slot="{ id, describedby, invalid }" :label="t('admin.scenes.audio_file')" :hint="t('admin.scenes.audio_file_hint')" :error="form.errors.file">
                    <input :id="id" type="file" accept="audio/mpeg,.mp3" class="input" :aria-describedby="describedby" :aria-invalid="invalid" @change="onFile" />
                </FormField>
                <FormField v-model="form.caption" class="sm:col-span-2" :label="t('admin.scenes.audio_caption')" :error="form.errors.caption" maxlength="500" />
                <FormField v-model="form.transcript" class="sm:col-span-2" type="textarea" :rows="4" :label="t('admin.scenes.audio_transcript')" :hint="t('admin.media.transcript_hint')" :error="form.errors.transcript" maxlength="20000" />
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-end gap-3">
                <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-32">{{ form.progress.percentage }}%</progress>
                <button type="submit" class="btn btn-primary" :disabled="form.processing || !form.file">
                    <Icon name="upload" :size="18" />{{ t('admin.scenes.audio_upload') }}
                </button>
            </div>
        </form>
    </div>
</template>
