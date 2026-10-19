<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Thẻ bài blog: ảnh bìa, danh mục, tác giả, ngày, lượt xem. */
defineProps({
    post: { type: Object, required: true },
    headingLevel: { type: Number, default: 3 },
    eager: { type: Boolean, default: false },
});
const { t } = useT();
const { date, number } = useFormat();
</script>

<template>
    <article class="card card-hover flex h-full flex-col overflow-hidden">
        <Link :href="post.url" tabindex="-1" aria-hidden="true">
            <CoverImage :src="post.cover_url" :alt="post.title" icon="book" ratio="aspect-[16/9]" :eager="eager" />
        </Link>
        <div class="flex flex-1 flex-col gap-1.5 p-4">
            <p v-if="post.category" class="eyebrow">{{ post.category.name }}</p>
            <component :is="`h${headingLevel}`" class="text-lg font-bold leading-snug">
                <Link :href="post.url" class="hover:text-primary">{{ post.title }}</Link>
            </component>
            <p class="line-clamp-3 text-sm text-muted">{{ post.excerpt }}</p>
            <p class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 pt-2 text-xs text-muted">
                <span v-if="post.author?.name">{{ post.author.name }}</span>
                <time v-if="post.published_at" :datetime="post.published_at">{{ date(post.published_at) }}</time>
                <span class="inline-flex items-center gap-1"><Icon name="eye" :size="14" />{{ t('blog.views_n', { n: number(post.view_count ?? 0) }) }}</span>
            </p>
        </div>
    </article>
</template>
