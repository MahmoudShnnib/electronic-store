<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from 'ant-design-vue';
import { Plus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface PoRow extends Record<string, unknown> {
    id: number;
    po_number: string;
    supplier: string;
    warehouse: string;
    status: string;
    status_label: string;
    total_formatted: string;
    items_count: number;
    expected_at: string | null;
    created_at: string;
}

defineProps<{
    orders: Paginator<PoRow>;
    suppliers: { id: number; company_name: string }[];
    statuses: Option[];
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'po_number', label: t('admin.purchase_orders.po_number') },
    { key: 'supplier', label: t('admin.common.supplier') },
    { key: 'warehouse', label: t('admin.common.warehouse') },
    { key: 'items_count', label: t('admin.common.items') },
    { key: 'total_formatted', label: t('admin.common.total') },
    { key: 'expected_at', label: t('admin.common.expected') },
    { key: 'status', label: t('admin.common.status') },
];
</script>

<template>
    <Head :title="t('admin.purchase_orders.title')" />

    <PageHeader :title="t('admin.purchase_orders.title')" :subtitle="t('admin.purchase_orders.count', { count: orders.total })">
        <template #actions>
            <Link :href="route('admin.purchase-orders.create')">
                <Button type="primary">
                    <template #icon><Plus class="h-4 w-4" /></template>
                    {{ t('admin.purchase_orders.new') }}
                </Button>
            </Link>
        </template>
    </PageHeader>

    <FilterBar
        :url="route('admin.purchase-orders.index')"
        :filters="filters"
        :search-placeholder="t('admin.purchase_orders.search')"
        :selects="[
            { key: 'status', label: t('admin.common.all_statuses'), options: statuses },
            {
                key: 'supplier_id',
                label: t('admin.common.all_suppliers'),
                options: suppliers.map((s) => ({ value: String(s.id), label: s.company_name })),
            },
        ]"
    />

    <DataTable :columns="columns" :rows="orders.data">
        <template #cell-po_number="{ row }">
            <Link :href="route('admin.purchase-orders.show', row.id)" class="admin-link font-medium hover:underline">
                {{ row.po_number }}
            </Link>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" :label="row.status_label" />
        </template>
    </DataTable>

    <Pagination :paginator="orders" />
</template>
