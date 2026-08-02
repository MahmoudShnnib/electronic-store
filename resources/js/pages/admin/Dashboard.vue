<script setup lang="ts">
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { BarElement, CategoryScale, Chart as ChartJS, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip } from 'chart.js';
import { computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { useI18n } from 'vue-i18n';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler);

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

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
};
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />

    <PageHeader :title="t('admin.dashboard.title')" :subtitle="t('admin.dashboard.subtitle')" />

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <StatCard :label="t('admin.dashboard.revenue_today')" :value="kpis.revenue_today" tone="success" />
        <StatCard :label="t('admin.dashboard.revenue_this_month')" :value="kpis.revenue_month" tone="success" />
        <StatCard :label="t('admin.dashboard.total_orders')" :value="kpis.orders_total" />
        <StatCard :label="t('admin.dashboard.pending_orders')" :value="kpis.orders_pending" :tone="kpis.orders_pending > 0 ? 'warning' : 'default'" />
        <StatCard :label="t('admin.dashboard.completed_orders')" :value="kpis.orders_completed" />
        <StatCard :label="t('admin.dashboard.cancelled_orders')" :value="kpis.orders_cancelled" />
        <StatCard :label="t('admin.dashboard.customers')" :value="kpis.customers_total" />
        <StatCard
            :label="t('admin.common.products')"
            :value="kpis.products_total"
            :hint="t('admin.dashboard.active_count', { count: kpis.products_active })"
        />
        <StatCard :label="t('admin.dashboard.low_stock')" :value="kpis.low_stock" :tone="kpis.low_stock > 0 ? 'warning' : 'default'" />
        <StatCard :label="t('admin.dashboard.out_of_stock')" :value="kpis.out_of_stock" :tone="kpis.out_of_stock > 0 ? 'danger' : 'default'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.dashboard.revenue_chart') }}</h2>
            <div class="h-64">
                <Line :data="revenueChart" :options="chartOptions" />
            </div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.dashboard.orders_chart') }}</h2>
            <div class="h-64">
                <Bar :data="ordersChart" :options="chartOptions" />
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.dashboard.recent_orders') }}</h2>
                <Link :href="route('admin.orders.index')" class="text-sm text-blue-600 hover:underline dark:text-blue-400">{{
                    t('admin.dashboard.view_all')
                }}</Link>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2.5">
                            <Link :href="route('admin.orders.show', order.id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                {{ order.number }}
                            </Link>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ order.customer }}</td>
                        <td class="px-4 py-2.5"><StatusBadge :status="order.status" :label="order.status_label" /></td>
                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ order.total_formatted }}</td>
                        <td class="px-4 py-2.5 text-gray-400">{{ order.created_at }}</td>
                    </tr>
                    <tr v-if="recentOrders.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="5">{{ t('admin.dashboard.no_orders') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">
                    {{ t('admin.dashboard.best_sellers') }}
                </h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="(item, i) in bestSellers" :key="i" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                        <span class="truncate text-gray-700 dark:text-gray-300">{{ item.name }}</span>
                        <span class="shrink-0 text-xs text-gray-400">{{
                            t('admin.dashboard.sold_revenue', { sold: item.sold, revenue: item.revenue_formatted })
                        }}</span>
                    </li>
                    <li v-if="bestSellers.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">{{ t('admin.dashboard.no_sales') }}</li>
                </ul>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">
                    {{ t('admin.dashboard.recent_activity') }}
                </h2>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="entry in recentActivity" :key="entry.id" class="px-4 py-2.5 text-sm">
                        <p class="text-gray-700 dark:text-gray-300">{{ entry.description }}</p>
                        <p class="text-xs text-gray-400">{{ entry.causer }} · {{ entry.created_at }}</p>
                    </li>
                    <li v-if="recentActivity.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                        {{ t('admin.dashboard.no_activity') }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
