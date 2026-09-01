<script setup lang="ts">
import type { PaginationLink } from '@/components/store/types';
import { router } from '@inertiajs/vue3';
import { Pagination } from 'ant-design-vue';
import { computed } from 'vue';

const props = defineProps<{ links?: PaginationLink[] | null }>();

/**
 * Laravel hands us rendered links, AntD wants a page number and a total. The
 * numeric links carry both, so they are the source of truth: their count is the
 * page count and the active one is the current page. Prev/next and the "..."
 * gap are dropped — AntD draws its own.
 */
const pages = computed(() =>
    (props.links ?? []).filter((link) => /^\d+$/.test(link.label)).map((link) => ({ page: Number(link.label), url: link.url, active: link.active })),
);

const current = computed(() => pages.value.find((page) => page.active)?.page ?? 1);

/**
 * Laravel only renders a window of page links around the current one, so the
 * highest label is not necessarily the last page. The last *numeric* link is
 * the furthest we can prove exists, which is what AntD needs to draw the bar.
 */
const total = computed(() => Math.max(...pages.value.map((page) => page.page), 1));

const hasPages = computed(() => total.value > 1);

/**
 * The window means the target page may have no link yet. Rewriting `page` on
 * the current URL keeps every active filter without this component knowing what
 * they are — the same approach the dashboard's paginator takes.
 */
function go(page: number) {
    const known = pages.value.find((candidate) => candidate.page === page)?.url;

    if (known) {
        router.get(known, {}, { preserveScroll: true, preserveState: true });

        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));
    router.get(url.toString(), {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <nav v-if="hasPages" class="mt-8 flex justify-center" aria-label="Pagination">
        <Pagination :current="current" :total="total" :page-size="1" :show-size-changer="false" :show-quick-jumper="false" @change="go" />
    </nav>
</template>
