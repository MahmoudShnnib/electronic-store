<script setup lang="ts">
import { Rate } from 'ant-design-vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        rating: number | null;
        count?: number;
        size?: 'sm' | 'md';
    }>(),
    { count: undefined, size: 'sm' },
);

/** `Rate` rounds to the nearest half itself, but it will not take null. */
const value = computed(() => props.rating ?? 0);
</script>

<template>
    <div class="flex items-center gap-1.5">
        <Rate :value="value" disabled allow-half :class="['store-rate', size === 'sm' ? 'store-rate-sm' : 'store-rate-md']" />
        <span v-if="count !== undefined" class="store-rate-count">({{ count }})</span>
    </div>
</template>
