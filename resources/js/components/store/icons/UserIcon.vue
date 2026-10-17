<script setup lang="ts">
/** Storefront nav icon: person in a circle. `filled` (active) makes the person solid. */
defineProps<{ filled?: boolean }>();
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9.5" />
        <circle cx="12" cy="9.5" r="3" :fill="filled ? 'currentColor' : 'none'" />
        <path v-if="filled" d="M5.6 18.9C6.9 16.7 9.3 15.3 12 15.3s5.1 1.4 6.4 3.6A9.5 9.5 0 0 1 5.6 18.9Z" fill="currentColor" />
        <path v-else d="M5.6 18.9C6.9 16.7 9.3 15.3 12 15.3s5.1 1.4 6.4 3.6" />
    </svg>
</template>
