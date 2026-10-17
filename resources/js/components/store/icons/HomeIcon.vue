<script setup lang="ts">
/** Storefront nav icon: rounded house with an arched door. `filled` is the active state. */
defineProps<{ filled?: boolean }>();
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        :fill="filled ? 'currentColor' : 'none'"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path
            d="M3.5 10.2c0-.6.27-1.17.74-1.55l6.5-5.3a2 2 0 0 1 2.52 0l6.5 5.3c.47.38.74.95.74 1.55v8.3a2 2 0 0 1-2 2H15v-4.25a3 3 0 0 0-6 0v4.25H5.5a2 2 0 0 1-2-2Z"
        />
    </svg>
</template>
