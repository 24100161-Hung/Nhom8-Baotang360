<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';
import { CONTACT_STATUSES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    messages: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { dateTime } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.messages));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: codeOptions(CONTACT_STATUSES, te, 'contact_status') },
]);
const openId = ref(null);
const form = useForm({ status: 'read', reply_note: '' });

function open(m) {
    openId.value = openId.value === m.id ? null : m.id;
    form.status = m.status === 'new' ? 'read' : m.status;
    form.reply_note = m.reply_note || '';
    form.clearErrors();
}
function save(m, status) {
    form.status = status;
    form.patch(lr(`/quan-tri/lien-he/${m.id}`), { preserveScroll: true, onSuccess: () => (openId.value = null) });
}
</script>

<template>
    <Head :title="t('admin.contacts.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.contacts.title')" :description="t('admin.contacts.description')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <EmptyState v-if="!rows.length" icon="mail" :title="t('admin.contacts.empty')" />
    <ul v-else class="space-y-3">
        <li v-for="m in rows" :key="m.id" class="card p-4" :class="m.status === 'new' ? 'border-l-4 border-l-accent' : ''">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ m.subject }}</span>
                        <StatusBadge :status="m.status" group="contact_status" />
                    </p>
                    <p class="text-xs text-muted">
                        {{ m.name }} · <a :href="`mailto:${m.email}`" class="link">{{ m.email }}</a><template v-if="m.phone"> · {{ m.phone }}</template> · {{ dateTime(m.created_at) }}
                    </p>
                </div>
                <button type="button" class="btn btn-sm" :aria-expanded="openId === m.id" @click="open(m)"><Icon :name="openId === m.id ? 'chevronUp' : 'chevronDown'" :size="16" />{{ t('admin.contacts.handle') }}</button>
            </div>
            <p class="mt-2 whitespace-pre-line text-sm" :class="openId === m.id ? '' : 'line-clamp-2'">{{ m.body }}</p>
            <p v-if="m.reply_note && openId !== m.id" class="mt-2 rounded bg-surface-2 p-2 text-xs"><strong>{{ t('admin.contacts.reply_note') }}:</strong> {{ m.reply_note }}<template v-if="m.replied_by"> – {{ m.replied_by.name || m.replied_by }}</template></p>
            <form v-if="openId === m.id" class="mt-3 space-y-3 border-t border-border pt-3" @submit.prevent="save(m, 'replied')">
                <FormField v-model="form.reply_note" type="textarea" :rows="3" :label="t('admin.contacts.reply_note')" :hint="t('admin.contacts.reply_hint')" :error="form.errors.reply_note" required maxlength="3000" />
                <p v-if="form.errors.status" class="field-error">{{ form.errors.status }}</p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn btn-sm" :disabled="form.processing" @click="save(m, 'read')">{{ t('admin.contacts.mark_read') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary" :disabled="form.processing">{{ t('admin.contacts.mark_replied') }}</button>
                </div>
            </form>
        </li>
    </ul>
    <Pagination :paginator="messages" />
</template>
