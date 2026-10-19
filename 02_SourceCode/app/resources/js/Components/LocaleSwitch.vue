<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

const page = usePage();
const { t } = useT();

const other = computed(() => {
    const list = page.props.locales || [
        { code: 'vi', label: 'Tiếng Việt' },
        { code: 'en', label: 'English' },
    ];
    return list.find((l) => l.code !== page.props.locale) || list[0];
});

const href = computed(() => page.props.currentUrlOtherLocale || `/${other.value.code}`);

// Đổi ngôn ngữ không tải lại trang: Inertia visit giữ state (khung 360°, giỏ hàng, vị trí cuộn);
// <html lang> được đồng bộ trong app.js. Liên kết vẫn là <a href> thật để hoạt động khi chưa có JS.
function switchLocale(e) {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    router.visit(href.value, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <a
        :href="href"
        class="btn btn-ghost btn-sm"
        :hreflang="other.code"
        :lang="other.code"
        :aria-label="t('common.switch_language', { language: other.label })"
        @click="switchLocale"
    >
        <Icon name="globe" :size="18" />
        <span class="uppercase">{{ other.code }}</span>
    </a>
</template>
