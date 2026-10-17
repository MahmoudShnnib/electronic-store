<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatCard from '@/components/admin/StatCard.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Tooltip } from 'ant-design-vue';
import { BadgeCheck, Printer, ScanBarcode } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface SaleRow extends Record<string, unknown> {
    id: number;
    number: string;
    at: string;
    branch: string | null;
    cashier: string | null;
    shift_id: number | null;
    /** A linked customer account, a typed name, or null for a cash customer. */
    customer: string | null;
    is_member: boolean;
    units: number;
    payment_label: string;
    total_formatted: string;
    status: string;
    status_label: string;
    discount_formatted: string | null;
}

interface Option {
    value: number | string;
    label: string;
}

const props = defineProps<{
    sales: Paginator<SaleRow>;
    totals: {
        sales: number;
        total_formatted: string;
        cash_formatted: string;
        bank_formatted: string;
        returned: number;
        returned_formatted: string;
        net_formatted: string;
    };
    filters: Record<string, string | undefined>;
    warehouses: Option[];
    cashiers: Option[];
    /** A cashier sees only the sales they rang up themselves. */
    ownOnly: boolean;
}>();

const columns: Column[] = [
    { key: 'number', label: t('admin.pos_sales.receipt') },
    { key: 'at', label: t('admin.common.date') },
    { key: 'customer', label: t('admin.common.customer') },
    { key: 'cashier', label: t('admin.pos.cashier') },
    { key: 'units', label: t('admin.pos_sales.units'), class: '!text-center' },
    { key: 'payment_label', label: t('admin.pos.payment') },
    { key: 'total_formatted', label: t('admin.common.total'), class: '!text-end' },
    { key: 'status', label: t('admin.common.status') },
];

const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

/** Dates sit outside FilterBar (it has no range); applying them keeps every other filter. */
function applyDates(): void {
    router.get(
        route('admin.pos.sales.index'),
        { ...props.filters, from: from.value || undefined, to: to.value || undefined },
        { preserveState: true },
    );
}
</script>

<template>
    <Head :title="t('admin.nav.pos_sales')" />

    <PageHeader :title="t('admin.nav.pos_sales')" :subtitle="ownOnly ? t('admin.pos_sales.subtitle_own') : t('admin.pos_sales.subtitle')">
        <template #actions>
            <Link :href="route('admin.pos.register')">
                <Button type="primary">
                    <template #icon><ScanBarcode class="h-4 w-4" /></template>
                    {{ t('admin.nav.pos') }}
                </Button>
            </Link>
        </template>
    </PageHeader>

    <div class="mb-5 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <StatCard :label="t('admin.pos_sales.count')" :value="totals.sales" />
        <StatCard :label="t('admin.pos_sales.total')" :value="totals.total_formatted" />
        <StatCard :label="t('admin.pos_sales.cash')" :value="totals.cash_formatted" />
        <StatCard :label="t('admin.pos_sales.bank')" :value="totals.bank_formatted" />
        <StatCard
            :label="t('admin.pos_sales.returned')"
            :value="totals.returned_formatted"
            tone="warning"
            :suffix="t('admin.pos_sales.returned_count', { count: totals.returned })"
        />
        <StatCard :label="t('admin.pos_sales.net')" :value="totals.net_formatted" tone="success" :help="t('admin.pos_sales.net_help')" />
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <Field :label="t('admin.common.from')"><AdminDateInput v-model="from" /></Field>
        <Field :label="t('admin.common.to')"><AdminDateInput v-model="to" /></Field>
        <Button class="mb-4" @click="applyDates">{{ t('admin.common.apply') }}</Button>
    </div>

    <FilterBar
        :url="route('admin.pos.sales.index')"
        :filters="filters"
        :search-placeholder="t('admin.pos_sales.search')"
        :selects="[
            { key: 'warehouse_id', label: t('admin.pos_sales.all_branches'), options: warehouses },
            ...(ownOnly ? [] : [{ key: 'cashier_id', label: t('admin.pos_sales.all_cashiers'), options: cashiers }]),
            {
                key: 'payment_method',
                label: t('admin.pos_sales.all_methods'),
                options: [
                    { value: 'cash', label: t('admin.pos_sales.method_cash') },
                    { value: 'bank_transfer', label: t('admin.pos_sales.method_bank') },
                ],
            },
            {
                key: 'status',
                label: t('admin.common.all_statuses'),
                options: [
                    { value: 'completed', label: t('admin.pos_sales.status_sold') },
                    { value: 'refunded', label: t('admin.pos_sales.status_returned') },
                ],
            },
        ]"
    />

    <DataTable :columns="columns" :rows="sales.data">
        <template #cell-number="{ row }">
            <Link :href="route('admin.orders.show', row.id)" class="admin-link font-medium tabular-nums hover:underline" dir="ltr">{{
                row.number
            }}</Link>
            <span v-if="row.branch" class="block text-xs text-ad-muted"
                >{{ row.branch }}<template v-if="row.shift_id"> · #{{ row.shift_id }}</template></span
            >
        </template>
        <template #cell-at="{ row }">
            <span class="whitespace-nowrap tabular-nums text-ad-muted">{{ row.at }}</span>
        </template>
        <template #cell-customer="{ row }">
            <span v-if="row.customer" class="inline-flex items-center gap-1" dir="auto">
                {{ row.customer }}
                <Tooltip v-if="row.is_member" :title="t('admin.pos_sales.member')"><BadgeCheck class="h-3.5 w-3.5 text-ad-accent" /></Tooltip>
            </span>
            <span v-else class="text-ad-muted">{{ t('admin.pos_sales.cash_customer') }}</span>
        </template>
        <template #cell-total_formatted="{ row }">
            <span class="font-medium tabular-nums">{{ row.total_formatted }}</span>
            <span v-if="row.discount_formatted" class="block text-xs text-ad-muted">{{
                t('admin.pos_sales.discount', { amount: row.discount_formatted })
            }}</span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge
                :status="row.status"
                :label="row.status === 'completed' ? t('admin.pos_sales.status_sold') : t('admin.pos_sales.status_returned')"
            />
        </template>
        <template #row-actions="{ row }">
            <Tooltip :title="t('admin.pos_sales.reprint')">
                <Link :href="route('admin.pos.receipt', row.id)" :aria-label="t('admin.pos_sales.reprint')">
                    <Button type="text" size="small">
                        <template #icon><Printer class="h-4 w-4" /></template>
                    </Button>
                </Link>
            </Tooltip>
        </template>
    </DataTable>

    <Pagination :paginator="sales" />
</template>
