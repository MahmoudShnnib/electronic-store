<script setup lang="ts">
defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    tone?: 'default' | 'success' | 'warning' | 'danger';
}>();

const toneClasses: Record<string, string> = {
    default: 'text-gray-900 dark:text-gray-100',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-red-600 dark:text-red-400',
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <p class="truncate text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold" :class="toneClasses[tone ?? 'default']">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ hint }}</p>
    </div>
</template>
