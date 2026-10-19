<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

defineProps({
    status: { type: String, default: null },
});
const { t } = useT();
const lr = useLocaleRoute();
const form = useForm({ email: '' });
const submit = () => form.post(lr('/quen-mat-khau'), { preserveScroll: true });
</script>

<template>
    <PageMeta :title="t('auth.forgot_title')" fallback-key="auth.forgot_intro" />
    <div class="card p-6 md:p-8">
        <h1 class="text-2xl font-bold">{{ t('auth.forgot_title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ t('auth.forgot_intro') }}</p>
        <p v-if="status" class="mt-4 rounded-lg bg-success-soft p-3 text-sm text-success" role="status">{{ status }}</p>
        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="email" :error="form.errors.email" />
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('auth.send_reset_link') }}
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-muted">
            <Link :href="lr('/dang-nhap')" class="link font-semibold">{{ t('auth.back_to_login') }}</Link>
        </p>
    </div>
</template>
