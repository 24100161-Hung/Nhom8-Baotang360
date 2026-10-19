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
import MinimapPicker from '@/Components/Admin/MinimapPicker.vue';
import PanoramaUploader from '@/Components/Admin/PanoramaUploader.vue';
import AudioUploader from '@/Components/Admin/AudioUploader.vue';
import HotspotEditor from '@/Components/Admin/HotspotEditor.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES } from '@/Utils/options';
import { buildInterpretations, serializeInterpretations, hasErrorWithPrefix } from '@/Utils/contentForm';
import { LICENSES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    scene: { type: Object, default: null },
    scenes: { type: Array, default: () => [] },
    artifacts: { type: Array, default: () => [] },
    floorplan: { type: Object, default: null },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const s = props.scene || {};
const isEdit = computed(() => !!props.scene?.id);
const v = (key, fallback = '') => s[key] ?? fallback;
const form = useForm({
    slug: v('slug'), code: v('code'), floor: v('floor', 0), sort_order: v('sort_order', 0),
    hfov_default: v('hfov_default', 100), pitch_default: v('pitch_default', 0), yaw_default: v('yaw_default', 0),
    minimap_x: s.minimap_x ?? s.minimap?.x ?? '', minimap_y: s.minimap_y ?? s.minimap?.y ?? '', north_offset: v('north_offset', 0),
    status: v('status', 'draft'), source_name: v('source_name', 'Naturalis Biodiversity Center'), source_url: v('source_url'),
    author: v('author'), license: v('license', 'cc_by_sa_3'), is_simulated: !!s.is_simulated,
    interpretations: buildInterpretations(s.interpretations),
});

const opt = computed(() => ({
    status: codeOptions(STATUS_CODES, te, 'status'),
    licenses: codeOptions(props.enums.licenses || LICENSES, te, 'license'),
}));
const others = computed(() => props.scenes.filter((x) => x.id !== s.id));
const err = (keys) => keys.some((k) => form.errors[k]);
const sections = computed(() => [
    { id: 'general', label: t('admin.form.section_general'), error: err(['slug', 'code', 'floor', 'sort_order', 'status']) },
    { id: 'view', label: t('admin.scenes.section_view'), error: err(['hfov_default', 'pitch_default', 'yaw_default', 'minimap_x', 'minimap_y', 'north_offset']) },
    { id: 'provenance', label: t('admin.scenes.section_provenance'), error: err(['source_name', 'source_url', 'author', 'license']) },
    { id: 'interpretations', label: t('admin.form.section_interpretations'), error: hasErrorWithPrefix(form.errors, 'interpretations') },
    ...(isEdit.value
        ? [{ id: 'panorama', label: t('admin.scenes.section_panorama') }, { id: 'audio', label: t('admin.scenes.section_audio') }, { id: 'hotspots', label: t('admin.scenes.section_hotspots') }]
        : []),
]);

function submit() {
    form.transform((d) => ({ ...d, interpretations: serializeInterpretations(d.interpretations) }));
    if (isEdit.value) form.put(lr(`/quan-tri/khong-gian-360/${s.id}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/khong-gian-360'), { preserveScroll: true });
}
const title = computed(() => (isEdit.value ? t('admin.scenes.edit_title', { name: s.title || s.slug }) : t('admin.scenes.create_title')));
const num = (step = 'any') => ({ type: 'number', step });
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.scenes.title'), href: lr('/quan-tri/khong-gian-360') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title">
        <template v-if="scene?.url" #actions>
            <a :href="scene.url" target="_blank" rel="noopener" class="btn">{{ t('admin.common.view_public') }}</a>
        </template>
    </PageHeader>
    <SectionNav :sections="sections" />

    <form class="space-y-6" @submit.prevent="submit">
        <FormSection id="general" :title="t('admin.form.section_general')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" :title="t('admin.form.slug_hint')" />
                <FormField v-model="form.code" :label="t('admin.scenes.code')" :hint="t('admin.scenes.code_hint')" :error="form.errors.code" required maxlength="40" pattern="[a-z0-9_]+" :title="t('admin.scenes.code_hint')" />
                <FormField v-model="form.floor" v-bind="num('1')" min="-2" max="10" required :label="t('admin.scenes.floor')" :error="form.errors.floor" />
                <FormField v-model="form.sort_order" v-bind="num('1')" min="0" max="1000" :label="t('admin.common.sort_order')" :error="form.errors.sort_order" />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="opt.status" :error="form.errors.status" required />
                <div class="flex items-end pb-2 sm:col-span-3">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.is_simulated" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />{{ t('admin.scenes.is_simulated') }}
                    </label>
                </div>
            </div>
        </FormSection>

        <FormSection id="view" :title="t('admin.scenes.section_view')" :description="t('admin.scenes.section_view_hint')" class="scroll-mt-20">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField v-model="form.hfov_default" v-bind="num()" min="30" max="120" :label="t('admin.scenes.hfov_default')" :error="form.errors.hfov_default" />
                    <FormField v-model="form.north_offset" v-bind="num()" min="-360" max="360" :label="t('admin.scenes.north_offset')" :error="form.errors.north_offset" />
                    <FormField v-model="form.pitch_default" v-bind="num()" min="-90" max="90" :label="t('admin.scenes.pitch_default')" :error="form.errors.pitch_default" />
                    <FormField v-model="form.yaw_default" v-bind="num()" min="-180" max="180" :label="t('admin.scenes.yaw_default')" :error="form.errors.yaw_default" />
                    <FormField v-model="form.minimap_x" v-bind="num()" min="0" max="1" required :label="t('admin.scenes.minimap_x')" :error="form.errors.minimap_x" />
                    <FormField v-model="form.minimap_y" v-bind="num()" min="0" max="1" required :label="t('admin.scenes.minimap_y')" :error="form.errors.minimap_y" />
                </div>
                <MinimapPicker v-model:x="form.minimap_x" v-model:y="form.minimap_y" :floorplan="floorplan" :others="others" />
            </div>
        </FormSection>

        <FormSection id="provenance" :title="t('admin.scenes.section_provenance')" class="scroll-mt-20">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField v-model="form.source_name" :label="t('admin.media.source_name')" :error="form.errors.source_name" required maxlength="190" />
                <FormField v-model="form.source_url" type="url" :label="t('admin.media.source_url')" :error="form.errors.source_url" maxlength="500" />
                <FormField v-model="form.author" :label="t('provenance.author')" :error="form.errors.author" maxlength="150" />
                <FormField v-model="form.license" type="select" :label="t('provenance.license')" :options="opt.licenses" :error="form.errors.license" required />
            </div>
        </FormSection>

        <FormSection id="interpretations" :title="t('admin.form.section_interpretations')" :description="t('admin.scenes.interp_hint')" class="scroll-mt-20">
            <InterpretationEditor :items="form.interpretations" :errors="form.errors" />
        </FormSection>

        <FormActions :cancel-href="lr('/quan-tri/khong-gian-360')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>

    <template v-if="isEdit">
        <FormSection id="panorama" :title="t('admin.scenes.section_panorama')" :description="t('admin.scenes.section_panorama_hint')" class="mt-6 scroll-mt-20">
            <PanoramaUploader :scene-id="scene.id" :initial-state="scene.tiles || (scene.tiles_state ? { state: scene.tiles_state } : null)" :current-url="scene.panorama_url || scene.panorama?.fallback_url" />
        </FormSection>
        <FormSection id="audio" :title="t('admin.scenes.section_audio')" :description="t('admin.scenes.section_audio_hint')" class="mt-6 scroll-mt-20">
            <AudioUploader :scene-id="scene.id" :audios="scene.audios || []" />
        </FormSection>
        <FormSection id="hotspots" :title="t('admin.scenes.section_hotspots')" class="mt-6 scroll-mt-20">
            <HotspotEditor :scene="scene" :scenes="scenes" :artifacts="artifacts" />
        </FormSection>
    </template>
    <p v-else class="mt-6 text-sm text-muted">{{ t('admin.scenes.after_create') }}</p>
</template>
