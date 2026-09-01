<script setup lang="ts">
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { TableColumnType } from 'ant-design-vue';
import { Card, Col, List, ListItem, Row, Table, Typography } from 'ant-design-vue';
import { BarElement, CategoryScale, Chart as ChartJS, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler);

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const { Text } = Typography;

interface RevenuePoint {
    day: string;
    revenue: number;
    orders: number;
}

interface BestSeller {
    product_id: number | null;
    name: string;
    sold: number;
    revenue_formatted: string;
}

interface RecentOrder {
    id: number;
    number: string;
    customer: string;
    status: string;
    status_label: string;
    total_formatted: string;
    created_at: string;
}

interface ActivityEntry {
    id: number;
    description: string;
    causer: string;
    created_at: string;
}

const props = defineProps<{
    kpis: {
        revenue_today: string;
        revenue_month: string;
        orders_total: number;
        orders_pending: number;
        orders_completed: number;
        orders_cancelled: number;
        customers_total: number;
        products_total: number;
        products_active: number;
        low_stock: number;
        out_of_stock: number;
    };
    revenueSeries: RevenuePoint[];
    bestSellers: BestSeller[];
    recentOrders: RecentOrder[];
    recentActivity: ActivityEntry[];
}>();

const revenueChart = computed(() => ({
    labels: props.revenueSeries.map((p) => p.day.slice(5)),
    datasets: [
        {
            label: t('admin.common.revenue'),
            data: props.revenueSeries.map((p) => p.revenue),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
        },
    ],
}));

const ordersChart = computed(() => ({
    labels: props.revenueSeries.map((p) => p.day.slice(5)),
    datasets: [
        {
            label: t('admin.common.orders'),
            data: props.revenueSeries.map((p) => p.orders),
            backgroundColor: '#10b981',
            borderRadius: 3,
            // Sparse series (one day with orders) would otherwise stretch a single
            // bar across the whole plot area and read as a solid block.
            maxBarThickness: 48,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(128,128,128,0.1)' } },
        x: { grid: { display: false } },
    },
    // A single day of data draws no line segment; without a visible point the
    // revenue chart reads as empty.
    elements: { point: { radius: 3, hoverRadius: 5 } },
};

/** The original markup had no header row; AntD needs titles, so reuse the shared column labels. */
const recentOrderColumns: TableColumnType[] = [
    { key: 'number', dataIndex: 'number', title: t('admin.common.order') },
    { key: 'customer', dataIndex: 'customer', title: t('admin.common.customer') },
    { key: 'status', dataIndex: 'status', title: t('admin.common.status') },
    { key: 'total_formatted', dataIndex: 'total_formatted', title: t('admin.common.total') },
    { key: 'created_at', dataIndex: 'created_at', title: t('admin.common.date') },
];
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />

    <PageHeader :title="t('admin.dashboard.title')" :subtitle="t('admin.dashboard.subtitle')" />

    <Row :gutter="[16, 16]">
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.revenue_today')" :value="kpis.revenue_today" tone="success" />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.revenue_this_month')" :value="kpis.revenue_month" tone="success" />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.total_orders')" :value="kpis.orders_total" />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard
                :label="t('admin.dashboard.pending_orders')"
                :value="kpis.orders_pending"
                :tone="kpis.orders_pending > 0 ? 'warning' : 'default'"
            />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.completed_orders')" :value="kpis.orders_completed" />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.cancelled_orders')" :value="kpis.orders_cancelled" />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.customers')" :value="kpis.customers_total" />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard
                :label="t('admin.common.products')"
                :value="kpis.products_total"
                :hint="t('admin.dashboard.active_count', { count: kpis.products_active })"
            />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.low_stock')" :value="kpis.low_stock" :tone="kpis.low_stock > 0 ? 'warning' : 'default'" />
        </Col>
        <Col :xs="12" :md="8" :xl="6">
            <StatCard :label="t('admin.dashboard.out_of_stock')" :value="kpis.out_of_stock" :tone="kpis.out_of_stock > 0 ? 'danger' : 'default'" />
        </Col>
    </Row>

    <Row :gutter="[16, 16]" class="mt-6">
        <Col :xs="24" :lg="12">
            <Card :title="t('admin.dashboard.revenue_chart')" size="small">
                <div class="h-64">
                    <Line :data="revenueChart" :options="chartOptions" />
                </div>
            </Card>
        </Col>
        <Col :xs="24" :lg="12">
            <Card :title="t('admin.dashboard.orders_chart')" size="small">
                <div class="h-64">
                    <Bar :data="ordersChart" :options="chartOptions" />
                </div>
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]" class="mt-6">
        <Col :xs="24" :xl="16">
            <Card :title="t('admin.dashboard.recent_orders')" size="small" :body-style="{ padding: 0 }">
                <template #extra>
                    <Link :href="route('admin.orders.index')" class="text-blue-600 hover:underline dark:text-blue-400">{{
                        t('admin.dashboard.view_all')
                    }}</Link>
                </template>
                <Table
                    :columns="recentOrderColumns"
                    :data-source="recentOrders"
                    row-key="id"
                    :pagination="false"
                    size="middle"
                    :scroll="{ x: 'max-content' }"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'number'">
                            <Link
                                :href="route('admin.orders.show', record.id)"
                                class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >{{ record.number }}</Link
                            >
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <StatusBadge :status="record.status" :label="record.status_label" />
                        </template>
                        <template v-else-if="column.key === 'total_formatted'">
                            <span class="font-medium">{{ record.total_formatted }}</span>
                        </template>
                        <template v-else-if="column.key === 'created_at'">
                            <Text type="secondary">{{ record.created_at }}</Text>
                        </template>
                    </template>
                    <template #emptyText>{{ t('admin.dashboard.no_orders') }}</template>
                </Table>
            </Card>
        </Col>

        <Col :xs="24" :xl="8">
            <Card :title="t('admin.dashboard.best_sellers')" size="small" :body-style="{ padding: 0 }">
                <List size="small" :data-source="bestSellers" :locale="{ emptyText: t('admin.dashboard.no_sales') }" :split="true" class="px-2">
                    <template #renderItem="{ item }">
                        <ListItem>
                            <span class="truncate">{{ item.name }}</span>
                            <Text type="secondary" class="shrink-0 !text-xs">
                                {{ t('admin.dashboard.sold_revenue', { sold: item.sold, revenue: item.revenue_formatted }) }}
                            </Text>
                        </ListItem>
                    </template>
                </List>
            </Card>

            <Card :title="t('admin.dashboard.recent_activity')" size="small" :body-style="{ padding: 0 }" class="mt-4">
                <List
                    size="small"
                    :data-source="recentActivity"
                    row-key="id"
                    :locale="{ emptyText: t('admin.dashboard.no_activity') }"
                    :split="true"
                    class="px-2"
                >
                    <template #renderItem="{ item }">
                        <ListItem>
                            <div>
                                <p class="mb-0">{{ item.description }}</p>
                                <Text type="secondary" class="!text-xs">{{ item.causer }} · {{ item.created_at }}</Text>
                            </div>
                        </ListItem>
                    </template>
                </List>
            </Card>
        </Col>
    </Row>
</template>
