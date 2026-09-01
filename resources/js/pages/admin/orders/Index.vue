<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { Text } = Typography;

const { t } = useI18n();

interface OrderRow extends Record<string, unknown> {
    id: number;
    number: string;
    customer_name: string;
    customer_email: string;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    payment_method: string;
    items_count: number;
    total_formatted: string;
    created_at: string;
}

defineProps<{
    orders: Paginator<OrderRow>;
    statuses: Option[];
    paymentStatuses: Option[];
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'number', label: t('admin.common.order') },
    { key: 'customer_name', label: t('admin.common.customer') },
    { key: 'status', label: t('admin.common.status') },
    { key: 'payment_status', label: t('admin.common.payment') },
    { key: 'payment_method', label: t('admin.common.method') },
    { key: 'items_count', label: t('admin.common.items') },
    { key: 'total_formatted', label: t('admin.common.total') },
    { key: 'created_at', label: t('admin.common.date') },
];
</script>

<template>
    <Head :title="t('admin.orders.title')" />

    <PageHeader :title="t('admin.orders.title')" :subtitle="t('admin.orders.count', { count: orders.total })" />

    <FilterBar
        :url="route('admin.orders.index')"
        :filters="filters"
        :search-placeholder="t('admin.orders.search')"
        :selects="[
            { key: 'status', label: t('admin.common.all_statuses'), options: statuses },
            { key: 'payment_status', label: t('admin.common.all_payment_states'), options: paymentStatuses },
        ]"
    />

    <DataTable :columns="columns" :rows="orders.data">
        <template #cell-number="{ row }">
            <Link :href="route('admin.orders.show', row.id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                {{ row.number }}
            </Link>
        </template>
        <template #cell-customer_name="{ row }">
            <div>
                <p>{{ row.customer_name }}</p>
                <Text type="secondary" class="!text-xs">{{ row.customer_email }}</Text>
            </div>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" :label="row.status_label" />
        </template>
        <template #cell-payment_status="{ row }">
            <StatusBadge :status="row.payment_status" :label="row.payment_status_label" />
        </template>
    </DataTable>

    <Pagination :paginator="orders" />
</template>
