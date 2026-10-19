<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import ProvenanceBadge from '@/Components/ProvenanceBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { enumOptions } from '@/Utils/options';
import { LICENSES } from '@/Components/Admin/options';

/**
 * Ảnh của hiện vật/không gian + form tải lên kèm trường nguồn gốc / bản quyền.
 * Backend: POST /quan-tri/media (subject_type artifact|scene, subject_id, file jpg/png/webp ≤5MB hoặc external_url https,
 * license, caption_vi/en, source_name, source_url, author, rights_holder, captured_at, provenance_note, is_cover);
 * DELETE /quan-tri/media/{id}. Chỉ nhận ảnh (audio của phòng dùng AudioUploader).
 */
const props = defineProps({
    media: { type: Array, default: () => [] },
    subjectType: { type: String, required: true },
    subjectId: { type: Number, required: true },
    licenses: { type: [Array, Object], default: null },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const licenseOptions = computed(() => enumOptions(props.licenses || LICENSES, te, 'license'));
const base = computed(() => lr('/quan-tri/media'));

const blank = () => ({
    subject_type: props.subjectType, subject_id: props.subjectId, media_type: 'image', file: null, external_url: '',
    caption_vi: '', caption_en: '', source_name: 'Naturalis Biodiversity Center', source_url: '',
    author: '', license: 'cc_by_sa_3', rights_holder: '', captured_at: '', provenance_note: '', is_cover: false,
});
const form = useForm(blank());

function onFile(e) {
    form.file = e.target.files?.[0] || null;
}

function submit() {
    form.post(base.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const thumbIcon = (m) => ({ audio: 'volume', video: 'play', document: 'file' })[m.media_type] || 'image';
</script>

<template>
    <div class="space-y-6">
        <EmptyState v-if="!media.length" icon="image" :title="t('admin.media.empty_subject')" compact />
        <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="m in media" :key="m.id" class="card overflow-hidden">
                <div class="relative aspect-video bg-surface-2">
                    <img v-if="m.media_type === 'image' && m.url" :src="m.url" :alt="m.caption || ''" class="h-full w-full object-cover" loading="lazy" />
                    <div v-else class="flex h-full items-center justify-center text-accent-strong"><Icon :name="thumbIcon(m)" :size="36" /></div>
                    <span v-if="m.is_cover" class="badge badge-accent absolute left-2 top-2">{{ t('admin.media.cover') }}</span>
                    <span class="badge absolute right-2 top-2">{{ te('media_type', m.media_type) }}<template v-if="m.locale"> · {{ m.locale }}</template></span>
                </div>
                <div class="space-y-2 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <p class="line-clamp-2 text-sm font-medium">{{ m.caption || t('admin.media.no_caption') }}</p>
                        <DeleteAction :url="`${base}/${m.id}`" :name="m.caption || `#${m.id}`" />
                    </div>
                    <ProvenanceBadge :item="m" />
                </div>
            </li>
        </ul>

        <form class="rounded-xl border border-dashed border-border p-4" @submit.prevent="submit">
            <h3 class="mb-3 font-body text-base font-bold">{{ t('admin.media.upload_title') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-slot="{ id, describedby, invalid }" :label="t('admin.media.file')" :hint="t('admin.media.file_hint')" :error="form.errors.file">
                    <input :id="id" type="file" accept="image/jpeg,image/png,image/webp" class="input" :aria-describedby="describedby" :aria-invalid="invalid" @change="onFile" />
                </FormField>
                <FormField v-model="form.external_url" type="url" :label="t('admin.media.external_url')" :hint="t('admin.media.external_url_hint')" :error="form.errors.external_url" maxlength="500" pattern="https://.+" />
                <FormField v-model="form.caption_vi" :label="t('admin.media.caption_vi')" :error="form.errors.caption_vi" maxlength="500" />
                <FormField v-model="form.caption_en" :label="t('admin.media.caption_en')" :error="form.errors.caption_en" maxlength="500" />
            </div>

            <h4 class="mb-2 mt-5 text-sm font-bold uppercase tracking-wider text-muted">{{ t('admin.media.provenance_title') }}</h4>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.license" type="select" :label="t('provenance.license')" :options="licenseOptions" :error="form.errors.license" required />
                <FormField v-model="form.source_name" :label="t('admin.media.source_name')" :error="form.errors.source_name" required maxlength="190" />
                <FormField v-model="form.source_url" type="url" :label="t('admin.media.source_url')" :error="form.errors.source_url" maxlength="500" />
                <FormField v-model="form.author" :label="t('provenance.author')" :error="form.errors.author" maxlength="190" />
                <FormField v-model="form.rights_holder" :label="t('provenance.rights_holder')" :error="form.errors.rights_holder" maxlength="190" />
                <FormField v-model="form.captured_at" type="date" :label="t('provenance.captured_at')" :error="form.errors.captured_at" />
                <FormField v-model="form.provenance_note" class="sm:col-span-2 lg:col-span-3" type="textarea" :rows="2" :label="t('provenance.note')" :error="form.errors.provenance_note" maxlength="1000" />
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="form.is_cover" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />
                    {{ t('admin.media.set_cover') }}
                </label>
                <div class="flex items-center gap-3">
                    <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-32">{{ form.progress.percentage }}%</progress>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <Icon name="upload" :size="18" />{{ t('admin.media.upload') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
