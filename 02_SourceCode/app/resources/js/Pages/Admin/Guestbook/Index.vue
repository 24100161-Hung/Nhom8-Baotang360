<script setup>
import { reactive, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';
import { GUESTBOOK_STATUSES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    entries: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { dateTime } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.entries));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), options: codeOptions(GUESTBOOK_STATUSES, te, 'guestbook_status') },
]);
const saving = reactive({});
function setStatus(entry, status) {
    saving[entry.id] = true;
    router.patch(lr(`/quan-tri/so-luu-but/${entry.id}`), { status }, { preserveScroll: true, onFinish: () => (saving[entry.id] = false) });
}
</script>

<template>
    <Head :title="t('admin.guestbook.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.guestbook.title')" :description="t('admin.guestbook.description')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />
    <EmptyState v-if="!rows.length" icon="edit" :title="t('admin.guestbook.empty')" />
    <ul v-else class="grid gap-3 md:grid-cols-2">
        <li v-for="e in rows" :key="e.id" class="card flex flex-col p-4">
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold">{{ e.display_name || e.user?.name || t('reviews.anonymous') }}</span>
                <span class="text-xs text-muted">{{ dateTime(e.created_at) }}</span>
                <span v-if="e.locale" class="badge">{{ e.locale.toUpperCase() }}</span>
                <StatusBadge :status="e.status" group="guestbook_status" />
            </p>
            <p class="mt-2 flex-1 whitespace-pre-line text-sm">{{ e.message }}</p>
            <div class="mt-3 flex flex-wrap justify-end gap-2">
                <button v-if="e.status !== 'approved'" type="button" class="btn btn-sm btn-primary" :disabled="saving[e.id]" @click="setStatus(e, 'approved')"><Icon name="check" :size="16" />{{ t('admin.guestbook.approve') }}</button>
                <button v-if="e.status !== 'hidden'" type="button" class="btn btn-sm" :disabled="saving[e.id]" @click="setStatus(e, 'hidden')"><Icon name="eye" :size="16" />{{ t('admin.guestbook.hide') }}</button>
            </div>
        </li>
    </ul>
    <Pagination :paginator="entries" />
</template>
