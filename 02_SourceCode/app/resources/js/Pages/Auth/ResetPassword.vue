<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    token: { type: String, default: '' },
    email: { type: String, default: '' },
});
const { t } = useT();
const lr = useLocaleRoute();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
// Backend: POST /{locale}/dat-lai-mat-khau (token nằm trong body)
const submit = () => form.post(lr('/dat-lai-mat-khau'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <PageMeta :title="t('auth.reset_title')" fallback-key="auth.reset_intro" />
    <div class="card p-6 md:p-8">
        <h1 class="text-2xl font-bold">{{ t('auth.reset_title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ t('auth.reset_intro') }}</p>
        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="username" :error="form.errors.email" />
            <FormField v-model="form.password" :label="t('account.new_password')" type="password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" minlength="8" maxlength="255" :title="t('auth.password_hint')" autocomplete="new-password" :hint="t('auth.password_hint')" :error="form.errors.password" />
            <FormField v-model="form.password_confirmation" :label="t('auth.password_confirmation')" type="password" required minlength="8" maxlength="255" autocomplete="new-password" :error="form.errors.password_confirmation" />
            <p v-if="form.errors.token" class="field-error" role="alert">{{ form.errors.token }}</p>
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('auth.reset_submit') }}
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-muted">
            <Link :href="lr('/dang-nhap')" class="link font-semibold">{{ t('auth.back_to_login') }}</Link>
        </p>
    </div>
</template>
