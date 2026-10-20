<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatCard from '@/components/admin/StatCard.vue';
import type { Paginator } from '@/components/admin/types';
import { useAdminPermissions } from '@/composables/useAdminNav';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button, Card, Input, InputNumber } from 'ant-design-vue';
import { ArrowDownLeft, ArrowUpRight } from 'lucide-vue-next';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { can } = useAdminPermissions();

const { t } = useI18n();

interface TransactionRow extends Record<string, unknown> {
    id: number;
    direction: 'in' | 'out';
    direction_label: string;
    account: string;
    account_label: string;
    amount_formatted: string;
    order: { id: number; number: string } | null;
    reference: string | null;
    note: string | null;
    user: string | null;
    at: string;
}

const props = defineProps<{
    balances: { cash_formatted: string; bank_formatted: string; total_formatted: string };
    transactions: Paginator<TransactionRow>;
    filters: Record<string, unknown>;
}>();

const ledgerFilters = reactive({
    account: String(props.filters.account ?? ''),
    direction: String(props.filters.direction ?? ''),
});

function applyFilters(): void {
    const query = Object.fromEntries(Object.entries(ledgerFilters).filter(([, value]) => value !== ''));

    router.get(route('admin.treasury.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

const columns: Column[] = [
    { key: 'direction_label', label: t('admin.treasury.direction') },
    { key: 'account_label', label: t('admin.treasury.account') },
    { key: 'amount_formatted', label: t('admin.common.amount') },
    { key: 'order', label: t('admin.common.order') },
    { key: 'note', label: t('admin.common.note') },
    { key: 'reference', label: t('admin.common.reference') },
    { key: 'user', label: t('admin.common.by') },
    { key: 'at', label: t('admin.common.date') },
];

const form = useForm({ direction: 'in', account: 'cash', amount: null as number | null, reference: '', note: '' });

function submit(): void {
    form.post(route('admin.treasury.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="t('admin.treasury.title')" />

    <PageHeader :title="t('admin.treasury.title')" :subtitle="t('admin.treasury.subtitle')" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <StatCard :label="t('admin.treasury.cash')" :value="balances.cash_formatted" tone="success" />
        <StatCard :label="t('admin.treasury.bank')" :value="balances.bank_formatted" tone="success" />
        <StatCard :label="t('admin.treasury.balance')" :value="balances.total_formatted" />
    </div>

    <Card v-if="can('treasury.manage')" class="mb-6" :title="t('admin.treasury.manual_entry')" size="small">
        <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="submit">
            <Field :label="t('admin.treasury.direction')" :error="form.errors.direction">
                <AdminSelect
                    v-model="form.direction"
                    :options="[
                        { value: 'in', label: t('admin.treasury.deposit') },
                        { value: 'out', label: t('admin.treasury.withdrawal') },
                    ]"
                />
            </Field>
            <Field :label="t('admin.treasury.account')" :error="form.errors.account">
                <AdminSelect
                    v-model="form.account"
                    :options="[
                        { value: 'cash', label: t('admin.treasury.cash') },
                        { value: 'bank', label: t('admin.treasury.bank') },
                    ]"
                />
            </Field>
            <Field :label="t('admin.common.amount')" :error="form.errors.amount">
                <InputNumber v-model:value="form.amount" :min="0.01" :step="0.01" class="w-full" />
            </Field>
            <Field :label="t('admin.common.reference_optional')" :error="form.errors.reference">
                <Input v-model:value="form.reference" dir="ltr" />
            </Field>
            <Field :label="t('admin.common.note')" :error="form.errors.note">
                <div class="flex gap-2">
                    <Input v-model:value="form.note" />
                    <Button html-type="submit" class="shrink-0" :loading="form.processing">
                        {{ t('admin.common.save') }}
                    </Button>
                </div>
            </Field>
        </form>
    </Card>

    <!-- No text search here — the ledger is only ever narrowed by account and direction. -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <AdminSelect
            v-model="ledgerFilters.account"
            class="!w-auto min-w-40"
            :empty-label="t('admin.treasury.account')"
            :options="[
                { value: '', label: t('admin.common.all') },
                { value: 'cash', label: t('admin.treasury.cash') },
                { value: 'bank', label: t('admin.treasury.bank') },
            ]"
            @update:model-value="applyFilters"
        />
        <AdminSelect
            v-model="ledgerFilters.direction"
            class="!w-auto min-w-40"
            :empty-label="t('admin.treasury.direction')"
            :options="[
                { value: '', label: t('admin.common.all') },
                { value: 'in', label: t('admin.treasury.deposit') },
                { value: 'out', label: t('admin.treasury.withdrawal') },
            ]"
            @update:model-value="applyFilters"
        />
    </div>

    <DataTable :columns="columns" :rows="transactions.data">
        <template #cell-direction_label="{ row }">
            <span class="inline-flex items-center gap-1 text-sm font-medium" :class="row.direction === 'in' ? 'is-money' : 'is-danger'">
                <component :is="row.direction === 'in' ? ArrowDownLeft : ArrowUpRight" class="h-4 w-4" />
                {{ row.direction_label }}
            </span>
        </template>
        <template #cell-amount_formatted="{ row }">
            <span :class="row.direction === 'in' ? 'is-money' : 'is-danger'">
                {{ row.direction === 'in' ? '+' : '−' }}{{ row.amount_formatted }}
            </span>
        </template>
        <template #cell-order="{ row }">
            <Link v-if="row.order" :href="route('admin.orders.show', row.order.id)" class="admin-link font-medium hover:underline">
                {{ row.order.number }}
            </Link>
            <span v-else class="text-ad-label">—</span>
        </template>
    </DataTable>

    <Pagination :paginator="transactions" />
</template>
