<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useT } from '@/Composables/useT';

// Chân trang học thuật bắt buộc trên mọi trang (mã học phần, nhóm, trường, cảnh báo dữ liệu/thanh toán, phiên bản).
// variant: "full" (PublicLayout), "compact" (Admin/Auth/Error), "viewer" (thanh nhỏ gọn nổi trên trình xem 360°)
defineProps({
    variant: { type: String, default: 'compact' },
});
const page = usePage();
const { t } = useT();
const app = computed(() => page.props.app || {});
const version = computed(() => app.value.version || '1.0.0');
// Thanh trình xem 360° chỉ một dòng: đặt mã học phần, nhóm, phiên bản lên đầu để vẫn thấy khi bị cắt trên màn hình nhỏ
const viewerText = computed(() =>
    [
        `CSE703073 – ${t('footer.course_name')}`,
        t('footer.team_full'),
        `${t('footer.version_label')} ${version.value}`,
        t('footer.school'),
        t('footer.disclaimer'),
    ].join(' · '),
);
</script>

<template>
    <div
        v-if="variant === 'viewer'"
        class="h-5 truncate bg-black px-3 text-center text-[11px] leading-5 text-white/85"
        data-academic-footer
        role="contentinfo"
        :aria-label="t('footer.label')"
        :title="viewerText"
    >
        {{ viewerText }}
    </div>
    <div v-else class="text-center text-xs leading-relaxed text-muted" :class="variant === 'full' ? '' : 'border-t border-border px-4 py-4'" data-academic-footer>
        <p>
            <span class="font-semibold text-text">CSE703073 – {{ t('footer.course_name') }}</span>
            · {{ t('footer.team_full') }} · {{ t('footer.school') }}
        </p>
        <p>{{ t('footer.disclaimer') }}</p>
        <p>{{ t('footer.version_label') }} {{ version }}</p>
    </div>
</template>
