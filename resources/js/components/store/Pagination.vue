<script setup lang="ts">
import type { PaginationLink } from '@/components/store/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ links?: PaginationLink[] | null }>();

// Strip Laravel's HTML entities from the prev/next labels.
const cleaned = computed(() =>
    (props.links ?? []).map((link) => ({
        ...link,
        label: link.label.replace('&laquo;', '«').replace('&raquo;', '»'),
    })),
);

const hasPages = computed(() => cleaned.value.length > 3);
</script>

<template>
    <nav v-if="hasPages" class="mt-8 flex flex-wrap items-center justify-center gap-1" aria-label="Pagination">
        <template v-for="(link, i) in cleaned" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="min-w-9 rounded-md border px-3 py-1.5 text-center text-sm transition"
                :class="
                    link.active
                        ? 'border-black bg-black font-semibold text-white dark:border-white dark:bg-white dark:text-black'
                        : 'border-border bg-card hover:bg-muted'
                "
                >{{ link.label }}</Link
            >
            <span v-else class="min-w-9 px-3 py-1.5 text-center text-sm text-muted-foreground" v-text="link.label" />
        </template>
    </nav>
</template>
