<script setup lang="ts">
import { switchLocale, type AppLocale } from '@/i18n';
import { usePage } from '@inertiajs/vue3';
import { Languages } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as AppLocale);

function toggle() {
    switchLocale(locale.value === 'ar' ? 'en' : 'ar');
}
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-md p-2 text-sm font-medium text-black transition hover:bg-[#F5F5F5] dark:text-white dark:hover:bg-neutral-900"
        @click="toggle"
    >
        <Languages class="h-5 w-5" :stroke-width="2.5" />
        <span class="hidden sm:inline">{{ t('common.language') }}</span>
    </button>
</template>
