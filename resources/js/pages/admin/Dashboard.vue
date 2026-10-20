<script setup lang="ts">
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { seedAttention } from '@/composables/useAdminNav';
import { useAdminColors } from '@/composables/useAdminTheme';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import type { TableColumnType } from 'ant-design-vue';
import { Alert, Card, List, ListItem, Table, Typography } from 'ant-design-vue';
import { BarElement, CategoryScale, Chart as ChartJS, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
import {
    Banknote,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    HandCoins,
    Package,
    PackageX,
    ShoppingCart,
    Star,
    TrendingDown,
    Truck,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed, watch, type Component } from 'vue';
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

interface AttentionItem {
    key: string;
    count: number;
    href: string;
    tone: 'warning' | 'danger' | 'info';
}

interface ActivityEntry {
    id: number;
    description: string;
    causer: string;
    created_at: string;
}

/** Null when this member of staff may not see the figure (see DashboardController). */
const props = defineProps<{
    can: { reports: boolean; orders: boolean; products: boolean; customers: boolean; audit: boolean };
    kpis: {
        revenue_today: string | null;
        revenue_month: string | null;
        orders_total: number | null;
        orders_in_progress: number | null;
        orders_completed: number | null;
        orders_cancelled: number | null;
        orders_refunded: number | null;
        customers_total: number | null;
        products_total: number | null;
        products_active: number | null;
        low_stock: number | null;
        out_of_stock: number | null;
    };
    attention: AttentionItem[];
    staleCodOrders: { id: number; number: string; customer: string; days: number; total_formatted: string }[];
    revenueSeries: RevenuePoint[];
    bestSellers: BestSeller[];
    recentOrders: RecentOrder[];
    recentActivity: ActivityEntry[];
}>();

const colors = useAdminColors();

/*
 * Needs attention: what has a count gets a tile, what is clear collapses into
 * one quiet line, so the panel is short on a good day and loud on a bad one.
 * The sidebar badges read the same numbers; seeding them here keeps the two in
 * step without a second request.
 */
const attentionIcons: Record<string, Component> = {
    orders_to_fulfil: ShoppingCart,
    awaiting_payment: HandCoins,
    low_stock: TrendingDown,
    out_of_stock: PackageX,
    pending_reviews: Star,
    purchase_orders_open: Truck,
    open_shifts: Wallet,
};

const waiting = computed(() => props.attention.filter((item) => item.count > 0));
const clear = computed(() => props.attention.filter((item) => item.count === 0));

watch(
    () => props.attention,
    (items) => seedAttention(items),
    { immediate: true },
);

const revenueChart = computed(() => ({
    labels: props.revenueSeries.map((p) => p.day.slice(5)),
    datasets: [
        {
            label: t('admin.common.revenue'),
            data: props.revenueSeries.map((p) => p.revenue),
            borderColor: colors.value.accent,
            backgroundColor: colors.value.chartFill,
            borderWidth: 2,
            fill: true,
            tension: 0.3,
        },
    ],
}));

const ordersChart = computed(() => ({
    labels: props.revenueSeries.map((p) => p.day.slice(5)),
    datasets: [
        {
            label: t('admin.common.orders'),
            data: props.revenueSeries.map((p) => p.orders),
            backgroundColor: colors.value.success,
            borderRadius: { topLeft: 4, topRight: 4 },
            borderSkipped: 'bottom' as const,
            // Sparse series (one day with orders) would otherwise stretch a single
            // bar across the whole plot area and read as a solid block.
            maxBarThickness: 44,
        },
    ],
}));

const chartOptions = computed(() => ({
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
        x: {
            grid: { display: false },
            border: { color: colors.value.lineStrong },
            ticks: { color: colors.value.muted, font: { size: 11 } },
        },
    },
    // A single day of data draws no line segment; without a visible point the
    // revenue chart reads as empty.
    elements: { point: { radius: 3, hoverRadius: 5, backgroundColor: colors.value.accent } },
}));

/** The original markup had no header row; AntD needs titles, so reuse the shared column labels. */
const recentOrderColumns: TableColumnType[] = [
    { key: 'number', dataIndex: 'number', title: t('admin.common.order') },
    { key: 'customer', dataIndex: 'customer', title: t('admin.common.customer') },
    { key: 'status', dataIndex: 'status', title: t('admin.common.status') },
    { key: 'total_formatted', dataIndex: 'total_formatted', title: t('admin.common.total'), className: 'text-end' },
    { key: 'created_at', dataIndex: 'created_at', title: t('admin.common.date'), className: 'text-end' },
];
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />

    <PageHeader :title="t('admin.dashboard.title')" :subtitle="t('admin.dashboard.subtitle')" />

    <section v-if="attention.length > 0" class="admin-attention" aria-labelledby="admin-attention-title">
        <h2 id="admin-attention-title" class="admin-attention-title">{{ t('admin.dashboard.attention.title') }}</h2>

        <div v-if="waiting.length > 0" class="admin-attention-grid">
            <Link v-for="item in waiting" :key="item.key" :href="item.href" class="admin-attention-tile" :class="`is-${item.tone}`">
                <span class="admin-attention-icon" aria-hidden="true"><component :is="attentionIcons[item.key] ?? Package" /></span>
                <span class="admin-attention-count">{{ item.count }}</span>
                <span class="admin-attention-label">{{ t(`admin.dashboard.attention.${item.key}`) }}</span>
                <span class="admin-attention-hint">{{ t(`admin.dashboard.attention.${item.key}_hint`) }}</span>
                <ChevronRight class="admin-attention-arrow" aria-hidden="true" />
            </Link>
        </div>

        <p v-else class="admin-attention-allclear">
            <CheckCircle2 aria-hidden="true" />
            {{ t('admin.dashboard.attention.all_clear') }}
        </p>

        <div v-if="clear.length > 0 && waiting.length > 0" class="admin-attention-clear">
            <span class="admin-attention-clear-label">{{ t('admin.dashboard.attention.clear') }}</span>
            <Link v-for="item in clear" :key="item.key" :href="item.href" class="admin-attention-chip">
                <CheckCircle2 aria-hidden="true" />
                {{ t(`admin.dashboard.attention.${item.key}`) }}
            </Link>
        </div>
    </section>

    <Alert v-if="staleCodOrders.length > 0" type="warning" show-icon :message="t('admin.dashboard.stale_cod_title')">
        <template #description>
            <div>{{ t('admin.dashboard.stale_cod_hint') }}</div>
            <ul class="mt-2 space-y-1">
                <li v-for="order in staleCodOrders" :key="order.id">
                    <Link :href="route('admin.orders.show', order.id)" class="admin-link hover:underline">{{ order.number }}</Link>
                    — {{ order.customer }} — {{ order.total_formatted }} — {{ t('admin.dashboard.days_old', { days: order.days }) }}
                </li>
            </ul>
        </template>
    </Alert>

    <div class="admin-dash">
        <!-- Headline numbers -->
        <div class="admin-dash-kpis">
            <StatCard
                v-if="kpis.revenue_today !== null"
                :label="t('admin.dashboard.revenue_today')"
                :value="kpis.revenue_today"
                tone="success"
                :icon="Banknote"
            />
            <StatCard
                v-if="kpis.revenue_month !== null"
                :label="t('admin.dashboard.revenue_this_month')"
                :value="kpis.revenue_month"
                tone="success"
                :icon="CalendarDays"
            />
            <StatCard
                v-if="kpis.orders_total !== null"
                :label="t('admin.dashboard.total_orders')"
                :value="kpis.orders_total"
                :hint="
                    t('admin.dashboard.orders_breakdown', {
                        completed: kpis.orders_completed,
                        cancelled: kpis.orders_cancelled,
                        refunded: kpis.orders_refunded,
                    })
                "
                :icon="ShoppingCart"
            />
            <StatCard
                v-if="kpis.products_total !== null"
                :label="t('admin.common.products')"
                :value="kpis.products_total"
                :suffix="t('admin.dashboard.active_count', { count: kpis.products_active })"
                :icon="Package"
            />
            <StatCard
                v-if="kpis.customers_total !== null"
                :label="t('admin.dashboard.customers')"
                :value="kpis.customers_total"
                :hint="t('admin.dashboard.registered')"
                :icon="Users"
            />
        </div>

        <!-- Charts. Plots stay left-to-right in both languages: time runs that way. -->
        <div v-if="can.reports" class="admin-dash-charts">
            <Card :title="t('admin.dashboard.revenue_chart')">
                <div class="h-56" dir="ltr">
                    <Line :data="revenueChart" :options="chartOptions" />
                </div>
            </Card>
            <Card :title="t('admin.dashboard.orders_chart')">
                <div class="h-56" dir="ltr">
                    <Bar :data="ordersChart" :options="chartOptions" />
                </div>
            </Card>
        </div>

        <!-- Recent orders + side column -->
        <div class="admin-dash-bottom">
            <Card v-if="can.orders" :title="t('admin.dashboard.recent_orders')" :body-style="{ padding: 0 }" class="overflow-hidden">
                <template #extra>
                    <Link :href="route('admin.orders.index')" class="admin-link">{{ t('admin.dashboard.view_all') }}</Link>
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
                            <Link :href="route('admin.orders.show', record.id)" class="admin-link font-medium">{{ record.number }}</Link>
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

            <div class="admin-dash-side">
                <Card v-if="can.reports" :title="t('admin.dashboard.best_sellers')" :body-style="{ padding: 0 }">
                    <List :data-source="bestSellers" :locale="{ emptyText: t('admin.dashboard.no_sales') }" :split="true">
                        <template #renderItem="{ item }">
                            <ListItem class="gap-3">
                                <span class="truncate" dir="auto">{{ item.name }}</span>
                                <Text type="secondary" class="shrink-0 !text-xs">
                                    {{ t('admin.dashboard.sold_revenue', { sold: item.sold, revenue: item.revenue_formatted }) }}
                                </Text>
                            </ListItem>
                        </template>
                    </List>
                </Card>

                <Card v-if="can.audit" :title="t('admin.dashboard.recent_activity')" :body-style="{ padding: 0 }">
                    <List :data-source="recentActivity" row-key="id" :locale="{ emptyText: t('admin.dashboard.no_activity') }" :split="true">
                        <template #renderItem="{ item }">
                            <ListItem>
                                <div class="min-w-0">
                                    <p class="mb-0">{{ item.description }}</p>
                                    <Text type="secondary" class="!text-xs">{{ item.causer }} · {{ item.created_at }}</Text>
                                </div>
                            </ListItem>
                        </template>
                    </List>
                </Card>
            </div>
        </div>
    </div>
</template>
