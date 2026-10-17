<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatCard from '@/components/admin/StatCard.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Input, InputNumber, Radio, Textarea } from 'ant-design-vue';
import { ArrowLeft, HandCoins, NotebookPen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const RadioGroup = Radio.Group;
const RadioButton = Radio.Button;
const { t } = useI18n();

interface TransactionRow extends Record<string, unknown> {
    id: number;
    type: 'purchase' | 'payment' | 'adjustment';
    type_label: string;
    /** Signed from the shop's side: positive raises what we owe, negative lowers it. */
    amount_cents: number;
    amount_formatted: string;
    balance_after_formatted: string;
    purchase_order: { id: number; po_number: string } | null;
    account_label: string | null;
    reference: string | null;
    note: string | null;
    by: string | null;
    at: string;
}

const props = defineProps<{
    supplier: { id: number; company_name: string; contact_person: string | null; phone: string | null; email: string | null; status: string };
    totals: {
        balance_cents: number;
        balance_formatted: string;
        received_formatted: string;
        paid_formatted: string;
        adjusted_cents: number;
        adjusted_formatted: string;
    };
    transactions: Paginator<TransactionRow>;
    purchaseOrders: { id: number; label: string }[];
    accounts: { value: string; label: string; balance_formatted: string }[];
    canRecord: boolean;
}>();

const columns: Column[] = [
    { key: 'at', label: t('admin.common.date') },
    { key: 'type_label', label: t('admin.supplier_account.entry') },
    { key: 'details', label: t('admin.supplier_account.details') },
    { key: 'amount', label: t('admin.common.amount'), class: '!text-end' },
    { key: 'balance_after_formatted', label: t('admin.supplier_account.balance_after'), class: '!text-end' },
];

/** What the balance means, in words: who owes whom. */
const balanceLabel = computed(() =>
    props.totals.balance_cents > 0
        ? t('admin.supplier_account.we_owe')
        : props.totals.balance_cents < 0
          ? t('admin.supplier_account.they_owe')
          : t('admin.supplier_account.settled'),
);

/* -- Record a payment ------------------------------------------------------- */

const paying = ref(false);
const payment = useForm({
    amount: null as number | null,
    account: 'cash',
    purchase_order_id: '' as number | '',
    reference: '',
    note: '',
});

function openPayment(): void {
    payment.reset();
    payment.clearErrors();
    // Prefill what is owed: the common case is settling the account.
    payment.amount = props.totals.balance_cents > 0 ? props.totals.balance_cents / 100 : null;
    paying.value = true;
}

function submitPayment(): void {
    payment
        .transform((data) => ({ ...data, purchase_order_id: data.purchase_order_id || null }))
        .post(route('admin.suppliers.payments.store', props.supplier.id), {
            preserveScroll: true,
            onSuccess: () => (paying.value = false),
        });
}

/* -- Write in a debt or a credit -------------------------------------------- */

const adjusting = ref(false);
const adjustment = useForm({ direction: 'debt', amount: null as number | null, note: '' });

function openAdjustment(): void {
    adjustment.reset();
    adjustment.clearErrors();
    adjusting.value = true;
}

function submitAdjustment(): void {
    adjustment.post(route('admin.suppliers.adjustments.store', props.supplier.id), {
        preserveScroll: true,
        onSuccess: () => (adjusting.value = false),
    });
}
</script>

<template>
    <Head :title="supplier.company_name" />

    <Link :href="route('admin.suppliers.index')" class="mb-3 inline-flex items-center gap-1.5 text-sm text-ad-muted hover:text-ad-accent">
        <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
        {{ t('admin.suppliers.title') }}
    </Link>

    <PageHeader :title="supplier.company_name" :subtitle="[supplier.contact_person, supplier.phone].filter(Boolean).join(' · ') || undefined">
        <template v-if="canRecord" #actions>
            <Button @click="openAdjustment">
                <template #icon><NotebookPen class="h-4 w-4" /></template>
                {{ t('admin.supplier_account.add_adjustment') }}
            </Button>
            <Button type="primary" @click="openPayment">
                <template #icon><HandCoins class="h-4 w-4" /></template>
                {{ t('admin.supplier_account.record_payment') }}
            </Button>
        </template>
    </PageHeader>

    <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <StatCard
            :label="balanceLabel"
            :value="totals.balance_formatted"
            :tone="totals.balance_cents > 0 ? 'danger' : totals.balance_cents < 0 ? 'success' : 'default'"
            :help="t('admin.supplier_account.balance_help')"
        />
        <StatCard
            :label="t('admin.supplier_account.received')"
            :value="totals.received_formatted"
            :help="t('admin.supplier_account.received_help')"
        />
        <StatCard :label="t('admin.supplier_account.paid')" :value="totals.paid_formatted" tone="success" />
        <StatCard
            :label="t('admin.supplier_account.adjusted')"
            :value="(totals.adjusted_cents < 0 ? '−' : '') + totals.adjusted_formatted"
            :help="t('admin.supplier_account.adjusted_help')"
        />
    </div>

    <DataTable :columns="columns" :rows="transactions.data">
        <template #cell-at="{ row }">
            <span class="whitespace-nowrap tabular-nums text-ad-muted">{{ row.at }}</span>
        </template>
        <template #cell-details="{ row }">
            <div class="flex flex-col gap-0.5 text-sm">
                <Link
                    v-if="row.purchase_order"
                    :href="route('admin.purchase-orders.show', row.purchase_order.id)"
                    class="text-ad-accent hover:underline"
                >
                    {{ row.purchase_order.po_number }}
                </Link>
                <span v-if="row.account_label" class="text-ad-muted">
                    {{ row.account_label }}<template v-if="row.reference"> · {{ row.reference }}</template>
                </span>
                <span v-if="row.note && row.type !== 'purchase'" dir="auto">{{ row.note }}</span>
                <span v-if="row.by" class="text-xs text-ad-muted">{{ row.by }}</span>
            </div>
        </template>
        <template #cell-amount="{ row }">
            <!-- + raises what we owe (goods, a debt); − lowers it (a payment, a credit). -->
            <bdi dir="ltr" class="font-medium tabular-nums" :class="row.amount_cents > 0 ? 'text-ad-danger' : 'text-ad-success'">
                {{ row.amount_cents > 0 ? '+' : '−' }}{{ row.amount_formatted }}
            </bdi>
        </template>
        <template #cell-balance_after_formatted="{ row }">
            <bdi dir="ltr" class="tabular-nums">{{ row.balance_after_formatted }}</bdi>
        </template>
    </DataTable>

    <Pagination :paginator="transactions" />

    <FormModal :open="paying" :title="t('admin.supplier_account.record_payment')" @close="paying = false">
        <form class="grid gap-4" @submit.prevent="submitPayment">
            <Field :label="t('admin.common.amount')" required :error="payment.errors.amount">
                <InputNumber v-model:value="payment.amount" :min="0.01" :step="1" :precision="2" class="!w-full" />
            </Field>
            <Field :label="t('admin.supplier_account.paid_from')" required :error="payment.errors.account">
                <AdminSelect
                    v-model="payment.account"
                    :options="
                        accounts.map((a) => ({
                            value: a.value,
                            label: `${a.label} — ${t('admin.supplier_account.holds', { amount: a.balance_formatted })}`,
                        }))
                    "
                />
            </Field>
            <Field :label="t('admin.supplier_account.for_po')" :error="payment.errors.purchase_order_id">
                <AdminSelect
                    v-model="payment.purchase_order_id"
                    :options="purchaseOrders.map((po) => ({ value: po.id, label: po.label }))"
                    :empty-label="t('admin.supplier_account.no_po')"
                />
            </Field>
            <Field :label="t('admin.common.reference')" :error="payment.errors.reference">
                <Input v-model:value="payment.reference" dir="ltr" />
            </Field>
            <Field :label="t('admin.common.notes')" :error="payment.errors.note">
                <Textarea v-model:value="payment.note" :rows="2" />
            </Field>
            <div class="flex justify-end gap-3">
                <Button @click="paying = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="payment.processing">{{ t('admin.supplier_account.record_payment') }}</Button>
            </div>
        </form>
    </FormModal>

    <FormModal :open="adjusting" :title="t('admin.supplier_account.add_adjustment')" @close="adjusting = false">
        <form class="grid gap-4" @submit.prevent="submitAdjustment">
            <Field :label="t('admin.supplier_account.adjustment_kind')" required :error="adjustment.errors.direction">
                <RadioGroup v-model:value="adjustment.direction" button-style="solid" class="!flex">
                    <RadioButton value="debt" class="!flex-1 !text-center">{{ t('admin.supplier_account.debt') }}</RadioButton>
                    <RadioButton value="credit" class="!flex-1 !text-center">{{ t('admin.supplier_account.credit') }}</RadioButton>
                </RadioGroup>
            </Field>
            <Field :label="t('admin.common.amount')" required :error="adjustment.errors.amount">
                <InputNumber v-model:value="adjustment.amount" :min="0.01" :step="1" :precision="2" class="!w-full" />
            </Field>
            <Field
                :label="t('admin.supplier_account.reason')"
                required
                :error="adjustment.errors.note"
                :help="t('admin.supplier_account.adjustment_help')"
            >
                <Textarea v-model:value="adjustment.note" :rows="2" />
            </Field>
            <div class="flex justify-end gap-3">
                <Button @click="adjusting = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="adjustment.processing">{{ t('admin.common.save') }}</Button>
            </div>
        </form>
    </FormModal>
</template>
