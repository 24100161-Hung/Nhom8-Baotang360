<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import StarRating from '@/Components/StarRating.vue';
import FormField from '@/Components/FormField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Đánh giá 1–5 sao + nhận xét cho hiện vật/tour: danh sách đã duyệt + form gửi (chờ kiểm duyệt). */
const props = defineProps({
    subjectType: { type: String, required: true }, // artifact | tour
    subjectId: { type: Number, required: true },
    reviews: { type: Array, default: () => [] },
    myReview: { type: Object, default: null },
    ratingAvg: { type: [Number, String], default: null },
    ratingCount: { type: Number, default: 0 },
});
const page = usePage();
const { t } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();

const user = computed(() => page.props.auth?.user || null);
const form = useForm({ subject_type: props.subjectType, subject_id: props.subjectId, rating: 0, title: '', body: '' });
const submit = () => form.post(lr('/danh-gia'), { preserveScroll: true, onSuccess: () => form.reset('title', 'body', 'rating') });
const initial = (name) => (name || '?').charAt(0).toUpperCase();
</script>

<template>
    <section aria-labelledby="reviews-title">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h2 id="reviews-title" class="section-title">{{ t('reviews.title') }}</h2>
            <p v-if="ratingCount" class="flex items-center gap-2 text-sm">
                <StarRating :value="ratingAvg" readonly />
                <span class="font-semibold">{{ Number(ratingAvg || 0).toFixed(1) }}</span>
                <span class="text-muted">{{ t('reviews.count', { n: ratingCount }) }}</span>
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div>
                <EmptyState v-if="!reviews.length" icon="star" :title="t('reviews.empty')" compact />
                <ul v-else class="space-y-3">
                    <li v-for="r in reviews" :key="r.id" class="card p-4">
                        <div class="flex items-center gap-3">
                            <img v-if="r.user?.avatar_url" :src="r.user.avatar_url" alt="" class="h-9 w-9 rounded-full object-cover" loading="lazy" />
                            <span v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-soft text-sm font-bold text-primary" aria-hidden="true">{{ initial(r.user?.name) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ r.user?.name || t('reviews.anonymous') }}</p>
                                <p class="text-xs text-muted"><time :datetime="r.created_at">{{ date(r.created_at) }}</time></p>
                            </div>
                            <StarRating :value="r.rating" readonly :size="16" />
                        </div>
                        <p v-if="r.title" class="mt-2 font-semibold">{{ r.title }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ r.body }}</p>
                    </li>
                </ul>
            </div>

            <aside class="card h-fit p-4">
                <template v-if="myReview">
                    <h3 class="font-body text-base font-bold">{{ t('reviews.mine') }}</h3>
                    <div class="mt-2 flex items-center gap-2">
                        <StarRating :value="myReview.rating" readonly :size="16" />
                        <StatusBadge :status="myReview.status" group="review_status" />
                    </div>
                    <p v-if="myReview.title" class="mt-2 font-semibold">{{ myReview.title }}</p>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ myReview.body }}</p>
                    <p v-if="myReview.status === 'pending'" class="mt-2 text-xs text-muted">{{ t('reviews.pending_note') }}</p>
                </template>
                <template v-else-if="user">
                    <h3 class="font-body text-base font-bold">{{ t('reviews.write') }}</h3>
                    <form class="mt-3 space-y-3" @submit.prevent="submit">
                        <StarRating v-model="form.rating" :label="t('reviews.your_rating')" :size="22" required />
                        <p v-if="form.errors.rating" class="field-error" role="alert">{{ form.errors.rating }}</p>
                        <FormField v-model="form.title" :label="`${t('reviews.review_title')} (${t('common.optional')})`" :error="form.errors.title" maxlength="150" />
                        <FormField v-model="form.body" type="textarea" :rows="4" :label="t('reviews.body')" :error="form.errors.body" required minlength="5" maxlength="2000" />
                        <p v-if="form.errors.subject_id" class="field-error" role="alert">{{ form.errors.subject_id }}</p>
                        <button type="submit" class="btn btn-primary w-full" :disabled="form.processing || !form.rating">{{ form.processing ? t('common.saving') : t('reviews.submit') }}</button>
                        <p class="text-xs text-muted">{{ t('reviews.moderation_note') }}</p>
                    </form>
                </template>
                <template v-else>
                    <h3 class="font-body text-base font-bold">{{ t('reviews.write') }}</h3>
                    <p class="mt-2 text-sm text-muted">{{ t('reviews.login_required') }}</p>
                    <Link :href="lr('/dang-nhap')" class="btn btn-primary mt-3 w-full">{{ t('nav.login') }}</Link>
                </template>
            </aside>
        </div>
    </section>
</template>
