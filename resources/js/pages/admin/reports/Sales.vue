<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { btnSecondary } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
        avg_order_formatted: string;
        estimated_cost_formatted: string;
        estimated_profit_formatted: string;
        refunded_formatted: string;
        new_customers: number;
    };
    daily: { day: string; orders: number; revenue: number }[];
    byProduct: { product_id: number | null; name: string; sku: string; units: number; revenue_formatted: string }[];
    byCategory: { name: string; units: number; revenue_formatted: string; revenue: number }[];
    byBrand: { name: string; units: number; revenue_formatted: string; revenue: number }[];
    byPaymentMethod: { method: string; orders: number; revenue_formatted: string }[];
    byStatus: { status: string; orders: number; revenue_formatted: string }[];
    byCustomer: { customer_id: number; name: string; email: string; orders: number; revenue_formatted: string }[];
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyRange(): void {
    router.get(route('admin.reports.sales'), { from: from.value, to: to.value }, { preserveState: true });
}

const revenueChart = computed(() => ({
    labels: props.daily.map((d) => d.day.slice(5)),
    datasets: [
        {
            label: t('admin.common.revenue'),
            data: props.daily.map((d) => d.revenue),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
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
            backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#64748b'],
        },
    ],
}));

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } },
};

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'right' as const } },
};

function exportUrl(report: string): string {
    return route('admin.reports.export', { report }) + `?from=${from.value}&to=${to.value}`;
}
</script>

<template>
    <Head :title="t('admin.reports.sales_title')" />

    <PageHeader :title="t('admin.reports.sales_title')">
        <template #actions>
            <Link :href="route('admin.reports.invoices')" :class="btnSecondary">{{ t('admin.reports.invoices_title') }}</Link>
            <Link :href="route('admin.reports.inventory')" :class="btnSecondary">{{ t('admin.reports.inventory_title') }}</Link>
        </template>
    </PageHeader>

    <!-- Date range -->
    <div class="mb-6 flex flex-wrap items-end gap-3">
        <label class="text-sm text-gray-600 dark:text-gray-300">{{ t('admin.common.from') }}<AdminDateInput v-model="from" class="mt-1" /> </label>
        <label class="text-sm text-gray-600 dark:text-gray-300">{{ t('admin.common.to') }}<AdminDateInput v-model="to" class="mt-1" /> </label>
        <button type="button" :class="btnSecondary" @click="applyRange">{{ t('admin.common.apply') }}</button>
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <StatCard :label="t('admin.common.orders')" :value="summary.orders" />
        <StatCard :label="t('admin.common.revenue')" :value="summary.revenue_formatted" tone="success" />
        <StatCard :label="t('admin.common.avg_order_value')" :value="summary.avg_order_formatted" />
        <StatCard :label="t('admin.reports.discounts_given')" :value="summary.discounts_formatted" />
        <StatCard
            :label="t('admin.reports.est_profit')"
            :value="summary.estimated_profit_formatted"
            tone="success"
            :hint="t('admin.reports.cost', { amount: summary.estimated_cost_formatted })"
        />
        <StatCard :label="t('admin.common.refunded')" :value="summary.refunded_formatted" tone="warning" />
        <StatCard :label="t('admin.reports.new_customers')" :value="summary.new_customers" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.revenue_by_day') }}</h2>
                <a :href="exportUrl('sales_by_day')" class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    ><Download class="inline h-4 w-4" />{{ t('admin.common.csv') }}</a
                >
            </div>
            <div class="h-72"><Line :data="revenueChart" :options="lineOptions" /></div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.revenue_by_category') }}</h2>
                <a :href="exportUrl('sales_by_category')" class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    ><Download class="inline h-4 w-4" />{{ t('admin.common.csv') }}</a
                >
            </div>
            <div class="h-72"><Doughnut :data="categoryChart" :options="doughnutOptions" /></div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <!-- By product -->
        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.top_products') }}</h2>
                <a :href="exportUrl('sales_by_product')" class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    ><Download class="inline h-4 w-4" />{{ t('admin.common.csv') }}</a
                >
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(row, i) in byProduct" :key="i">
                        <td class="px-4 py-2">
                            {{ row.name }} <span class="text-xs text-gray-400">{{ row.sku }}</span>
                        </td>
                        <td class="px-4 py-2 text-end">{{ t('admin.reports.units_n', { count: row.units }) }}</td>
                        <td class="px-4 py-2 text-end font-medium">{{ row.revenue_formatted }}</td>
                    </tr>
                    <tr v-if="byProduct.length === 0">
                        <td class="px-4 py-6 text-center text-gray-400" colspan="3">{{ t('admin.common.no_data') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- By brand -->
        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.sales_by_brand') }}</h2>
                <a :href="exportUrl('sales_by_brand')" class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    ><Download class="inline h-4 w-4" />{{ t('admin.common.csv') }}</a
                >
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(row, i) in byBrand" :key="i">
                        <td class="px-4 py-2">{{ row.name }}</td>
                        <td class="px-4 py-2 text-end">{{ t('admin.reports.units_n', { count: row.units }) }}</td>
                        <td class="px-4 py-2 text-end font-medium">{{ row.revenue_formatted }}</td>
                    </tr>
                    <tr v-if="byBrand.length === 0">
                        <td class="px-4 py-6 text-center text-gray-400" colspan="3">{{ t('admin.common.no_data') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- By customer -->
        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.top_customers') }}</h2>
                <a :href="exportUrl('sales_by_customer')" class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    ><Download class="inline h-4 w-4" />{{ t('admin.common.csv') }}</a
                >
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="row in byCustomer" :key="row.customer_id">
                        <td class="px-4 py-2">
                            {{ row.name }} <span class="text-xs text-gray-400">{{ row.email }}</span>
                        </td>
                        <td class="px-4 py-2 text-end">{{ t('admin.reports.orders_n', { count: row.orders }) }}</td>
                        <td class="px-4 py-2 text-end font-medium">{{ row.revenue_formatted }}</td>
                    </tr>
                    <tr v-if="byCustomer.length === 0">
                        <td class="px-4 py-6 text-center text-gray-400" colspan="3">{{ t('admin.common.no_data') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Payment + status -->
        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">
                {{ t('admin.reports.payment_methods') }}
            </h2>
            <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-800">
                <table class="min-w-0 text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in byPaymentMethod" :key="row.method">
                            <td class="px-4 py-2 capitalize">{{ row.method.replaceAll('_', ' ') }}</td>
                            <td class="px-4 py-2 text-end">{{ row.orders }}</td>
                            <td class="px-4 py-2 text-end font-medium">{{ row.revenue_formatted }}</td>
                        </tr>
                    </tbody>
                </table>
                <table class="min-w-0 text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in byStatus" :key="row.status">
                            <td class="px-4 py-2 capitalize">{{ row.status.replaceAll('_', ' ') }}</td>
                            <td class="px-4 py-2 text-end">{{ row.orders }}</td>
                            <td class="px-4 py-2 text-end font-medium">{{ row.revenue_formatted }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a :href="exportUrl('movements')" :class="btnSecondary"><Download class="h-4 w-4" />{{ t('admin.reports.movements_csv') }}</a>
        <a :href="exportUrl('refunds')" :class="btnSecondary"><Download class="h-4 w-4" />{{ t('admin.reports.refunds_csv') }}</a>
    </div>
</template>
