<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useTheme } from '@/Composables/useTheme';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    // Token Sanctum: chỉ hiển thị khu quản lý khi backend truyền prop này
    tokens: { type: Array, default: undefined },
});
const page = usePage();
const { t, te } = useT();
const { dateTime } = useFormat();
const lr = useLocaleRoute();
const { theme } = useTheme();

const AVATAR_MAX = 2 * 1024 * 1024;
const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const profile = useForm({
    _method: 'patch',
    name: props.user?.name || '',
    phone: props.user?.phone || '',
    locale: props.user?.locale || page.props.locale || 'vi',
    theme: props.user?.theme || theme.value || 'light',
    avatar: null,
});
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const tokenForm = useForm({ name: '' });
const preview = ref(null);
const avatarError = ref('');
const revoking = ref(null);
const copied = ref(false);
const newToken = computed(() => page.props.flash?.token || page.props.newToken || null);
const avatarSrc = computed(() => preview.value || props.user?.avatar_url || null);

// Áp dụng theme ngay trên trình duyệt; giá trị được lưu cùng lần cập nhật hồ sơ
function applyThemeLocally(value) {
    document.documentElement.setAttribute('data-theme', value);
    theme.value = value;
    try {
        localStorage.setItem('bt360-theme', value);
    } catch {
        // storage bị chặn: chỉ đổi trong phiên
    }
}

function onAvatar(e) {
    const file = e.target.files?.[0] || null;
    avatarError.value = '';
    if (file && (!AVATAR_TYPES.includes(file.type) || file.size > AVATAR_MAX)) {
        avatarError.value = t('account.avatar_invalid');
        e.target.value = '';
        return;
    }
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
    profile.avatar = file;
}

function saveProfile() {
    const localeChanged = profile.locale !== page.props.locale;
    // PATCH multipart: gửi POST kèm _method=patch để PHP đọc được tệp
    profile.post(lr('/tai-khoan/ho-so'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            applyThemeLocally(profile.theme);
            profile.avatar = null;
            if (localeChanged) router.visit(`/${profile.locale}/tai-khoan/ho-so`, { preserveState: true });
        },
    });
}

const savePassword = () => password.patch(lr('/tai-khoan/mat-khau'), { preserveScroll: true, onSuccess: () => password.reset() });
const createToken = () => tokenForm.post(lr('/tai-khoan/token'), { preserveScroll: true, onSuccess: () => tokenForm.reset() });
function revoke() {
    const tk = revoking.value;
    if (!tk) return;
    router.delete(lr(`/tai-khoan/token/${tk.id}`), { preserveScroll: true, onFinish: () => (revoking.value = null) });
}
async function copyToken() {
    try {
        await navigator.clipboard.writeText(newToken.value);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}

const localeOptions = (page.props.locales || [{ code: 'vi' }, { code: 'en' }]).map((l) => ({ value: l.code, label: l.label || te('locale', l.code) }));
const themeOptions = ['light', 'dark'].map((v) => ({ value: v, label: te('theme', v) }));
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));
</script>

<template>
    <Head :title="t('account.profile_title')" />
    <div class="container-page max-w-3xl py-8">
        <PageHeader :eyebrow="t('nav.profile')" :title="t('account.profile_title')" :description="user?.email" />

        <section class="card p-5 md:p-6">
            <h2 class="font-body text-lg font-bold">{{ t('account.profile_info') }}</h2>
            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="saveProfile">
                <div class="flex items-center gap-4 sm:col-span-2">
                    <img v-if="avatarSrc" :src="avatarSrc" :alt="t('account.avatar_alt')" class="h-20 w-20 rounded-full border border-border object-cover" />
                    <span v-else class="flex h-20 w-20 items-center justify-center rounded-full bg-primary-soft text-2xl font-bold text-primary" aria-hidden="true">{{ (user?.name || '?').charAt(0).toUpperCase() }}</span>
                    <FormField v-slot="{ id, describedby, invalid }" class="min-w-0 flex-1" :label="t('account.avatar')" :hint="t('account.avatar_hint')" :error="avatarError || profile.errors.avatar">
                        <input :id="id" type="file" accept="image/jpeg,image/png,image/webp" class="input" :aria-describedby="describedby" :aria-invalid="invalid" @change="onAvatar" />
                    </FormField>
                </div>
                <FormField v-model="profile.name" class="sm:col-span-2" :label="t('common.name')" required minlength="2" maxlength="120" autocomplete="name" :error="profile.errors.name" />
                <FormField v-model="profile.phone" :label="t('common.phone')" type="tel" pattern="\+?[0-9]{9,15}" maxlength="16" inputmode="tel" :title="t('validation_hint.phone')" autocomplete="tel" :error="profile.errors.phone" />
                <FormField v-model="profile.locale" :label="t('account.locale')" type="select" required :options="localeOptions" :error="profile.errors.locale" />
                <FormField v-model="profile.theme" :label="t('account.theme')" type="select" required :options="themeOptions" :error="profile.errors.theme" @update:model-value="applyThemeLocally" />
                <div class="sm:col-span-2">
                    <progress v-if="profile.progress" :value="profile.progress.percentage" max="100" class="mb-2 w-40">{{ profile.progress.percentage }}%</progress>
                    <button type="submit" class="btn btn-primary" :disabled="profile.processing">{{ profile.processing ? t('common.saving') : t('common.save') }}</button>
                    <span v-if="profile.recentlySuccessful" class="ml-3 text-sm text-success" role="status">{{ t('account.saved') }}</span>
                </div>
            </form>
        </section>

        <section class="card mt-6 p-5 md:p-6">
            <h2 class="font-body text-lg font-bold">{{ t('account.change_password') }}</h2>
            <form class="mt-4 grid gap-4" @submit.prevent="savePassword">
                <FormField v-model="password.current_password" :label="t('account.current_password')" type="password" required maxlength="255" autocomplete="current-password" :error="password.errors.current_password" />
                <FormField v-model="password.password" :label="t('account.new_password')" type="password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}" minlength="8" maxlength="255" :title="t('auth.password_hint')" autocomplete="new-password" :hint="t('auth.password_hint')" :error="password.errors.password" />
                <FormField v-model="password.password_confirmation" :label="t('auth.password_confirmation')" type="password" required minlength="8" maxlength="255" autocomplete="new-password" :error="password.errors.password_confirmation" />
                <div>
                    <button type="submit" class="btn btn-primary" :disabled="password.processing">{{ password.processing ? t('common.saving') : t('account.update_password') }}</button>
                    <span v-if="password.recentlySuccessful" class="ml-3 text-sm text-success" role="status">{{ t('account.saved') }}</span>
                </div>
            </form>
        </section>

        <section v-if="tokens !== undefined" class="card mt-6 p-5 md:p-6" aria-labelledby="tokens-title">
            <h2 id="tokens-title" class="font-body text-lg font-bold">{{ t('account.tokens_title') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ t('account.tokens_intro') }}</p>
            <div v-if="newToken" class="mt-4 rounded-lg bg-success-soft p-3 text-sm" role="status">
                <p class="font-semibold text-success">{{ t('account.token_created') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="min-w-0 flex-1 break-all rounded bg-surface p-2 text-xs">{{ newToken }}</code>
                    <button type="button" class="btn btn-sm" @click="copyToken"><Icon name="check" v-if="copied" :size="16" />{{ copied ? t('account.copied') : t('account.copy') }}</button>
                </div>
            </div>
            <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="createToken">
                <FormField v-model="tokenForm.name" class="min-w-0 flex-1" :label="t('account.token_name')" :hint="t('account.token_name_hint')" :error="tokenForm.errors.name" required minlength="2" maxlength="60" />
                <button type="submit" class="btn btn-primary" :disabled="tokenForm.processing || !tokenForm.name.trim()"><Icon name="key" :size="18" />{{ t('account.token_create') }}</button>
            </form>
            <EmptyState v-if="!tokens?.length" icon="key" :title="t('account.no_tokens')" compact class="mt-4" />
            <ul v-else class="mt-4 divide-y divide-border text-sm">
                <li v-for="tk in tokens" :key="tk.id" class="flex flex-wrap items-center gap-3 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ tk.name }}</p>
                        <p class="text-xs text-muted">{{ t('account.token_created_at', { time: dateTime(tk.created_at) }) }} · {{ tk.last_used_at ? t('account.token_last_used', { time: dateTime(tk.last_used_at) }) : t('account.token_never_used') }}</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" @click="revoking = tk">{{ t('account.token_revoke') }}</button>
                </li>
            </ul>
        </section>

        <ConfirmDialog
            :open="!!revoking"
            :title="t('account.token_revoke_title')"
            :message="t('account.token_revoke_message', { name: revoking?.name || '' })"
            :confirm-text="t('account.token_revoke')"
            danger
            @confirm="revoke"
            @cancel="revoking = null"
        />
    </div>
</template>
