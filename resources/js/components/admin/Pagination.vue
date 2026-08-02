<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { PaginatorLink } from './types';

const { t } = useI18n();

defineProps<{
    links: PaginatorLink[];
    total?: number;
    from?: number | null;
    to?: number | null;
}>();

function clean(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}
</script>

<template>
    <div v-if="links.length > 3" class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p v-if="total !== undefined" class="text-sm text-gray-500 dark:text-gray-400">
            {{ t('admin.common.showing', { from: from ?? 0, to: to ?? 0, total }) }}
        </p>
        <nav class="flex flex-wrap gap-1">
            <template v-for="(link, i) in links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="rounded-md border px-3 py-1.5 text-sm"
                    :class="
                        link.active
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'
                    "
                >
                    {{ clean(link.label) }}
                </Link>
                <span
                    v-else
                    class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-600"
                >
                    {{ clean(link.label) }}
                </span>
            </template>
        </nav>
    </div>
</template>
