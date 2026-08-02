<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface ReturnRow extends Record<string, unknown> {
    id: number;
    rma_number: string;
    order_number: string;
    customer: string;
    status: string;
    status_label: string;
    reason: string;
    items_count: number;
    created_at: string;
}

defineProps<{
    returns: Paginator<ReturnRow>;
    statuses: Option[];
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'rma_number', label: t('admin.returns.rma') },
    { key: 'order_number', label: t('admin.common.order') },
    { key: 'customer', label: t('admin.common.customer') },
    { key: 'reason', label: t('admin.common.reason') },
    { key: 'items_count', label: t('admin.common.items') },
    { key: 'status', label: t('admin.common.status') },
    { key: 'created_at', label: t('admin.returns.requested') },
];
</script>

<template>
    <Head :title="t('admin.returns.title')" />

    <PageHeader :title="t('admin.returns.title')" :subtitle="t('admin.returns.count', { count: returns.total })" />

    <FilterBar
        :url="route('admin.returns.index')"
        :filters="filters"
        :search-placeholder="t('admin.returns.search')"
        :selects="[{ key: 'status', label: t('admin.common.all_statuses'), options: statuses }]"
    />

    <DataTable :columns="columns" :rows="returns.data">
        <template #cell-rma_number="{ row }">
            <Link :href="route('admin.returns.show', row.id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                {{ row.rma_number }}
            </Link>
        </template>
        <template #cell-reason="{ row }">
            <span class="capitalize">{{ String(row.reason).replaceAll('_', ' ') }}</span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" :label="row.status_label" />
        </template>
    </DataTable>

    <Pagination :links="returns.links" :total="returns.total" :from="returns.from" :to="returns.to" />
</template>
