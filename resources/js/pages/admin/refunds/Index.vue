<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface RefundRow extends Record<string, unknown> {
    id: number;
    order_number: string;
    order_id: number;
    rma_number: string | null;
    amount_formatted: string;
    method: string;
    reference: string | null;
    reason: string | null;
    created_by: string | null;
    created_at: string;
}

defineProps<{
    refunds: Paginator<RefundRow>;
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'order_number', label: t('admin.common.order') },
    { key: 'rma_number', label: t('admin.common.rma') },
    { key: 'amount_formatted', label: t('admin.common.amount') },
    { key: 'method', label: t('admin.common.method') },
    { key: 'reference', label: t('admin.common.reference') },
    { key: 'reason', label: t('admin.common.reason') },
    { key: 'created_by', label: t('admin.common.by') },
    { key: 'created_at', label: t('admin.common.date') },
];
</script>

<template>
    <Head :title="t('admin.refunds.title')" />

    <PageHeader :title="t('admin.refunds.title')" :subtitle="t('admin.refunds.count', { count: refunds.total })" />

    <FilterBar :url="route('admin.refunds.index')" :filters="filters" :search-placeholder="t('admin.refunds.search')" />

    <DataTable :columns="columns" :rows="refunds.data">
        <template #cell-order_number="{ row }">
            <Link :href="route('admin.orders.show', row.order_id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                {{ row.order_number }}
            </Link>
        </template>
    </DataTable>

    <Pagination :links="refunds.links" :total="refunds.total" :from="refunds.from" :to="refunds.to" />
</template>
