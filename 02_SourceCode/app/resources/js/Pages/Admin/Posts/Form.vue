<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import BilingualField from '@/Components/Admin/BilingualField.vue';
import CoverUpload from '@/Components/Admin/CoverUpload.vue';
import MarkdownEditor from '@/Components/Admin/MarkdownEditor.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { STATUS_CODES, entityOptions } from '@/Utils/options';
import { codeOptions, numOrNull } from '@/Components/Admin/options';

const props = defineProps({
    post: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const p = props.post || {};
const isEdit = computed(() => !!props.post?.id);
const toLocalInput = (iso) => (iso ? String(iso).slice(0, 16) : '');
const form = useForm({
    category_id: p.category_id ?? p.category?.id ?? '', slug: p.slug ?? '', published_at: toLocalInput(p.published_at),
    is_featured: !!p.is_featured, status: p.status ?? 'draft',
    title_vi: p.title_vi ?? p.title ?? '', title_en: p.title_en ?? '', excerpt_vi: p.excerpt_vi ?? p.excerpt ?? '', excerpt_en: p.excerpt_en ?? '',
    body_vi: p.body_vi ?? '', body_en: p.body_en ?? '', cover: null,
});
const bodyLocale = ref('vi');

function submit() {
    form.transform((d) => ({ ...d, category_id: numOrNull(d.category_id), published_at: d.published_at || null, cover: d.cover || undefined, ...(isEdit.value && d.cover ? { _method: 'put' } : {}) }));
    const opts = { preserveScroll: true, forceFormData: !!form.cover };
    if (!isEdit.value) form.post(lr('/quan-tri/blog'), opts);
    else if (form.cover) form.post(lr(`/quan-tri/blog/${p.id}`), opts);
    else form.put(lr(`/quan-tri/blog/${p.id}`), opts);
}
const title = computed(() => (isEdit.value ? t('admin.posts.edit_title', { name: p.title_vi || p.title || p.slug }) : t('admin.posts.create_title')));
const catOptions = computed(() => props.categories.map((c) => ({ value: c.id, label: c.name || te('post_category', c.code) })));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.posts.title'), href: lr('/quan-tri/blog') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title">
        <template v-if="post?.url" #actions><a :href="post.url" target="_blank" rel="noopener" class="btn">{{ t('admin.common.view_public') }}</a></template>
    </PageHeader>
    <form class="space-y-6" @submit.prevent="submit">
        <FormSection :title="t('admin.form.section_general')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField v-model="form.category_id" type="select" :label="t('admin.posts.category')" :options="catOptions.length ? catOptions : entityOptions(categories)" :placeholder="t('admin.form.choose')" :error="form.errors.category_id" required />
                <FormField v-model="form.slug" :label="t('admin.form.slug')" :hint="t('admin.form.slug_hint')" :error="form.errors.slug" required maxlength="150" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" :title="t('admin.form.slug_hint')" />
                <FormField v-model="form.published_at" type="datetime-local" :label="t('admin.posts.published_at')" :error="form.errors.published_at" />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="codeOptions(STATUS_CODES, te, 'status')" :error="form.errors.status" required />
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.is_featured" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />{{ t('admin.common.is_featured') }}
                    </label>
                </div>
            </div>
            <div class="mt-4"><CoverUpload v-model="form.cover" :current-url="post?.cover_url" :error="form.errors.cover" /></div>
        </FormSection>
        <FormSection :title="t('admin.posts.section_content')">
            <div class="space-y-4">
                <BilingualField v-model:vi="form.title_vi" v-model:en="form.title_en" :label="t('admin.common.title')" :error-vi="form.errors.title_vi" :error-en="form.errors.title_en" required maxlength="255" />
                <BilingualField v-model:vi="form.excerpt_vi" v-model:en="form.excerpt_en" type="textarea" :rows="2" :label="t('admin.posts.excerpt')" :error-vi="form.errors.excerpt_vi" :error-en="form.errors.excerpt_en" maxlength="1000" />
                <div class="flex gap-2" role="tablist" :aria-label="t('admin.form.interp_locale')">
                    <button v-for="loc in ['vi', 'en']" :key="loc" type="button" role="tab" class="tab" :aria-selected="bodyLocale === loc" @click="bodyLocale = loc">{{ te('locale', loc) }}</button>
                </div>
                <MarkdownEditor v-if="bodyLocale === 'vi'" v-model="form.body_vi" :label="`${t('admin.posts.body')} (${t('admin.common.lang_vi')})`" :error="form.errors.body_vi" required maxlength="60000" />
                <MarkdownEditor v-else v-model="form.body_en" :label="`${t('admin.posts.body')} (${t('admin.common.lang_en')})`" :error="form.errors.body_en" required maxlength="60000" />
            </div>
        </FormSection>
        <FormActions :cancel-href="lr('/quan-tri/blog')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>
</template>
