<script setup lang="ts">
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { btnSecondary } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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

function exportUrl(report: string): string {
    return route('admin.reports.export', { report });
}
</script>

<template>
    <Head :title="t('admin.reports.inventory_title')" />

    <PageHeader :title="t('admin.reports.inventory_title')">
        <template #actions>
            <Link :href="route('admin.reports.sales')" :class="btnSecondary">{{ t('admin.reports.sales_title') }}</Link>
            <Link :href="route('admin.reports.invoices')" :class="btnSecondary">{{ t('admin.reports.invoices_title') }}</Link>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard :label="t('admin.common.units_on_hand')" :value="valuation.units" />
        <StatCard :label="t('admin.reports.cost_value')" :value="valuation.cost_value_formatted" />
        <StatCard :label="t('admin.reports.retail_value')" :value="valuation.retail_value_formatted" tone="success" />
    </div>
    <div class="mt-3">
        <a :href="exportUrl('inventory_valuation')" class="text-sm text-blue-600 hover:underline dark:text-blue-400">
            <Download class="inline h-4 w-4" />{{ t('admin.reports.export_valuation') }}</a
        >
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.low_stock') }}</h2>
                <a :href="exportUrl('low_stock')" class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                    ><Download class="inline h-4 w-4" />{{ t('admin.common.csv') }}</a
                >
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(row, i) in lowStock" :key="i">
                        <td class="px-4 py-2">
                            {{ row.name }}<span v-if="row.variant_name" class="text-gray-400"> — {{ row.variant_name }}</span>
                            <span class="ms-1 text-xs text-gray-400">{{ row.sku }}</span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ row.warehouse }}</td>
                        <td class="px-4 py-2 text-end font-semibold text-amber-600">{{ row.quantity }} / {{ row.threshold }}</td>
                    </tr>
                    <tr v-if="lowStock.length === 0">
                        <td class="px-4 py-6 text-center text-gray-400" colspan="3">{{ t('admin.reports.nothing_low') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.out_of_stock') }}</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="(row, i) in outOfStock" :key="i">
                        <td class="px-4 py-2">
                            {{ row.name }}<span v-if="row.variant_name" class="text-gray-400"> — {{ row.variant_name }}</span>
                            <span class="ms-1 text-xs text-gray-400">{{ row.sku }}</span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ row.warehouse }}</td>
                        <td class="px-4 py-2 text-end font-semibold text-red-600">{{ row.quantity }}</td>
                    </tr>
                    <tr v-if="outOfStock.length === 0">
                        <td class="px-4 py-6 text-center text-gray-400" colspan="3">{{ t('admin.reports.nothing_out') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
