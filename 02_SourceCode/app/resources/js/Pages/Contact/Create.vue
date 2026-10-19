<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    museum: { type: Object, default: null },
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();

// Thông tin bảo tàng: ưu tiên prop trang, dự phòng shared props app.museum
const m = computed(() => ({ ...(page.props.app?.museum || {}), ...(props.museum || {}) }));
const user = computed(() => page.props.auth?.user || null);
const form = useForm({ name: user.value?.name || '', email: user.value?.email || '', phone: user.value?.phone || '', subject: '', body: '', website: '' });
const telHref = computed(() => (m.value.phone ? `tel:${String(m.value.phone).replace(/[^\d+]/g, '')}` : null));
const submit = () => form.post(lr('/lien-he'), { preserveScroll: true, onSuccess: () => form.reset('subject', 'body', 'website') });
</script>

<template>
    <PageMeta :title="t('contact.title')" :description="t('contact.intro')" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.contact')" :title="t('contact.title')" :description="t('contact.intro')" />
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <form class="card grid gap-4 p-4 sm:grid-cols-2 md:p-6" @submit.prevent="submit">
                <FormField v-model="form.name" :label="t('contact.name')" autocomplete="name" :error="form.errors.name" required minlength="2" maxlength="120" />
                <FormField v-model="form.email" type="email" :label="t('common.email')" autocomplete="email" :error="form.errors.email" required maxlength="190" />
                <FormField v-model="form.phone" type="tel" :label="`${t('common.phone')} (${t('common.optional')})`" autocomplete="tel" :error="form.errors.phone" pattern="\+?[0-9 .\-]{9,20}" maxlength="20" inputmode="tel" :title="t('validation_hint.phone')" />
                <FormField v-model="form.subject" :label="t('contact.subject')" :error="form.errors.subject" required minlength="3" maxlength="190" />
                <FormField v-model="form.body" class="sm:col-span-2" type="textarea" :rows="6" :label="t('contact.body')" :error="form.errors.body" required minlength="10" maxlength="5000" />
                <!-- Bẫy bot (honeypot): người dùng không thấy, không tab tới -->
                <div class="sr-only" aria-hidden="true">
                    <label for="contact-website">Website</label>
                    <input id="contact-website" v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
                </div>
                <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
                    <button type="submit" class="btn btn-primary" :disabled="form.processing"><Icon name="send" :size="18" />{{ form.processing ? t('common.loading') : t('contact.send') }}</button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-success" role="status">{{ t('contact.sent') }}</span>
                    <p class="w-full text-xs text-muted">{{ t('contact.privacy') }}</p>
                </div>
            </form>

            <aside class="card h-fit p-5 text-sm">
                <h2 class="font-body text-lg font-bold">{{ m.name || 'Naturalis Biodiversity Center' }}</h2>
                <ul class="mt-3 space-y-3">
                    <li v-if="m.address" class="flex items-start gap-2"><Icon name="pin" :size="18" class="mt-0.5 text-primary" /><span>{{ m.address }}</span></li>
                    <li v-if="m.phone" class="flex items-center gap-2"><Icon name="phone" :size="18" class="text-primary" /><a :href="telHref" class="link">{{ m.phone }}</a></li>
                    <li v-if="m.email" class="flex items-center gap-2"><Icon name="mail" :size="18" class="text-primary" /><a :href="`mailto:${m.email}`" class="link break-all">{{ m.email }}</a></li>
                    <li v-if="m.website" class="flex items-center gap-2"><Icon name="globe" :size="18" class="text-primary" /><a :href="m.website" target="_blank" rel="noopener noreferrer" class="link break-all">{{ m.website }}</a></li>
                    <li class="flex items-start gap-2"><Icon name="clock" :size="18" class="mt-0.5 text-primary" /><span>{{ m.opening_hours || t('contact.hours') }}</span></li>
                </ul>
            </aside>
        </div>
    </div>
</template>
