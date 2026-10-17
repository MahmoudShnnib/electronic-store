<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { useAdminColors } from '@/composables/useAdminTheme';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { adminChartSeries } from '@/theme/adminTokens';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Card, Table } from 'ant-design-vue';
import { ArcElement, CategoryScale, Chart as ChartJS, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
import { Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Doughnut, Line } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Tooltip, Legend, Filler);

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const props = defineProps<{
    summary: {
        orders: number;
        revenue_formatted: string;
        discounts_formatted: string;
        manual_discounts_formatted: string;
        avg_order_formatted: string;
        estimated_cost_formatted: string;
        estimated_profit_formatted: string;
        refunded_formatted: string;
        new_customers: number;
    };
    writeOffs: {
        total_formatted: string;
        rows: { type: string; label: string; units: number; cost_formatted: string }[];
        /** Sent back to a supplier — owed back, so listed apart and not in the total. */
        supplier_returns: { units: number; cost_formatted: string };
    };
    daily: { day: string; orders: number; revenue: number }[];
    byProduct: { product_id: number | null; name: string; sku: string; units: number; revenue_formatted: string }[];
    byCategory: { name: string; units: number; revenue_formatted: string; revenue: number }[];
    byBrand: { name: string; units: number; revenue_formatted: string; revenue: number }[];
    byPaymentMethod: { method: string; label: string; orders: number; revenue_formatted: string }[];
    byStatus: { status: string; label: string; orders: number; revenue_formatted: string }[];
    byCustomer: { customer_id: number; name: string; email: string; orders: number; revenue_formatted: string }[];
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyRange(): void {
    router.get(route('admin.reports.sales'), { from: from.value, to: to.value }, { preserveState: true });
}

/** These rows carry no id, so the row index stands in as the key (it was `:key="i"` before). */
const rowIndexKey = (_: unknown, i: number): number => i;

/** `text-end` rather than AntD's `align`, so the columns follow RTL. */
const endCell = { customHeaderCell: () => ({ class: 'text-end' }), customCell: () => ({ class: 'text-end' }) };

const productColumns = [
    { key: 'name', dataIndex: 'name', title: t('admin.common.product') },
    { key: 'units', dataIndex: 'units', title: t('admin.common.qty'), ...endCell },
    { key: 'revenue_formatted', dataIndex: 'revenue_formatted', title: t('admin.common.revenue'), ...endCell },
];

const brandColumns = [
    { key: 'name', dataIndex: 'name', title: t('admin.common.brand') },
    { key: 'units', dataIndex: 'units', title: t('admin.common.qty'), ...endCell },
    { key: 'revenue_formatted', dataIndex: 'revenue_formatted', title: t('admin.common.revenue'), ...endCell },
];

const customerColumns = [
    { key: 'name', dataIndex: 'name', title: t('admin.common.customer') },
    { key: 'orders', dataIndex: 'orders', title: t('admin.common.orders'), ...endCell },
    { key: 'revenue_formatted', dataIndex: 'revenue_formatted', title: t('admin.common.revenue'), ...endCell },
];

const paymentColumns = [
    { key: 'method', dataIndex: 'method', title: t('admin.common.method') },
    { key: 'orders', dataIndex: 'orders', title: t('admin.common.orders'), ...endCell },
    { key: 'revenue_formatted', dataIndex: 'revenue_formatted', title: t('admin.common.revenue'), ...endCell },
];

const statusColumns = [
    { key: 'status', dataIndex: 'status', title: t('admin.common.status') },
    { key: 'orders', dataIndex: 'orders', title: t('admin.common.orders'), ...endCell },
    { key: 'revenue_formatted', dataIndex: 'revenue_formatted', title: t('admin.common.revenue'), ...endCell },
];

const colors = useAdminColors();

const revenueChart = computed(() => ({
    labels: props.daily.map((d) => d.day.slice(5)),
    datasets: [
        {
            label: t('admin.common.revenue'),
            data: props.daily.map((d) => d.revenue),
            borderColor: colors.value.accent,
            backgroundColor: colors.value.chartFill,
            borderWidth: 2,
            fill: true,
            tension: 0.3,
            pointRadius: 2,
        },
    ],
}));

const categoryChart = computed(() => ({
    labels: props.byCategory.map((c) => c.name),
    datasets: [
        {
            data: props.byCategory.map((c) => c.revenue),
            backgroundColor: adminChartSeries,
            // Chart.js separates arcs with a white border, which reads as a stray
            // white slash across the ring on the dark theme.
            borderWidth: 0,
        },
    ],
}));

const lineOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: {
            beginAtZero: true,
            grid: { color: colors.value.lineSoft },
            border: { display: false },
            ticks: { color: colors.value.muted, font: { size: 11 } },
        },
        x: { grid: { display: false }, border: { color: colors.value.lineStrong }, ticks: { color: colors.value.muted, font: { size: 11 } } },
    },
    // A range with one day in it draws no line segment, so the point itself has
    // to be visible or the chart looks empty.
    elements: { point: { radius: 3, hoverRadius: 5, backgroundColor: colors.value.accent } },
}));

const doughnutOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    // A ring rather than a pie, with dot legend keys — the default filled
    // squares and edge-to-edge arc read as a placeholder next to the line chart.
    cutout: '62%',
    plugins: {
        legend: {
            position: 'right' as const,
            labels: { usePointStyle: true, pointStyle: 'circle' as const, boxWidth: 8, boxHeight: 8, padding: 12, color: colors.value.text2 },
        },
    },
}));

function exportUrl(report: string): string {
    return route('admin.reports.export', { report }) + `?from=${from.value}&to=${to.value}`;
}
</script>

<template>
    <Head :title="t('admin.reports.sales_title')" />

    <PageHeader :title="t('admin.reports.sales_title')">
        <template #actions>
            <Link :href="route('admin.reports.invoices')"
                ><Button>{{ t('admin.reports.invoices_title') }}</Button></Link
            >
            <Link :href="route('admin.reports.inventory')"
                ><Button>{{ t('admin.reports.inventory_title') }}</Button></Link
            >
        </template>
    </PageHeader>

    <!-- Date range -->
    <div class="flex flex-wrap items-end gap-3">
        <Field :label="t('admin.common.from')"><AdminDateInput v-model="from" /></Field>
        <Field :label="t('admin.common.to')"><AdminDateInput v-model="to" /></Field>
        <!-- Matches Field's own bottom margin so the button lines up with the inputs. -->
        <Button class="mb-4" @click="applyRange">{{ t('admin.common.apply') }}</Button>
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <StatCard :label="t('admin.common.orders')" :value="summary.orders" />
        <StatCard :label="t('admin.common.revenue')" :value="summary.revenue_formatted" tone="success" />
        <StatCard :label="t('admin.common.avg_order_value')" :value="summary.avg_order_formatted" :help="t('admin.help.avg_order_value')" />
        <StatCard
            :label="t('admin.reports.discounts_given')"
            :value="summary.discounts_formatted"
            :help="t('admin.help.discounts_given')"
            :hint="t('admin.reports.manual_discounts', { amount: summary.manual_discounts_formatted })"
        />
        <StatCard
            :label="t('admin.reports.est_profit')"
            :value="summary.estimated_profit_formatted"
            :help="t('admin.help.est_profit')"
            tone="success"
            :hint="t('admin.reports.cost', { amount: summary.estimated_cost_formatted })"
        />
        <StatCard :label="t('admin.common.refunded')" :value="summary.refunded_formatted" tone="warning" />
        <StatCard
            :label="t('admin.reports.written_off')"
            :value="writeOffs.total_formatted"
            :help="t('admin.help.written_off')"
            tone="warning"
            :hint="
                [
                    ...writeOffs.rows.map((r) => `${r.label}: ${r.units} · ${r.cost_formatted}`),
                    ...(writeOffs.supplier_returns.units > 0
                        ? [
                              t('admin.reports.returned_to_supplier', {
                                  units: writeOffs.supplier_returns.units,
                                  amount: writeOffs.supplier_returns.cost_formatted,
                              }),
                          ]
                        : []),
                ].join(' — ')
            "
        />
        <StatCard :label="t('admin.reports.new_customers')" :value="summary.new_customers" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <Card size="small" class="lg:col-span-2" :title="t('admin.reports.revenue_by_day')">
            <template #extra>
                <Button type="link" size="small" :href="exportUrl('sales_by_day')">
                    <template #icon><Download class="inline h-4 w-4" /></template>
                    {{ t('admin.common.csv') }}
                </Button>
            </template>
            <div class="h-72"><Line :data="revenueChart" :options="lineOptions" /></div>
        </Card>
        <Card size="small" :title="t('admin.reports.revenue_by_category')">
            <template #extra>
                <Button type="link" size="small" :href="exportUrl('sales_by_category')">
                    <template #icon><Download class="inline h-4 w-4" /></template>
                    {{ t('admin.common.csv') }}
                </Button>
            </template>
            <div class="h-72"><Doughnut :data="categoryChart" :options="doughnutOptions" /></div>
        </Card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <!-- By product -->
        <Card size="small" :title="t('admin.reports.top_products')" :body-style="{ padding: 0 }">
            <template #extra>
                <Button type="link" size="small" :href="exportUrl('sales_by_product')">
                    <template #icon><Download class="inline h-4 w-4" /></template>
                    {{ t('admin.common.csv') }}
                </Button>
            </template>
            <Table
                :columns="productColumns"
                :data-source="byProduct"
                :row-key="rowIndexKey"
                :pagination="false"
                size="middle"
                :scroll="{ x: 'max-content' }"
            >
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        {{ record.name }} <span class="text-xs text-ad-label">{{ record.sku }}</span>
                    </template>
                    <template v-else-if="column.key === 'units'">{{ t('admin.reports.units_n', { count: record.units }) }}</template>
                    <span v-else-if="column.key === 'revenue_formatted'" class="font-medium">{{ record.revenue_formatted }}</span>
                </template>
                <template #emptyText>{{ t('admin.common.no_data') }}</template>
            </Table>
        </Card>

        <!-- By brand -->
        <Card size="small" :title="t('admin.reports.sales_by_brand')" :body-style="{ padding: 0 }">
            <template #extra>
                <Button type="link" size="small" :href="exportUrl('sales_by_brand')">
                    <template #icon><Download class="inline h-4 w-4" /></template>
                    {{ t('admin.common.csv') }}
                </Button>
            </template>
            <Table
                :columns="brandColumns"
                :data-source="byBrand"
                :row-key="rowIndexKey"
                :pagination="false"
                size="middle"
                :scroll="{ x: 'max-content' }"
            >
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'units'">{{ t('admin.reports.units_n', { count: record.units }) }}</template>
                    <span v-else-if="column.key === 'revenue_formatted'" class="font-medium">{{ record.revenue_formatted }}</span>
                </template>
                <template #emptyText>{{ t('admin.common.no_data') }}</template>
            </Table>
        </Card>

        <!-- By customer -->
        <Card size="small" :title="t('admin.reports.top_customers')" :body-style="{ padding: 0 }">
            <template #extra>
                <Button type="link" size="small" :href="exportUrl('sales_by_customer')">
                    <template #icon><Download class="inline h-4 w-4" /></template>
                    {{ t('admin.common.csv') }}
                </Button>
            </template>
            <Table
                :columns="customerColumns"
                :data-source="byCustomer"
                row-key="customer_id"
                :pagination="false"
                size="middle"
                :scroll="{ x: 'max-content' }"
            >
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        {{ record.name }} <span class="text-xs text-ad-label">{{ record.email }}</span>
                    </template>
                    <template v-else-if="column.key === 'orders'">{{ t('admin.reports.orders_n', { count: record.orders }) }}</template>
                    <span v-else-if="column.key === 'revenue_formatted'" class="font-medium">{{ record.revenue_formatted }}</span>
                </template>
                <template #emptyText>{{ t('admin.common.no_data') }}</template>
            </Table>
        </Card>

        <!-- Payment + status -->
        <Card size="small" :title="t('admin.reports.payment_methods')" :body-style="{ padding: 0 }">
            <div class="grid grid-cols-2">
                <Table
                    :columns="paymentColumns"
                    :data-source="byPaymentMethod"
                    row-key="method"
                    :pagination="false"
                    size="middle"
                    :scroll="{ x: 'max-content' }"
                >
                    <template #bodyCell="{ column, record }">
                        <span v-if="column.key === 'method'">{{ record.label }}</span>
                        <span v-else-if="column.key === 'revenue_formatted'" class="font-medium">{{ record.revenue_formatted }}</span>
                    </template>
                </Table>
                <Table
                    :columns="statusColumns"
                    :data-source="byStatus"
                    row-key="status"
                    :pagination="false"
                    size="middle"
                    :scroll="{ x: 'max-content' }"
                >
                    <template #bodyCell="{ column, record }">
                        <span v-if="column.key === 'status'">{{ record.label }}</span>
                        <span v-else-if="column.key === 'revenue_formatted'" class="font-medium">{{ record.revenue_formatted }}</span>
                    </template>
                </Table>
            </div>
        </Card>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <Button :href="exportUrl('movements')">
            <template #icon><Download class="h-4 w-4" /></template>
            {{ t('admin.reports.movements_csv') }}
        </Button>
        <Button :href="exportUrl('refunds')">
            <template #icon><Download class="h-4 w-4" /></template>
            {{ t('admin.reports.refunds_csv') }}
        </Button>
    </div>
</template>
