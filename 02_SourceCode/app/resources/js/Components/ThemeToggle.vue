<script setup>
import { onMounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useTheme } from '@/Composables/useTheme';
import { useT } from '@/Composables/useT';

const { theme, toggle, syncFromUser } = useTheme();
const { t } = useT();

onMounted(syncFromUser);
</script>

<template>
    <button
        type="button"
        class="btn btn-ghost btn-icon"
        :aria-label="theme === 'dark' ? t('common.theme_light') : t('common.theme_dark')"
        :title="theme === 'dark' ? t('common.theme_light') : t('common.theme_dark')"
        @click="toggle"
    >
        <Icon :name="theme === 'dark' ? 'sun' : 'moon'" />
    </button>
</template>
