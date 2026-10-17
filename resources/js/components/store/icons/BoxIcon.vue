<script setup lang="ts">
import { useId } from 'vue';

/**
 * Storefront nav icon: taped parcel with a handle slot. Outline normally;
 * `filled` (the active state) is solid with the top face and handle knocked
 * out. The cut-outs are deliberately wide — narrower ones closed up at 20px
 * and the parcel read as a blob.
 */
defineProps<{ filled?: boolean }>();

const maskId = `box-icon-${useId()}`;
const outline =
    'M10.85 3.02a2.6 2.6 0 0 1 2.3 0l6.25 3.08c.67.33 1.1 1.02 1.1 1.77v8.26c0 .75-.43 1.44-1.1 1.77l-6.25 3.08a2.6 2.6 0 0 1-2.3 0L4.6 17.9a1.97 1.97 0 0 1-1.1-1.77V7.87c0-.75.43-1.44 1.1-1.77Z';
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="filled">
            <mask :id="maskId">
                <rect width="24" height="24" fill="#fff" stroke="none" />
                <path d="M12 4.2 18.6 7.25 12 10.3 5.4 7.25Z" fill="#000" stroke="#000" stroke-width="1" />
                <path d="M8.4 5.6 15.5 8.95" stroke="#fff" stroke-linecap="butt" />
                <path d="M6.3 12.6 8 13.4" stroke="#000" stroke-width="1.9" />
            </mask>
            <path :d="outline" fill="currentColor" :mask="`url(#${maskId})`" />
        </template>
        <template v-else>
            <path :d="outline" />
            <path d="M3.9 6.9 12 11l8.1-4.1M12 11v10.2M8.1 5.1l8.1 4M6.6 12.4 8 13.1" />
        </template>
    </svg>
</template>
