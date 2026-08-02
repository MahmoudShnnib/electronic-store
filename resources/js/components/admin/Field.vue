<script setup lang="ts">
defineProps<{
    label: string;
    error?: string;
    required?: boolean;
    hint?: string;
}>();
</script>

<template>
    <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ label }}<span v-if="required" class="text-red-500"> *</span>
        </span>
        <slot />
        <span v-if="hint && !error" class="mt-1 block text-xs text-gray-400">{{ hint }}</span>
        <span v-if="error" class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ error }}</span>
    </label>
</template>
