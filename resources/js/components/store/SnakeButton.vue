<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

// Faithful clone of the reference site's snake-border CTA: four border spans,
// each carrying one travelling light segment, and the label on top.
defineProps<{ href: string }>();
</script>

<template>
    <Link :href="href" class="gc-snake-btn">
        <span class="gc-snake-btn-border"></span>
        <span class="gc-snake-btn-border"></span>
        <span class="gc-snake-btn-border"></span>
        <span class="gc-snake-btn-border"></span>
        <span class="gc-snake-btn-text"><slot /></span>
    </Link>
</template>
