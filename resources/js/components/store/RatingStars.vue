<script setup lang="ts">
import { Star } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        rating: number | null;
        count?: number;
        size?: 'sm' | 'md';
    }>(),
    { count: undefined, size: 'sm' },
);
</script>

<template>
    <div class="flex items-center gap-1" :aria-label="`${rating ?? 0} / 5`">
        <div class="flex">
            <Star
                v-for="i in 5"
                :key="i"
                :class="[
                    size === 'sm' ? 'h-3.5 w-3.5' : 'h-5 w-5',
                    (rating ?? 0) >= i - 0.5 ? 'fill-amber-400 text-amber-400' : 'fill-none text-gray-300 dark:text-gray-600',
                ]"
            />
        </div>
        <span v-if="count !== undefined" class="text-xs text-muted-foreground">({{ count }})</span>
    </div>
</template>
