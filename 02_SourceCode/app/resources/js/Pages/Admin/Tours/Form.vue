<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import SectionNav from '@/Components/Admin/SectionNav.vue';
import BilingualField from '@/Components/Admin/BilingualField.vue';
import CoverUpload from '@/Components/Admin/CoverUpload.vue';
import StepsEditor from '@/Components/Admin/StepsEditor.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, entityOptions } from '@/Utils/options';
import { hasErrorWithPrefix } from '@/Utils/contentForm';
import { DIFFICULTIES, TOUR_MODES, codeOptions, numOrNull } from '@/Components/Admin/options';

const props = defineProps({
    tour: { type: Object, default: null },
    themes: { type: Array, default: () => [] },
    scenes: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const tr = props.tour || {};
const isEdit = computed(() => !!props.tour?.id);
const v = (key, fallback = '') => tr[key] ?? fallback;
const normalizeStep = (s) => ({
    scene_id: s.scene_id ?? s.scene?.id ?? '', hotspot_id: s.hotspot_id ?? '', pitch: s.pitch ?? 0, yaw: s.yaw ?? 0, hfov: s.hfov ?? 100,
    dwell_seconds: s.dwell_seconds ?? 20, auto_advance: !!s.auto_advance,
    title_vi: s.title_vi ?? s.title ?? '', title_en: s.title_en ?? '', narration_vi: s.narration_vi ?? s.narration ?? '', narration_en: s.narration_en ?? '',
});
const form = useForm({
    slug: v('slug'), theme_tag_id: v('theme_tag_id', tr.theme?.id ?? ''), duration_minutes: v('duration_minutes', 30),
    difficulty: v('difficulty', 'de'), mode: v('mode', 'both'), is_featured: !!tr.is_featured, sort_order: v('sort_order', 0),
    status: v('status', 'draft'), name_vi: v('name_vi', tr.name ?? ''), name_en: v('name_en'),
    description_vi: v('description_vi', tr.description ?? ''), description_en: v('description_en'),
    highlights_vi: v('highlights_vi'), highlights_en: v('highlights_en'),
    cover: null, steps: (tr.steps || []).map(normalizeStep),
});

const err = (keys) => keys.some((k) => form.errors[k]);
const sections = computed(() => [
    { id: 'general', label: t('admin.form.section_general'), error: err(['slug', 'theme_tag_id', 'duration_minutes', 'difficulty', 'mode', 'status']) },
    { id: 'content', label: t('admin.tours.section_content'), error: err(['name_vi', 'name_en', 'description_vi', 'description_en', 'cover']) },
    { id: 'steps', label: t('admin.tours.section_steps'), error: hasErrorWithPrefix(form.errors, 'steps') },
]);

function submit() {
    form.transform((d) => ({
        ...d,
        theme_tag_id: numOrNull(d.theme_tag_id),
        cover: d.cover || undefined,
        steps: d.steps.map((s, i) => ({ ...s, step_order: i + 1, scene_id: numOrNull(s.scene_id), hotspot_id: numOrNull(s.hotspot_id), auto_advance: s.auto_advance ? 1 : 0 })),
        ...(isEdit.value && d.cover ? { _method: 'put' } : {}),
    }));
    // Có file ảnh bìa → multipart; Laravel cần POST + _method=PUT khi cập nhật kèm file
    const opts = { preserveScroll: true, forceFormData: !!form.cover };
    if (!isEdit.value) form.post(lr('/quan-tri/tour'), opts);
    else if (form.cover) form.post(lr(`/quan-tri/tour/${tr.id}`), opts);
    else form.put(lr(`/quan-tri/tour/${tr.id}`), opts);
}
const title = computed(() => (isEdit.value ? t('admin.tours.edit_title', { name: tr.name_vi || tr.name || tr.slug }) : t('admin.tours.create_title')));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.tours.title'), href: lr('/quan-tri/tour') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />
    <SectionNav :sections="sections" />

    <form class="space-y-6" @submit.prevent="submit">
        <FormSection id="general" :title="t('admin.form.section_general')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="150" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" :title="t('admin.form.slug_hint')" />
                <FormField v-model="form.theme_tag_id" type="select" :label="t('admin.tours.theme')" :options="entityOptions(themes)" :placeholder="t('common.none')" :error="form.errors.theme_tag_id" />
                <FormField v-model="form.duration_minutes" type="number" min="5" max="600" step="1" :label="t('admin.tours.duration_minutes')" :error="form.errors.duration_minutes" required />
                <FormField v-model="form.difficulty" type="select" :label="t('admin.tours.difficulty')" :options="codeOptions(DIFFICULTIES, te, 'difficulty')" :error="form.errors.difficulty" required />
                <FormField v-model="form.mode" type="select" :label="t('admin.tours.mode')" :options="codeOptions(TOUR_MODES, te, 'tour_mode')" :error="form.errors.mode" required />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="codeOptions(STATUS_CODES, te, 'status')" :error="form.errors.status" required />
                <FormField v-model="form.sort_order" type="number" min="0" max="1000" step="1" :label="t('admin.common.sort_order')" :error="form.errors.sort_order" />
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.is_featured" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />{{ t('admin.common.is_featured') }}
                    </label>
                </div>
            </div>
        </FormSection>

        <FormSection id="content" :title="t('admin.tours.section_content')" class="scroll-mt-20">
            <div class="space-y-4">
                <BilingualField v-model:vi="form.name_vi" v-model:en="form.name_en" :label="t('admin.common.name')" :error-vi="form.errors.name_vi" :error-en="form.errors.name_en" required maxlength="190" />
                <BilingualField v-model:vi="form.description_vi" v-model:en="form.description_en" type="textarea" :rows="4" :label="t('admin.common.description')" :error-vi="form.errors.description_vi" :error-en="form.errors.description_en" maxlength="5000" />
                <BilingualField v-model:vi="form.highlights_vi" v-model:en="form.highlights_en" type="textarea" :rows="3" :label="t('admin.tours.highlights')" :hint="t('admin.common.one_per_line')" :error-vi="form.errors.highlights_vi" :error-en="form.errors.highlights_en" maxlength="3000" />
                <CoverUpload v-model="form.cover" :current-url="tour?.cover_url" :error="form.errors.cover" />
            </div>
        </FormSection>

        <FormSection id="steps" :title="t('admin.tours.section_steps')" :description="t('admin.tours.section_steps_hint')" class="scroll-mt-20">
            <StepsEditor v-model="form.steps" :scenes="scenes" :errors="form.errors" />
        </FormSection>

        <FormActions :cancel-href="lr('/quan-tri/tour')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>
</template>
