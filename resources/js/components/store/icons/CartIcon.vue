<script setup lang="ts">
/** Storefront nav icon: shopping cart with ring wheels. `filled` (active) makes the basket and wheels solid. */
defineProps<{ filled?: boolean }>();
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 3h1.1a1.4 1.4 0 0 1 1.36 1.07L7.2 15" />
        <path d="M4.6 6.5H21l-.6 5.3a2.6 2.6 0 0 1-2.3 2.3L7.2 15" :fill="filled ? 'currentColor' : 'none'" />
        <path d="M7.2 15 5.9 17.1M8 19h7.5" />
        <circle cx="6" cy="19" r="2" :fill="filled ? 'currentColor' : 'none'" />
        <circle cx="17.5" cy="19" r="2" :fill="filled ? 'currentColor' : 'none'" />
    </svg>
</template>
