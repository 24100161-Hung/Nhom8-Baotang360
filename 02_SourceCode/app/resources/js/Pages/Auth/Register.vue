<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import PageMeta from '@/Components/Public/PageMeta.vue';

const { t } = useT();
const lr = useLocaleRoute();
const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });

const submit = () => form.post(lr('/dang-ky'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <PageMeta :title="t('auth.register_title')" fallback-key="auth.register_intro" />
    <div class="card p-6 md:p-8">
        <h1 class="text-2xl font-bold">{{ t('auth.register_title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ t('auth.register_intro') }}</p>
        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <FormField v-model="form.name" :label="t('common.name')" required autocomplete="name" minlength="2" maxlength="120" :error="form.errors.name" />
            <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="email" :error="form.errors.email" />
            <FormField v-model="form.phone" :label="t('common.phone')" type="tel" required pattern="\+?[0-9]{9,15}" maxlength="16" inputmode="tel" :title="t('validation_hint.phone')" autocomplete="tel" :error="form.errors.phone" />
            <FormField v-model="form.password" :label="t('auth.password')" type="password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" minlength="8" maxlength="255" :title="t('auth.password_hint')" autocomplete="new-password" :hint="t('auth.password_hint')" :error="form.errors.password" />
            <FormField v-model="form.password_confirmation" :label="t('auth.password_confirmation')" type="password" required minlength="8" maxlength="255" autocomplete="new-password" :error="form.errors.password_confirmation" />
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('auth.create_account') }}
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-muted">
            {{ t('auth.have_account') }}
            <Link :href="lr('/dang-nhap')" class="link font-semibold">{{ t('nav.login') }}</Link>
        </p>
    </div>
</template>
