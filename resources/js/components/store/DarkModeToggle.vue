<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { updateAppearance } = useAppearance();

// Reflects the class the boot script / initializeTheme() already applied,
// which also resolves the 'system' preference to an actual light/dark value.
const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggle() {
    isDark.value = !isDark.value;
    updateAppearance(isDark.value ? 'dark' : 'light');
}
</script>

<template>
    <button
        type="button"
        class="rounded-md p-2 text-black transition hover:bg-[#F5F5F5] dark:text-white dark:hover:bg-neutral-900"
        :aria-label="t('common.toggle_theme')"
        @click="toggle"
    >
        <Sun v-if="isDark" class="h-5 w-5" />
        <Moon v-else class="h-5 w-5" />
    </button>
</template>
