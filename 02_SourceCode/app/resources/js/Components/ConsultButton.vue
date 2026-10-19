<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

// Nút "Tư vấn ngay" dính góc phải (FR-34): mở hộp thoại nhanh với liên hệ, điện thoại, email (không có chat thật)
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const open = ref(false);
const trigger = ref(null);
const closeBtn = ref(null);
const museum = computed(() => page.props.app?.museum || {});
const telHref = computed(() => (museum.value.phone ? `tel:${String(museum.value.phone).replace(/[^\d+]/g, '')}` : null));

function close() {
    open.value = false;
    nextTick(() => trigger.value?.focus());
}
function onKey(e) {
    if (e.key === 'Escape') close();
}
watch(open, (v) => {
    if (v) {
        document.addEventListener('keydown', onKey);
        nextTick(() => closeBtn.value?.focus());
    } else {
        document.removeEventListener('keydown', onKey);
    }
});
router.on('navigate', () => (open.value = false));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="fixed bottom-4 right-4 z-[1050] flex flex-col items-end gap-3 print:hidden">
        <div v-if="open" id="consult-dialog" class="card w-[min(20rem,calc(100vw-2rem))] p-4" role="dialog" aria-modal="false" aria-labelledby="consult-title">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 id="consult-title" class="font-body text-base font-bold">{{ t('consult.title') }}</h2>
                    <p class="text-xs text-muted">{{ t('consult.subtitle') }}</p>
                </div>
                <button ref="closeBtn" type="button" class="btn btn-ghost btn-icon btn-sm" :aria-label="t('common.close')" @click="close"><Icon name="close" :size="16" /></button>
            </div>
            <ul class="mt-3 space-y-2 text-sm">
                <li>
                    <Link :href="lr('/lien-he')" class="flex items-center gap-3 rounded-lg border border-border p-2.5 hover:bg-surface-2" @click="open = false">
                        <Icon name="message" :size="18" class="text-primary" />
                        <span><span class="block font-semibold">{{ t('consult.contact_form') }}</span><span class="text-xs text-muted">{{ t('consult.contact_form_hint') }}</span></span>
                    </Link>
                </li>
                <li v-if="telHref">
                    <a :href="telHref" class="flex items-center gap-3 rounded-lg border border-border p-2.5 hover:bg-surface-2">
                        <Icon name="phone" :size="18" class="text-primary" />
                        <span><span class="block font-semibold">{{ museum.phone }}</span><span class="text-xs text-muted">{{ t('consult.phone_hint') }}</span></span>
                    </a>
                </li>
                <li v-if="museum.email">
                    <a :href="`mailto:${museum.email}`" class="flex items-center gap-3 rounded-lg border border-border p-2.5 hover:bg-surface-2">
                        <Icon name="mail" :size="18" class="text-primary" />
                        <span class="min-w-0"><span class="block truncate font-semibold">{{ museum.email }}</span><span class="text-xs text-muted">{{ t('consult.email_hint') }}</span></span>
                    </a>
                </li>
                <li>
                    <Link :href="lr('/ve')" class="flex items-center gap-3 rounded-lg border border-border p-2.5 hover:bg-surface-2" @click="open = false">
                        <Icon name="ticket" :size="18" class="text-primary" />
                        <span class="font-semibold">{{ t('consult.tickets') }}</span>
                    </Link>
                </li>
            </ul>
            <p class="mt-3 text-[11px] text-muted">{{ t('consult.hours') }}</p>
        </div>
        <button
            ref="trigger"
            type="button"
            class="btn btn-accent shadow-ds-lg"
            :aria-expanded="open"
            aria-controls="consult-dialog"
            @click="open ? close() : (open = true)"
        >
            <Icon :name="open ? 'close' : 'message'" :size="18" />{{ t('consult.button') }}
        </button>
    </div>
</template>
