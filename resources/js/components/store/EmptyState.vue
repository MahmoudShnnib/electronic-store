<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PackageOpen } from 'lucide-vue-next';

defineProps<{
    message: string;
    actionLabel?: string;
    actionHref?: string;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-border py-16 text-center">
        <PackageOpen class="h-12 w-12 text-muted-foreground" />
        <p class="text-muted-foreground">{{ message }}</p>
        <Link
            v-if="actionLabel && actionHref"
            :href="actionHref"
            class="rounded-lg bg-black px-4 py-2 text-sm font-semibold text-white transition hover:opacity-85 dark:bg-white dark:text-black"
        >
            {{ actionLabel }}
        </Link>
    </div>
</template>
