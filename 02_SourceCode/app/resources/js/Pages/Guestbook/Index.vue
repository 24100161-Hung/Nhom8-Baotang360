<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';

const props = defineProps({
    entries: { type: Object, default: () => ({ data: [] }) },
});
const page = usePage();
const { t } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.entries));
const user = computed(() => page.props.auth?.user || null);
const MAX_LEN = 1000;
const form = useForm({ display_name: user.value?.name || '', message: '', website: '' });
const submit = () => form.post(lr('/so-luu-but'), { preserveScroll: true, onSuccess: () => form.reset('message', 'website') });
</script>

<template>
    <PageMeta :title="t('guestbook.title')" :description="t('guestbook.intro')" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.guestbook')" :title="t('guestbook.title')" :description="t('guestbook.intro')" />
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <section :aria-label="t('guestbook.entries')">
                <EmptyState v-if="!rows.length" icon="edit" :title="t('guestbook.empty')" />
                <ul v-else class="grid gap-4 sm:grid-cols-2">
                    <li v-for="(e, i) in rows" :key="e.id ?? i" class="card p-4">
                        <p class="whitespace-pre-line leading-relaxed">“{{ e.message }}”</p>
                        <p class="mt-3 text-sm font-semibold">— {{ e.display_name || t('guestbook.anonymous') }}</p>
                        <p class="text-xs text-muted"><time :datetime="e.created_at">{{ date(e.created_at) }}</time></p>
                    </li>
                </ul>
                <Pagination :paginator="entries" />
            </section>

            <form class="card h-fit space-y-4 p-4 md:p-5" @submit.prevent="submit">
                <h2 class="font-body text-lg font-bold">{{ t('guestbook.write') }}</h2>
                <FormField v-model="form.display_name" :label="user ? `${t('guestbook.display_name')} (${t('common.optional')})` : t('guestbook.display_name')" :required="!user" minlength="2" maxlength="80" autocomplete="nickname" :error="form.errors.display_name" />
                <FormField v-model="form.message" type="textarea" :rows="5" :label="t('guestbook.message')" :hint="t('guestbook.chars', { n: form.message.length, max: MAX_LEN })" :error="form.errors.message" required minlength="3" :maxlength="MAX_LEN" />
                <div class="sr-only" aria-hidden="true">
                    <label for="gb-website">Website</label>
                    <input id="gb-website" v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
                </div>
                <button type="submit" class="btn btn-primary w-full" :disabled="form.processing || !form.message.trim() || form.message.length > MAX_LEN">
                    <Icon name="send" :size="18" />{{ form.processing ? t('common.loading') : t('guestbook.submit') }}
                </button>
                <p v-if="form.recentlySuccessful" class="text-sm text-success" role="status">{{ t('guestbook.pending') }}</p>
                <p class="text-xs text-muted">{{ t('guestbook.moderation_note') }}</p>
            </form>
        </div>
    </div>
</template>
