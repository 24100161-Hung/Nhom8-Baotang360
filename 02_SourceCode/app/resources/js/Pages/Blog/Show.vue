<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import CoverImage from '@/Components/CoverImage.vue';
import PostCard from '@/Components/PostCard.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    post: { type: Object, default: () => ({}) },
});
const { t } = useT();
const { longDate, number } = useFormat();
const lr = useLocaleRoute();
const p = computed(() => props.post || {});
</script>

<template>
    <PageMeta :title="p.title" :description="p.excerpt" :image="p.cover_url" />
    <article class="container-page max-w-4xl py-8">
        <Breadcrumbs
            :items="[
                { label: t('nav.blog'), href: lr('/blog') },
                ...(p.category ? [{ label: p.category.name, href: lr(`/blog?danh-muc=${p.category.slug}`) }] : []),
                { label: p.title },
            ]"
            class="mb-4"
        />
        <header>
            <p v-if="p.category" class="eyebrow">{{ p.category.name }}</p>
            <h1 class="mt-1 text-3xl font-bold md:text-4xl">{{ p.title }}</h1>
            <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                <span v-if="p.author?.name" class="inline-flex items-center gap-1"><Icon name="user" :size="16" />{{ p.author.name }}</span>
                <time v-if="p.published_at" :datetime="p.published_at" class="inline-flex items-center gap-1"><Icon name="calendar" :size="16" />{{ longDate(p.published_at) }}</time>
                <span class="inline-flex items-center gap-1"><Icon name="eye" :size="16" />{{ t('blog.views_n', { n: number(p.view_count ?? 0) }) }}</span>
            </p>
        </header>
        <CoverImage v-if="p.cover_url" :src="p.cover_url" :alt="p.title" icon="book" ratio="aspect-[16/9]" eager class="mt-6 rounded-ds-lg" />
        <p v-if="p.excerpt" class="mt-6 text-lg text-muted">{{ p.excerpt }}</p>
        <div class="prose-museum mt-6" v-html="p.body_html"></div>
        <div class="mt-8">
            <Link :href="lr('/blog')" class="btn"><Icon name="chevronLeft" :size="18" />{{ t('blog.back') }}</Link>
        </div>
    </article>

    <section v-if="p.related?.length" class="container-page mt-6" aria-labelledby="related-title">
        <h2 id="related-title" class="section-title mb-5">{{ t('blog.related') }}</h2>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <PostCard v-for="r in p.related" :key="r.slug" :post="r" />
        </div>
    </section>
</template>
