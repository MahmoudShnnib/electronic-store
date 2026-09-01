<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button, TypographyText } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface MovementRow extends Record<string, unknown> {
    id: number;
    name: string;
    sku: string;
    warehouse: string;
    type: string;
    type_label: string;
    before: number;
    change: number;
    after: number;
    reason: string | null;
    user: string;
    created_at: string;
}

defineProps<{
    movements: Paginator<MovementRow>;
    warehouses: { id: number; name: string }[];
    types: Option[];
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'created_at', label: t('admin.common.date') },
    { key: 'name', label: t('admin.common.product') },
    { key: 'warehouse', label: t('admin.common.warehouse') },
    { key: 'type', label: t('admin.common.type') },
    { key: 'before', label: t('admin.common.before') },
    { key: 'change', label: t('admin.common.change') },
    { key: 'after', label: t('admin.common.after') },
    { key: 'reason', label: t('admin.common.reason') },
    { key: 'user', label: t('admin.common.by') },
];
</script>

<template>
    <Head :title="t('admin.inventory.movements_title')" />

    <PageHeader :title="t('admin.inventory.movements_title')" :subtitle="t('admin.inventory.movements_subtitle')">
        <template #actions>
            <Link :href="route('admin.inventory.index')">
                <Button>{{ t('admin.inventory.back_to_stock') }}</Button>
            </Link>
        </template>
    </PageHeader>

    <FilterBar
        :url="route('admin.inventory.movements')"
        :filters="filters"
        search-key="product_id"
        :search-placeholder="t('admin.inventory.movements_filter')"
        :selects="[
            {
                key: 'warehouse_id',
                label: t('admin.common.all_warehouses'),
                options: warehouses.map((w) => ({ value: String(w.id), label: w.name })),
            },
            { key: 'type', label: t('admin.common.all_types'), options: types },
        ]"
    />

    <DataTable :columns="columns" :rows="movements.data">
        <template #cell-type="{ row }">
            <StatusBadge :status="row.type" :label="row.type_label" />
        </template>
        <template #cell-change="{ row }">
            <TypographyText :type="row.change >= 0 ? 'success' : 'danger'" strong> {{ row.change >= 0 ? '+' : '' }}{{ row.change }} </TypographyText>
        </template>
    </DataTable>

    <Pagination :paginator="movements" />
</template>
