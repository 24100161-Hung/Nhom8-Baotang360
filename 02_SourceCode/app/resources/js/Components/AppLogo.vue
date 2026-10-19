<script setup>
defineProps({
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <svg width="34" height="34" viewBox="0 0 40 40" aria-hidden="true" focusable="false" class="shrink-0">
            <circle cx="20" cy="20" r="19" fill="var(--ds-primary)" />
            <ellipse cx="20" cy="20" rx="13" ry="5.5" fill="none" stroke="var(--ds-accent)" stroke-width="1.6" />
            <path d="M12 26c2-6 5-10 8-12 2 1.5 3 3.5 3.5 6M20 14v-3M17 11.5l3-1.5 3 1.5" stroke="var(--ds-on-primary)" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="27" cy="25" r="2" fill="var(--ds-on-primary)" />
        </svg>
        <span v-if="!compact" class="font-display text-xl font-bold tracking-tight">
            BaoTang<span class="text-primary">360</span>
        </span>
    </span>
</template>
