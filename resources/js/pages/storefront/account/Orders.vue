<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import Pagination from '@/components/store/Pagination.vue';
import type { PaginationLink } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface OrderRow {
    id: number;
    number: string;
    status: string;
    status_label: string;
    payment_status: string;
    total_formatted: string;
    items_count: number;
    created_at: string;
    cancellable: boolean;
}

defineProps<{
    orders: { data: OrderRow[]; links: PaginationLink[] };
}>();

const { t } = useI18n();

const statusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    confirmed: 'bg-neutral-200 text-black dark:bg-neutral-800 dark:text-white',
    processing: 'bg-neutral-200 text-black dark:bg-neutral-800 dark:text-white',
    ready_for_shipping: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    shipped: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    delivered: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    completed: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    returned: 'bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
    refunded: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};
</script>

<template>
    <Head :title="t('account.orders_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <h1 class="mb-6 text-2xl font-bold">{{ t('account.orders_title') }}</h1>

        <EmptyState
            v-if="orders.data.length === 0"
            :message="t('account.no_orders')"
            :action-label="t('cart.continue_shopping')"
            :action-href="route('catalog.index')"
        />

        <div v-else class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-border text-start text-muted-foreground">
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.order') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.date') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.items') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.status') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ t('account.total') }}</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="order in orders.data" :key="order.id" class="border-b border-border last:border-0 hover:bg-muted/50">
                        <td class="px-4 py-3 font-medium">{{ order.number }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ order.created_at }}</td>
                        <td class="px-4 py-3">{{ order.items_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusColors[order.status] ?? 'bg-muted'">
                                {{ order.status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-semibold">{{ order.total_formatted }}</td>
                        <td class="px-4 py-3 text-end">
                            <Link
                                :href="route('account.orders.show', order.id)"
                                class="text-sm font-medium text-black hover:underline dark:text-white"
                            >
                                {{ t('account.view') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="orders.links" />
    </div>
</template>
