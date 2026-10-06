<script setup lang="ts">
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button, Card, Table } from 'ant-design-vue';
import { Download } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface StockRow {
    name: string;
    variant_name: string;
    sku: string;
    warehouse: string;
    quantity: number;
    threshold?: number;
}

defineProps<{
    valuation: { units: number; cost_value_formatted: string; retail_value_formatted: string };
    lowStock: StockRow[];
    outOfStock: StockRow[];
}>();

/** These rows carry no id, so the row index stands in as the key (it was `:key="i"` before). */
const rowIndexKey = (_: StockRow, i: number): number => i;

/** Both stock tables share the same shape; only the quantity cell differs. */
const stockColumns = [
    { key: 'name', dataIndex: 'name', title: t('admin.common.product') },
    { key: 'warehouse', dataIndex: 'warehouse', title: t('admin.common.warehouse') },
    {
        key: 'quantity',
        dataIndex: 'quantity',
        title: t('admin.common.quantity'),
        customHeaderCell: () => ({ class: 'text-end' }),
        customCell: () => ({ class: 'text-end' }),
    },
];

function exportUrl(report: string): string {
    return route('admin.reports.export', { report });
}
</script>

<template>
    <Head :title="t('admin.reports.inventory_title')" />

    <PageHeader :title="t('admin.reports.inventory_title')">
        <template #actions>
            <Link :href="route('admin.reports.sales')"
                ><Button>{{ t('admin.reports.sales_title') }}</Button></Link
            >
            <Link :href="route('admin.reports.invoices')"
                ><Button>{{ t('admin.reports.invoices_title') }}</Button></Link
            >
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard :label="t('admin.common.units_on_hand')" :value="valuation.units" />
        <StatCard :label="t('admin.reports.cost_value')" :value="valuation.cost_value_formatted" :help="t('admin.help.inventory_cost_value')" />
        <StatCard
            :label="t('admin.reports.retail_value')"
            :value="valuation.retail_value_formatted"
            :help="t('admin.help.inventory_retail_value')"
            tone="success"
        />
    </div>
    <!-- Anchored to the grid's trailing edge; on its own at the leading edge it
         read as a stray link rather than an action on the figures above. -->
    <div class="mt-2 flex justify-end">
        <Button type="link" size="small" :href="exportUrl('inventory_valuation')">
            <template #icon><Download class="inline h-4 w-4" /></template>
            {{ t('admin.reports.export_valuation') }}
        </Button>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <Card size="small" :title="t('admin.reports.low_stock')" :body-style="{ padding: 0 }">
            <template #extra>
                <Button type="link" size="small" :href="exportUrl('low_stock')">
                    <template #icon><Download class="inline h-4 w-4" /></template>
                    {{ t('admin.common.csv') }}
                </Button>
            </template>
            <Table
                :columns="stockColumns"
                :data-source="lowStock"
                :row-key="rowIndexKey"
                :pagination="false"
                size="middle"
                :scroll="{ x: 'max-content' }"
            >
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        {{ record.name }}<span v-if="record.variant_name" class="text-ad-label"> — {{ record.variant_name }}</span>
                        <span class="ms-1 text-xs text-ad-label">{{ record.sku }}</span>
                    </template>
                    <span v-else-if="column.key === 'warehouse'" class="text-ad-muted">{{ record.warehouse }}</span>
                    <span v-else-if="column.key === 'quantity'" class="is-warning font-semibold">{{ record.quantity }} / {{ record.threshold }}</span>
                </template>
                <template #emptyText>{{ t('admin.reports.nothing_low') }}</template>
            </Table>
        </Card>

        <Card size="small" :title="t('admin.reports.out_of_stock')" :body-style="{ padding: 0 }">
            <Table
                :columns="stockColumns"
                :data-source="outOfStock"
                :row-key="rowIndexKey"
                :pagination="false"
                size="middle"
                :scroll="{ x: 'max-content' }"
            >
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        {{ record.name }}<span v-if="record.variant_name" class="text-ad-label"> — {{ record.variant_name }}</span>
                        <span class="ms-1 text-xs text-ad-label">{{ record.sku }}</span>
                    </template>
                    <span v-else-if="column.key === 'warehouse'" class="text-ad-muted">{{ record.warehouse }}</span>
                    <span v-else-if="column.key === 'quantity'" class="is-danger font-semibold">{{ record.quantity }}</span>
                </template>
                <template #emptyText>{{ t('admin.reports.nothing_out') }}</template>
            </Table>
        </Card>
    </div>
</template>
