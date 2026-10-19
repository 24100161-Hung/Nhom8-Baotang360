<script setup>
import FlashToast from '@/Components/FlashToast.vue';
import AcademicFooter from '@/Components/AcademicFooter.vue';
import { useT } from '@/Composables/useT';

// Layout toàn màn hình cho trang tham quan 360°: không header; chỉ skip link, thông báo và thanh học thuật nhỏ gọn (h-5) ở đáy
const { t } = useT();
</script>

<template>
    <div class="min-h-[100dvh] bg-viewer-bg text-viewer-text">
        <a href="#pano-main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[1400] btn btn-primary">
            {{ t('common.skip_to_content') }}
        </a>
        <FlashToast />
        <main id="pano-main" tabindex="-1" class="outline-none">
            <slot />
        </main>
        <AcademicFooter variant="viewer" />
    </div>
</template>
