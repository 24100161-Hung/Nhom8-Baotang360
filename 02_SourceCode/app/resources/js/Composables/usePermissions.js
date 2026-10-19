import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/** Cờ quyền phía giao diện (chỉ ẩn/hiện; máy chủ vẫn kiểm tra quyền). */
export function usePermissions() {
    const page = usePage();
    const user = computed(() => page.props.auth?.user || null);
    const role = computed(() => user.value?.role || null);
    const isAdmin = computed(() => role.value === 'admin');
    const isCurator = computed(() => role.value === 'curator');
    const canAccessAdmin = computed(() => ['admin', 'curator'].includes(role.value));
    const isPremium = computed(() => !!user.value?.membership?.is_active);
    const adminHome = computed(() => '/quan-tri');
    return { user, role, isAdmin, isCurator, canAccessAdmin, isPremium, adminHome };
}
