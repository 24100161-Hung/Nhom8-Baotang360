<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import FormField from '@/Components/FormField.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import FormActions from '@/Components/Admin/FormActions.vue';
import BilingualField from '@/Components/Admin/BilingualField.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { PACKAGE_TYPES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    package: { type: Object, default: null },
    enums: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { money } = useFormat();
const lr = useLocaleRoute();

const p = props.package || {};
const isEdit = computed(() => !!props.package?.id);
// benefits có thể là {vi:[],en:[]} hoặc tách sẵn benefits_vi/benefits_en
const lines = (loc) => (p[`benefits_${loc}`] || p.benefits?.[loc] || []).join('\n');
const form = useForm({
    code: p.code ?? '', package_type: p.package_type ?? 'single', price_vnd: p.price_vnd ?? 0, validity_days: p.validity_days ?? 1,
    max_per_order: p.max_per_order ?? 10, is_featured: !!p.is_featured, sort_order: p.sort_order ?? 0, status: ({ active: 'published', inactive: 'draft' })[p.status] ?? p.status ?? 'published',
    name_vi: p.name_vi ?? p.name ?? '', name_en: p.name_en ?? '', description_vi: p.description_vi ?? p.description ?? '', description_en: p.description_en ?? '',
    benefits_vi: lines('vi'), benefits_en: lines('en'),
});
const split = (s) => String(s || '').split('\n').map((x) => x.trim()).filter(Boolean);
// Backend lưu draft|published (chấp nhận cả active|inactive); nhãn lấy từ enums.statuses nếu có
const statusOptions = computed(() => (props.enums.statuses?.length ? props.enums.statuses.map((x) => ({ value: x.code, label: x.name })) : ['published', 'draft'].map((c) => ({ value: c, label: te('status', c) }))));

function submit() {
    form.transform((d) => {
        const { benefits_vi: bv, benefits_en: be, ...rest } = d;
        return { ...rest, benefits: { vi: split(bv), en: split(be) } };
    });
    if (isEdit.value) form.put(lr(`/quan-tri/goi-ve/${p.id}`), { preserveScroll: true });
    else form.post(lr('/quan-tri/goi-ve'), { preserveScroll: true });
}
const title = computed(() => (isEdit.value ? t('admin.packages.edit_title', { name: p.name_vi || p.name || p.code }) : t('admin.packages.create_title')));
</script>

<template>
    <Head :title="title" />
    <Breadcrumbs :items="[{ label: t('admin.packages.title'), href: lr('/quan-tri/goi-ve') }, { label: title }]" class="mb-3" />
    <PageHeader :title="title" />
    <form class="space-y-6" @submit.prevent="submit">
        <FormSection :title="t('admin.form.section_general')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField v-model="form.code" :label="t('admin.packages.code')" :hint="t('admin.packages.code_hint')" :error="form.errors.code" required maxlength="40" pattern="[a-z0-9_\-]+" :title="t('admin.packages.code_hint')" />
                <FormField v-model="form.package_type" type="select" :label="t('common.type')" :options="codeOptions(PACKAGE_TYPES, te, 'package_type')" :error="form.errors.package_type" required />
                <FormField v-model="form.price_vnd" type="number" min="0" max="100000000" step="1" :label="t('admin.packages.price')" :hint="money(form.price_vnd)" :error="form.errors.price_vnd" required />
                <FormField v-model="form.validity_days" type="number" min="1" max="3650" step="1" :label="t('admin.packages.validity_days')" :hint="t('admin.packages.validity_hint')" :error="form.errors.validity_days" required />
                <FormField v-model="form.max_per_order" type="number" min="1" max="50" step="1" :label="t('admin.packages.max_per_order')" :error="form.errors.max_per_order" required />
                <FormField v-model="form.sort_order" type="number" min="0" max="1000" step="1" :label="t('admin.common.sort_order')" :error="form.errors.sort_order" />
                <FormField v-model="form.status" type="select" :label="t('common.status')" :options="statusOptions" :error="form.errors.status" required />
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input v-model="form.is_featured" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />{{ t('admin.common.is_featured') }}
                    </label>
                </div>
            </div>
        </FormSection>
        <FormSection :title="t('admin.packages.section_content')">
            <div class="space-y-4">
                <BilingualField v-model:vi="form.name_vi" v-model:en="form.name_en" :label="t('admin.common.name')" :error-vi="form.errors.name_vi" :error-en="form.errors.name_en" required maxlength="150" />
                <BilingualField v-model:vi="form.description_vi" v-model:en="form.description_en" type="textarea" :rows="3" :label="t('admin.common.description')" :error-vi="form.errors.description_vi" :error-en="form.errors.description_en" maxlength="2000" />
                <BilingualField v-model:vi="form.benefits_vi" v-model:en="form.benefits_en" type="textarea" :rows="5" :label="t('admin.packages.benefits')" :hint="t('admin.common.one_per_line')" :error-vi="form.errors['benefits.vi']" :error-en="form.errors['benefits.en']" />
            </div>
        </FormSection>
        <FormActions :cancel-href="lr('/quan-tri/goi-ve')" :processing="form.processing" :has-errors="form.hasErrors" />
    </form>
</template>
