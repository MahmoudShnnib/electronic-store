<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatCard from '@/components/admin/StatCard.vue';
import type { Paginator } from '@/components/admin/types';
import { btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight } from 'lucide-vue-next';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

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

    <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.treasury.manual_entry') }}</h2>
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
                <input v-model.number="form.amount" type="number" step="0.01" min="0.01" :class="inputClass" />
            </Field>
            <Field :label="t('admin.common.reference_optional')" :error="form.errors.reference">
                <input v-model="form.reference" type="text" :class="inputClass" dir="ltr" />
            </Field>
            <Field :label="t('admin.common.note')" :error="form.errors.note">
                <div class="flex gap-2">
                    <input v-model="form.note" type="text" :class="inputClass" />
                    <button type="submit" :disabled="form.processing" :class="btnSecondary" class="shrink-0">
                        {{ t('admin.common.save') }}
                    </button>
                </div>
            </Field>
        </form>
    </section>

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
            <span
                class="inline-flex items-center gap-1 text-sm font-medium"
                :class="row.direction === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
            >
                <component :is="row.direction === 'in' ? ArrowDownLeft : ArrowUpRight" class="h-4 w-4" />
                {{ row.direction_label }}
            </span>
        </template>
        <template #cell-amount_formatted="{ row }">
            <span :class="row.direction === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                {{ row.direction === 'in' ? '+' : '−' }}{{ row.amount_formatted }}
            </span>
        </template>
        <template #cell-order="{ row }">
            <Link
                v-if="row.order"
                :href="route('admin.orders.show', row.order.id)"
                class="font-medium text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ row.order.number }}
            </Link>
            <span v-else class="text-gray-400">—</span>
        </template>
    </DataTable>

    <Pagination :links="transactions.links" :total="transactions.total" :from="transactions.from" :to="transactions.to" />
</template>
