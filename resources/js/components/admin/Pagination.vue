<script setup lang="ts">
/**
 * Server-side pagination. Takes the whole Laravel paginator rather than its
 * `links` array: AntD needs current/per-page/total, and `links` can't supply
 * them once Laravel truncates the run with an ellipsis.
 *
 * Navigation rewrites `page` on the *current* URL, so active filters survive
 * without this component knowing anything about them.
 */
import { router } from '@inertiajs/vue3';
import { Pagination } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';
import type { Paginator } from './types';

const { t } = useI18n();

const props = defineProps<{
    paginator: Paginator<unknown>;
}>();

function onChange(page: number): void {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));

    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true });
}

function showTotal(): string {
    return t('admin.common.showing', {
        from: props.paginator.from ?? 0,
        to: props.paginator.to ?? 0,
        total: props.paginator.total,
    });
}
</script>

<template>
    <div v-if="paginator.last_page > 1" class="mt-4 flex justify-end">
        <Pagination
            :current="paginator.current_page"
            :page-size="paginator.per_page"
            :total="paginator.total"
            :show-size-changer="false"
            :show-total="showTotal"
            @change="onChange"
        />
    </div>
</template>
