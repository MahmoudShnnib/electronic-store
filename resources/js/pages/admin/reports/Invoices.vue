<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Card, Table } from 'ant-design-vue';
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

/** `text-end` rather than AntD's `align`, so the columns follow RTL. */
const endCell = { customHeaderCell: () => ({ class: 'text-end' }), customCell: () => ({ class: 'text-end' }) };

const columns = [
    { key: 'number', dataIndex: 'number', title: t('admin.orders.invoice') },
    { key: 'date', dataIndex: 'date', title: t('admin.common.date') },
    { key: 'customer', dataIndex: 'customer', title: t('admin.common.customer') },
    { key: 'status', dataIndex: 'status', title: t('admin.common.status') },
    { key: 'payment_status', dataIndex: 'payment_status', title: t('admin.common.payment') },
    { key: 'total_formatted', dataIndex: 'total_formatted', title: t('admin.common.total'), ...endCell },
    { key: 'paid_formatted', dataIndex: 'paid_formatted', title: t('admin.common.paid'), ...endCell },
    { key: 'outstanding_formatted', dataIndex: 'outstanding_formatted', title: t('admin.common.outstanding'), ...endCell },
    { key: 'actions', title: t('admin.common.actions'), ...endCell },
];

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
            <Link :href="route('admin.reports.sales')"
                ><Button>{{ t('admin.reports.sales_title') }}</Button></Link
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

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <StatCard :label="t('admin.reports.invoices_title')" :value="summary.invoices" />
        <StatCard :label="t('admin.reports.invoiced')" :value="summary.invoiced_formatted" />
        <StatCard :label="t('admin.common.paid')" :value="summary.paid_formatted" tone="success" />
        <StatCard :label="t('admin.common.outstanding')" :value="summary.outstanding_formatted" tone="warning" />
    </div>

    <Card size="small" class="mt-6" :title="t('admin.reports.invoices_title')" :body-style="{ padding: 0 }">
        <template #extra>
            <Button type="link" size="small" :href="exportUrl()">
                <template #icon><Download class="inline h-4 w-4" /></template>
                {{ t('admin.common.csv') }}
            </Button>
        </template>
        <Table :columns="columns" :data-source="invoices" row-key="order_id" :pagination="false" size="middle" :scroll="{ x: 'max-content' }">
            <template #bodyCell="{ column, record }">
                <Link
                    v-if="column.key === 'number'"
                    :href="route('admin.orders.show', record.order_id)"
                    class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                >
                    {{ record.number }}
                </Link>
                <span v-else-if="column.key === 'date'" class="text-gray-500">{{ record.date }}</span>
                <template v-else-if="column.key === 'customer'">
                    <p class="text-gray-900 dark:text-gray-100">{{ record.customer }}</p>
                    <p v-if="record.phone" class="text-xs text-gray-500" dir="ltr">{{ record.phone }}</p>
                </template>
                <StatusBadge v-else-if="column.key === 'status'" :status="record.status" :label="record.status_label" />
                <StatusBadge v-else-if="column.key === 'payment_status'" :status="record.payment_status" :label="record.payment_status_label" />
                <span v-else-if="column.key === 'total_formatted'" class="font-medium">{{ record.total_formatted }}</span>
                <span v-else-if="column.key === 'paid_formatted'" class="text-emerald-600 dark:text-emerald-400">{{ record.paid_formatted }}</span>
                <span
                    v-else-if="column.key === 'outstanding_formatted'"
                    :class="record.outstanding_cents > 0 ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-gray-400'"
                >
                    {{ record.outstanding_formatted }}
                </span>
                <Link
                    v-else-if="column.key === 'actions'"
                    :href="route('admin.orders.invoice', record.order_id)"
                    class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline dark:text-blue-400"
                >
                    <Printer class="h-4 w-4" />
                    {{ t('admin.orders.invoice') }}
                </Link>
            </template>
        </Table>
    </Card>
</template>
