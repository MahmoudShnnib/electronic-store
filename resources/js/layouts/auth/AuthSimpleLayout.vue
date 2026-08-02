<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-white p-6 text-black dark:bg-neutral-950 dark:text-white md:p-10">
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="route('home')" class="flex items-baseline gap-0.5 text-2xl font-bold lowercase tracking-tight">
                        <span class="text-3xl leading-none">◆</span>
                        <span>{{ appName }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
