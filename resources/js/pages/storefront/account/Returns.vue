<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import Pagination from '@/components/store/Pagination.vue';
import type { PaginationLink } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface ReturnRow {
    id: number;
    rma_number: string;
    order_number: string;
    status: string;
    status_label: string;
    reason_label: string;
    created_at: string;
}

defineProps<{
    returns: { data: ReturnRow[]; links: PaginationLink[] };
}>();

const { t } = useI18n();

const statusColors: Record<string, string> = {
    requested: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    under_review: 'bg-neutral-200 text-black dark:bg-neutral-800 dark:text-white',
    approved: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    rejected: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    item_received: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    refunded: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};
</script>

<template>
    <Head :title="t('account.returns_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <h1 class="mb-6 text-2xl font-bold">{{ t('account.returns_title') }}</h1>

        <EmptyState v-if="returns.data.length === 0" :message="t('account.no_returns')" />

        <div v-else class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr class="border-b border-border text-muted-foreground">
                        <th class="px-4 py-3 text-start font-medium">RMA</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.order') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.return_reason') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.status') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in returns.data" :key="row.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium">{{ row.rma_number }}</td>
                        <td class="px-4 py-3">{{ row.order_number }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ row.reason_label }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusColors[row.status] ?? 'bg-muted'">
                                {{ row.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ row.created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="returns.links" />
    </div>
</template>
