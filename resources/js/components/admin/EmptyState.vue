<script setup lang="ts">
import { PackageOpen } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-900"
    >
        <PackageOpen class="h-10 w-10 text-gray-400" />
        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ title ?? 'Nothing here yet' }}</h3>
        <p v-if="description" class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
        <div class="mt-4">
            <slot />
        </div>
    </div>
</template>
