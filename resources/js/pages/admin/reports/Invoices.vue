<script setup lang="ts">
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Printer } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const props = defineProps<{
    summary: {
        invoices: number;
        invoiced_formatted: string;
        paid_formatted: string;
        outstanding_formatted: string;
    };
    invoices: {
        order_id: number;
        number: string;
        date: string;
        customer: string;
        phone: string;
        status: string;
        status_label: string;
        payment_status: string;
        payment_status_label: string;
        total_formatted: string;
        paid_formatted: string;
        outstanding_cents: number;
        outstanding_formatted: string;
    }[];
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyRange(): void {
    router.get(route('admin.reports.invoices'), { from: from.value, to: to.value }, { preserveState: true });
}

function exportUrl(): string {
    return route('admin.reports.export', { report: 'invoices' }) + `?from=${from.value}&to=${to.value}`;
}
</script>

<template>
    <Head :title="t('admin.reports.invoices_title')" />

    <PageHeader :title="t('admin.reports.invoices_title')">
        <template #actions>
            <Link :href="route('admin.reports.sales')" :class="btnSecondary">{{ t('admin.reports.sales_title') }}</Link>
            <Link :href="route('admin.reports.inventory')" :class="btnSecondary">{{ t('admin.reports.inventory_title') }}</Link>
        </template>
    </PageHeader>

    <!-- Date range -->
    <div class="mb-6 flex flex-wrap items-end gap-3">
        <label class="text-sm text-gray-600 dark:text-gray-300"
            >{{ t('admin.common.from') }}<input v-model="from" type="date" class="mt-1 block" :class="inputClass" />
        </label>
        <label class="text-sm text-gray-600 dark:text-gray-300"
            >{{ t('admin.common.to') }}<input v-model="to" type="date" class="mt-1 block" :class="inputClass" />
        </label>
        <button type="button" :class="btnSecondary" @click="applyRange">{{ t('admin.common.apply') }}</button>
    </div>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <StatCard :label="t('admin.reports.invoices_title')" :value="summary.invoices" />
        <StatCard :label="t('admin.reports.invoiced')" :value="summary.invoiced_formatted" />
        <StatCard :label="t('admin.common.paid')" :value="summary.paid_formatted" tone="success" />
        <StatCard :label="t('admin.common.outstanding')" :value="summary.outstanding_formatted" tone="warning" />
    </div>

    <section class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 pt-4">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.reports.invoices_title') }}</h2>
            <a :href="exportUrl()" class="text-sm text-blue-600 hover:underline dark:text-blue-400"
                ><Download class="inline h-4 w-4" />{{ t('admin.common.csv') }}</a
            >
        </div>
        <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950/50">
                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="px-3 py-2.5 text-start">{{ t('admin.orders.invoice') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.common.date') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.common.customer') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.common.status') }}</th>
                    <th class="px-3 py-2.5 text-start">{{ t('admin.common.payment') }}</th>
                    <th class="px-3 py-2.5 text-end">{{ t('admin.common.total') }}</th>
                    <th class="px-3 py-2.5 text-end">{{ t('admin.common.paid') }}</th>
                    <th class="px-3 py-2.5 text-end">{{ t('admin.common.outstanding') }}</th>
                    <th class="px-3 py-2.5 text-end">{{ t('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="row in invoices" :key="row.order_id">
                    <td class="px-3 py-2.5">
                        <Link :href="route('admin.orders.show', row.order_id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                            {{ row.number }}
                        </Link>
                    </td>
                    <td class="px-3 py-2.5 text-gray-500">{{ row.date }}</td>
                    <td class="px-3 py-2.5">
                        <p class="text-gray-900 dark:text-gray-100">{{ row.customer }}</p>
                        <p v-if="row.phone" class="text-xs text-gray-500" dir="ltr">{{ row.phone }}</p>
                    </td>
                    <td class="px-3 py-2.5"><StatusBadge :status="row.status" :label="row.status_label" /></td>
                    <td class="px-3 py-2.5"><StatusBadge :status="row.payment_status" :label="row.payment_status_label" /></td>
                    <td class="px-3 py-2.5 text-end font-medium">{{ row.total_formatted }}</td>
                    <td class="px-3 py-2.5 text-end text-emerald-600 dark:text-emerald-400">{{ row.paid_formatted }}</td>
                    <td class="px-3 py-2.5 text-end" :class="row.outstanding_cents > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-gray-400'">
                        {{ row.outstanding_formatted }}
                    </td>
                    <td class="px-3 py-2.5 text-end">
                        <Link
                            :href="route('admin.orders.invoice', row.order_id)"
                            class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline dark:text-blue-400"
                        >
                            <Printer class="h-4 w-4" />
                            {{ t('admin.orders.invoice') }}
                        </Link>
                    </td>
                </tr>
                <tr v-if="invoices.length === 0">
                    <td colspan="9" class="px-3 py-8 text-center text-gray-500">{{ t('admin.common.no_records') }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
