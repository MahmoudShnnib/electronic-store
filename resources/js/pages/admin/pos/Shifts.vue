<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Typography } from 'ant-design-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { Text } = Typography;
const { t } = useI18n();

interface ShiftRow {
    id: number;
    cashier: string | null;
    warehouse: string | null;
    status: string;
    status_label: string;
    orders_count: number;
    opened_at: string;
    closed_at: string | null;
    opening_float_formatted: string;
    expected_formatted: string;
    counted_formatted: string | null;
    difference_cents: number | null;
    difference_formatted: string | null;
}

defineProps<{ shifts: { data: ShiftRow[]; [key: string]: unknown } }>();

const columns = computed<Column[]>(() => [
    { key: 'cashier', label: t('admin.pos.cashier') },
    { key: 'warehouse', label: t('admin.pos.sell_from') },
    { key: 'opened_at', label: t('admin.pos.opened_at') },
    { key: 'closed_at', label: t('admin.pos.closed_at') },
    { key: 'orders_count', label: t('admin.pos.sales_count') },
    { key: 'expected', label: t('admin.pos.expected_in_drawer') },
    { key: 'counted', label: t('admin.pos.counted_cash') },
    { key: 'difference', label: t('admin.pos.variance') },
    { key: 'status', label: t('admin.common.status') },
]);
</script>

<template>
    <Head :title="t('admin.pos.shifts')" />

    <PageHeader :title="t('admin.pos.shifts')" :subtitle="t('admin.pos.shifts_subtitle')" />

    <DataTable :columns="columns" :rows="shifts.data" row-key="id">
        <template #cell-cashier="{ row }">{{ row.cashier ?? '—' }}</template>
        <template #cell-warehouse="{ row }">{{ row.warehouse ?? '—' }}</template>
        <template #cell-opened_at="{ row }">{{ row.opened_at }}</template>
        <template #cell-closed_at="{ row }">{{ row.closed_at ?? '—' }}</template>
        <template #cell-orders_count="{ row }">{{ row.orders_count }}</template>
        <template #cell-expected="{ row }">{{ row.expected_formatted }}</template>
        <template #cell-counted="{ row }">{{ row.counted_formatted ?? '—' }}</template>

        <template #cell-difference="{ row }">
            <!-- Only a closed shift has been counted, so only it can have a
                 variance; an open one shows nothing rather than a misleading 0. -->
            <span v-if="row.difference_cents === null">—</span>
            <Text v-else-if="row.difference_cents === 0" type="success">{{ t('admin.pos.balanced') }}</Text>
            <Text v-else :type="row.difference_cents > 0 ? 'warning' : 'danger'">
                {{ row.difference_cents > 0 ? '+' : '−' }}{{ row.difference_formatted }}
            </Text>
        </template>

        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" :label="row.status_label" />
        </template>
    </DataTable>

    <Pagination :paginator="shifts" />
</template>
