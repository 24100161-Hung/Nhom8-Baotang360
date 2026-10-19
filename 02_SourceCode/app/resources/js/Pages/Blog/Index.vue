<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PostCard from '@/Components/PostCard.vue';
import Pagination from '@/Components/Pagination.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';

const props = defineProps({
    posts: { type: Object, default: () => ({ data: [] }) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
const { t } = useT();
const lr = useLocaleRoute();

const rows = computed(() => rowsOf(props.posts));
const active = computed(() => props.filters?.['danh-muc'] || props.filters?.danh_muc || props.filters?.category || '');
const q = ref(props.filters?.q || '');
watch(() => props.filters?.q, (v) => (q.value = v || ''));
const total = computed(() => props.categories.reduce((s, c) => s + Number(c.count || 0), 0));

function catHref(slug) {
    return slug ? lr(`/blog?danh-muc=${encodeURIComponent(slug)}`) : lr('/blog');
}
function search() {
    const query = {};
    if (active.value) query['danh-muc'] = active.value;
    if (q.value.trim()) query.q = q.value.trim();
    router.get(lr('/blog'), query, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <PageMeta :title="t('blog.title')" :description="t('blog.intro')" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.blog')" :title="t('blog.title')" :description="t('blog.intro')" />

        <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <nav class="flex flex-wrap gap-2" :aria-label="t('blog.categories')">
                <Link :href="catHref('')" class="tab" :aria-current="!active ? 'page' : undefined" preserve-scroll>
                    {{ t('blog.all') }}<span v-if="total" class="ml-1 opacity-75">({{ total }})</span>
                </Link>
                <Link v-for="c in categories" :key="c.slug" :href="catHref(c.slug)" class="tab" :aria-current="active === c.slug ? 'page' : undefined" preserve-scroll>
                    {{ c.name }}<span class="ml-1 opacity-75">({{ c.count ?? 0 }})</span>
                </Link>
            </nav>
            <form role="search" class="flex gap-2 md:w-80" @submit.prevent="search">
                <label for="blog-q" class="sr-only">{{ t('blog.search') }}</label>
                <input id="blog-q" v-model="q" type="search" maxlength="200" class="input" :placeholder="t('blog.search')" />
                <button type="submit" class="btn btn-icon btn-primary shrink-0" :aria-label="t('nav.search')"><Icon name="search" :size="18" /></button>
            </form>
        </div>

        <EmptyState v-if="!rows.length" icon="book" :title="t('blog.empty')" />
        <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <PostCard v-for="(p, i) in rows" :key="p.id ?? p.slug" :post="p" :heading-level="2" :eager="i === 0" />
        </div>
        <Pagination :paginator="posts" />
    </div>
</template>
