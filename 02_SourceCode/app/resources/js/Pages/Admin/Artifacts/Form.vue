<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import SectionNav from '@/Components/Admin/SectionNav.vue';
import InterpretationEditor from '@/Components/Admin/InterpretationEditor.vue';
import TagPicker from '@/Components/Admin/TagPicker.vue';
import MediaManager from '@/Components/Admin/MediaManager.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, entityOptions } from '@/Utils/options';
import { buildInterpretations, serializeInterpretations, hasErrorWithPrefix } from '@/Utils/contentForm';
import { TAXA, LICENSES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    artifact: { type: Object, default: null },
    periods: { type: Array, default: () => [] },
    scenes: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const a = props.artifact || {};
const isEdit = computed(() => !!props.artifact?.id);
const v = (key, fallback = '') => a[key] ?? fallback;

const form = useForm({
    slug: v('slug'), scene_id: v('scene_id'), catalog_number: v('catalog_number'), scientific_name: v('scientific_name'),
    taxon_group: v('taxon_group'), period_id: v('period_id'), origin_country: v('origin_country'), origin_locality: v('origin_locality'),
    material: v('material'), length_cm: v('length_cm'), width_cm: v('width_cm'), height_cm: v('height_cm'), weight_kg: v('weight_kg'),
    year_collected: v('year_collected'), collector: v('collector'), source_url: v('source_url'),
    image_license: v('image_license', 'cc_by_sa_3'), image_author: v('image_author'),
    is_highlight: !!a.is_highlight, status: v('status', 'draft'),
    interpretations: buildInterpretations(a.interpretations),
    tag_ids: [...(a.tag_ids || [])],
});

const opt = computed(() => ({
    status: codeOptions(STATUS_CODES, te, 'status'),
    taxa: codeOptions(props.enums.taxa || TAXA, te, 'taxon'),
    licenses: codeOptions(props.enums.licenses || LICENSES, te, 'license'),
    scenes: entityOptions(props.scenes),
    periods: entityOptions(props.periods),
}));

const GENERAL = ['slug', 'scene_id', 'catalog_number', 'scientific_name', 'taxon_group', 'period_id', 'status'];
const DETAIL = ['origin_country', 'origin_locality', 'material', 'length_cm', 'width_cm', 'height_cm', 'weight_kg', 'year_collected', 'collector', 'source_url', 'image_license', 'image_author'];
const sections = computed(() => [
    { id: 'general', label: t('admin.form.section_general'), error: GENERAL.some((k) => form.errors[k]) },
    { id: 'details', label: t('admin.artifacts.section_details'), error: DETAIL.some((k) => form.errors[k]) },
    { id: 'interpretations', label: t('admin.form.section_interpretations'), error: hasErrorWithPrefix(form.errors, 'interpretations') },
    { id: 'tags', label: t('admin.form.section_tags'), error: hasErrorWithPrefix(form.errors, 'tag_ids') },
    ...(isEdit.value ? [{ id: 'media', label: t('admin.form.section_media') }] : []),
]);

function submit() {
    form.transform((data) => ({ ...data, interpretations: serializeInterpretations(data.interpretations) }));
    if (isEdit.value) form.put(lr(`/quan-tri/hien-vat/${a.id}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/hien-vat'), { preserveScroll: true });
}

const title = computed(() => (isEdit.value ? t('admin.artifacts.edit_title', { name: a.title_vi || a.slug }) : t('admin.artifacts.create_title')));
const num = { type: 'number', step: 'any', min: 0, max: 9999999 };
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.artifacts.title'), href: lr('/quan-tri/hien-vat') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />
    <SectionNav :sections="sections" />

    <form class="space-y-6" @submit.prevent="submit">
        <FormSection id="general" :title="t('admin.form.section_general')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="150" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" :title="t('admin.form.slug_hint')" />
                <FormField v-model="form.catalog_number" :label="t('artifacts.catalog_number')" :hint="t('admin.artifacts.catalog_hint')" :error="form.errors.catalog_number" maxlength="60" />
                <FormField v-model="form.scientific_name" :label="t('artifacts.scientific_name')" :error="form.errors.scientific_name" maxlength="190" />
                <FormField v-model="form.taxon_group" type="select" :label="t('artifacts.taxon')" :options="opt.taxa" :placeholder="t('admin.form.choose')" :error="form.errors.taxon_group" required />
                <FormField v-model="form.period_id" type="select" :label="t('artifacts.period')" :options="opt.periods" :placeholder="t('common.none')" :error="form.errors.period_id" />
                <FormField v-model="form.scene_id" type="select" :label="t('admin.artifacts.scene')" :options="opt.scenes" :placeholder="t('common.none')" :error="form.errors.scene_id" />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="opt.status" :error="form.errors.status" required />
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.is_highlight" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />
                        {{ t('admin.artifacts.is_highlight') }}
                    </label>
                </div>
            </div>
        </FormSection>

        <FormSection id="details" :title="t('admin.artifacts.section_details')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField v-model="form.origin_country" :label="t('artifacts.origin')" :error="form.errors.origin_country" minlength="2" maxlength="2" pattern="[A-Za-z]{2}" :title="t('validation_hint.country')" />
                <FormField v-model="form.origin_locality" :label="t('admin.artifacts.origin_locality')" :error="form.errors.origin_locality" maxlength="190" />
                <FormField v-model="form.material" :label="t('artifacts.material')" :error="form.errors.material" maxlength="120" />
                <FormField v-model="form.year_collected" type="number" min="1500" :max="new Date().getFullYear()" step="1" :label="t('admin.artifacts.year_collected')" :error="form.errors.year_collected" />
                <FormField v-model="form.length_cm" v-bind="num" :label="t('admin.artifacts.length_cm')" :error="form.errors.length_cm" />
                <FormField v-model="form.width_cm" v-bind="num" :label="t('admin.artifacts.width_cm')" :error="form.errors.width_cm" />
                <FormField v-model="form.height_cm" v-bind="num" :label="t('admin.artifacts.height_cm')" :error="form.errors.height_cm" />
                <FormField v-model="form.weight_kg" v-bind="num" :label="t('admin.artifacts.weight_kg')" :error="form.errors.weight_kg" />
                <FormField v-model="form.collector" :label="t('admin.artifacts.collector')" :error="form.errors.collector" maxlength="190" />
                <FormField v-model="form.source_url" type="url" class="sm:col-span-2" :label="t('admin.artifacts.source_url')" :hint="t('admin.artifacts.source_url_hint')" :error="form.errors.source_url" maxlength="500" />
                <FormField v-model="form.image_license" type="select" :label="t('admin.artifacts.image_license')" :options="opt.licenses" :error="form.errors.image_license" />
                <FormField v-model="form.image_author" :label="t('admin.artifacts.image_author')" :error="form.errors.image_author" maxlength="190" />
            </div>
        </FormSection>

        <FormSection id="interpretations" :title="t('admin.form.section_interpretations')" :description="t('admin.form.interp_description')" class="scroll-mt-20">
            <InterpretationEditor :items="form.interpretations" :errors="form.errors" />
        </FormSection>

        <FormSection id="tags" :title="t('admin.form.section_tags')" class="scroll-mt-20">
            <TagPicker v-model="form.tag_ids" :tags="tags" :error="form.errors.tag_ids" />
        </FormSection>

        <FormActions :cancel-href="lr('/quan-tri/hien-vat')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>

    <FormSection v-if="isEdit" id="media" :title="t('admin.form.section_media')" :description="t('admin.form.media_description')" class="mt-6 scroll-mt-20">
        <MediaManager :media="artifact.media || []" subject-type="artifact" :subject-id="artifact.id" :licenses="enums.licenses" />
    </FormSection>
    <p v-else class="mt-6 text-sm text-muted">{{ t('admin.form.media_after_create') }}</p>
</template>
