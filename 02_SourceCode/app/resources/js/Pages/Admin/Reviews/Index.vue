<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StarRating from '@/Components/StarRating.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import ModerateDialog from '@/Components/Admin/ModerateDialog.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';
import { REVIEW_STATUSES, codeOptions } from '@/Components/Admin/options';

const props = defineProps({
    reviews: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const { dateTime } = useFormat();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.reviews));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'status', type: 'select', label: t('common.status'), placeholder: te('review_status', 'pending'), options: [{ value: 'all', label: t('common.all') }, ...codeOptions(REVIEW_STATUSES, te, 'review_status')] },
    { name: 'subject_type', type: 'select', label: t('admin.reviews.subject'), options: ['artifact', 'tour', 'museum'].map((c) => ({ value: c, label: te('subject_type', c) })) },
]);
const pending = ref(null); // {review, status}
const processing = ref(false);

function moderate(review, status) {
    pending.value = { review, status };
}
function confirm(note) {
    const { review, status } = pending.value;
    processing.value = true;
    router.patch(lr(`/quan-tri/danh-gia/${review.id}`), { status, review_note: note || null }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            pending.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('admin.reviews.title')" />
    <PageHeader :eyebrow="t('admin.title')" :title="t('admin.reviews.title')" :description="t('admin.reviews.description')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />

    <EmptyState v-if="!rows.length" icon="star" :title="t('admin.reviews.empty')" />
    <ul v-else class="space-y-3">
        <li v-for="r in rows" :key="r.id" class="card p-4">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 text-sm">
                        <StarRating :value="r.rating" readonly :size="16" />
                        <span class="font-semibold">{{ r.user?.name || t('reviews.anonymous') }}</span>
                        <span class="text-xs text-muted">{{ dateTime(r.created_at) }}</span>
                        <StatusBadge :status="r.status" group="review_status" />
                    </p>
                    <p class="mt-1 text-xs text-muted">
                        {{ te('subject_type', r.subject?.type || r.subject_type) }}:
                        <a v-if="r.subject?.url" :href="r.subject.url" target="_blank" rel="noopener" class="link">{{ r.subject.title }}</a>
                        <span v-else>{{ r.subject?.title || `#${r.subject_id}` }}</span>
                    </p>
                    <p v-if="r.title" class="mt-2 font-semibold">{{ r.title }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ r.body }}</p>
                    <p v-if="r.review_note" class="mt-2 rounded bg-surface-2 p-2 text-xs"><strong>{{ t('admin.reviews.note') }}:</strong> {{ r.review_note }}</p>
                </div>
                <div class="flex gap-2">
                    <button v-if="r.status !== 'approved'" type="button" class="btn btn-sm btn-primary" @click="moderate(r, 'approved')"><Icon name="check" :size="16" />{{ t('admin.reviews.approve') }}</button>
                    <button v-if="r.status !== 'rejected'" type="button" class="btn btn-sm" @click="moderate(r, 'rejected')"><Icon name="close" :size="16" />{{ t('admin.reviews.reject') }}</button>
                </div>
            </div>
        </li>
    </ul>
    <Pagination :paginator="reviews" />

    <ModerateDialog
        :open="!!pending"
        :title="pending?.status === 'approved' ? t('admin.reviews.approve_title') : t('admin.reviews.reject_title')"
        :confirm-text="pending?.status === 'approved' ? t('admin.reviews.approve') : t('admin.reviews.reject')"
        :danger="pending?.status === 'rejected'"
        :note-label="t('admin.reviews.note')"
        :processing="processing"
        @update:open="(v) => !v && (pending = null)"
        @confirm="confirm"
    />
</template>
