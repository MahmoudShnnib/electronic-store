<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import HelpHint from '@/components/admin/HelpHint.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Button,
    Card,
    Descriptions,
    DescriptionsItem,
    Divider,
    Input,
    InputNumber,
    Textarea,
    Timeline,
    TimelineItem,
    Typography,
} from 'ant-design-vue';
import { Printer } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { Text } = Typography;

const { t } = useI18n();

interface Address {
    name?: string;
    phone?: string;
    country?: string;
    city?: string;
    area?: string | null;
    notes?: string | null;
}

const props = defineProps<{
    order: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        allowed_transitions: Option[];
        payment_status: string;
        payment_status_label: string;
        payment_method: string;
        placed_via: string;
        customer: { name?: string; email?: string; phone?: string };
        account: { id: number; name: string; email: string; phone: string | null } | null;
        shipping_address: Address;
        billing_address: Address;
        subtotal_formatted: string;
        discount_formatted: string;
        coupon_code: string | null;
        tax_formatted: string;
        total_formatted: string;
        total_cents: number;
        paid_cents: number;
        paid_formatted: string;
        outstanding_formatted: string;
        refunded_formatted: string;
        customer_note: string | null;
        internal_note: string | null;
        created_at: string;
        items: {
            id: number;
            name: string;
            sku: string;
            quantity: number;
            unit_price_formatted: string;
            discount_formatted: string;
            tax_formatted: string;
            line_total_formatted: string;
        }[];
        timeline: { from: string | null; to: string; comment: string | null; user: string; at: string }[];
        payments: {
            id: number;
            amount_formatted: string;
            method: string;
            status: string;
            reference: string | null;
            confirmed_by: string | null;
            at: string;
        }[];
        refunds: { id: number; amount_formatted: string; method: string; reference: string | null; reason: string | null; at: string }[];
        returns: { id: number; rma_number: string; status_label: string }[];
    };
}>();

/** `!text-end` beats AntD's own cell alignment; `end` keeps RTL honest. */
const itemColumns: Column[] = [
    { key: 'name', label: t('admin.common.item') },
    { key: 'sku', label: t('admin.common.sku') },
    { key: 'quantity', label: t('admin.common.qty'), class: '!text-end' },
    { key: 'unit_price_formatted', label: t('admin.common.unit'), class: '!text-end' },
    { key: 'discount_formatted', label: t('admin.common.discount'), class: '!text-end' },
    { key: 'tax_formatted', label: t('admin.common.tax'), class: '!text-end' },
    { key: 'line_total_formatted', label: t('admin.common.total'), class: '!text-end' },
];

const statusForm = useForm({ status: '', comment: '' });

function submitStatus(): void {
    if (statusForm.status === '') return;

    statusForm.post(route('admin.orders.status', props.order.id), {
        preserveScroll: true,
        onSuccess: () => statusForm.reset(),
    });
}

const paymentForm = useForm({ amount: null as number | null, reference: '' });

function submitPayment(): void {
    paymentForm.post(route('admin.orders.payments.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset(),
    });
}

const refundForm = useForm({ amount: null as number | null, method: 'cash', reference: '', reason: '' });

function submitRefund(): void {
    refundForm.post(route('admin.refunds.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => refundForm.reset(),
    });
}

const noteForm = useForm({ internal_note: props.order.internal_note ?? '' });

function submitNote(): void {
    noteForm.post(route('admin.orders.note', props.order.id), { preserveScroll: true });
}

/**
 * One labelled row per field. Joining the whole address into a single
 * ` · `-separated string made a mistyped city indistinguishable from an area
 * or a delivery note, which is exactly what an order screen has to show.
 */
function addressRows(address: Address): { label: string; value: string }[] {
    return [
        { label: t('admin.common.name'), value: address.name },
        { label: t('admin.common.phone'), value: address.phone },
        { label: t('checkout.city'), value: address.city },
        { label: t('checkout.area'), value: address.area },
        { label: t('admin.common.notes'), value: address.notes },
    ]
        .filter((row): row is { label: string; value: string } => Boolean(row.value))
        .map((row) => ({ label: row.label, value: String(row.value) }));
}
</script>

<template>
    <Head :title="order.number" />

    <PageHeader :title="order.number" :subtitle="t('admin.orders.placed', { date: order.created_at, via: order.placed_via })">
        <template #actions>
            <Link :href="route('admin.orders.index')"
                ><Button>{{ t('admin.common.back') }}</Button></Link
            >
            <Link :href="route('admin.orders.invoice', order.id)">
                <Button>
                    <template #icon><Printer class="h-4 w-4" /></template>
                    {{ t('admin.orders.invoice') }}
                </Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
        <!-- Left: items + notes + timeline -->
        <div class="space-y-6 xl:col-span-2">
            <div class="space-y-4">
                <DataTable :columns="itemColumns" :rows="order.items" />

                <!-- pe-4 matches the table's own cell inset so the totals sit in the
                     same column as the figures in the Total column above. -->
                <div class="flex justify-end pe-4">
                    <Descriptions :column="1" size="small" class="admin-totals w-72">
                        <DescriptionsItem :label="t('admin.common.subtotal')">{{ order.subtotal_formatted }}</DescriptionsItem>
                        <DescriptionsItem>
                            <template #label>
                                {{ t('admin.common.discount') }}<span v-if="order.coupon_code" class="ms-1 text-xs">({{ order.coupon_code }})</span>
                            </template>
                            <bdi dir="ltr">−{{ order.discount_formatted }}</bdi>
                        </DescriptionsItem>
                        <DescriptionsItem :label="t('admin.common.tax')">{{ order.tax_formatted }}</DescriptionsItem>
                        <DescriptionsItem :label="t('admin.common.total')">
                            <span class="font-semibold">{{ order.total_formatted }}</span>
                        </DescriptionsItem>
                    </Descriptions>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <Card size="small" :title="t('admin.common.customer')">
                    <!-- Labelled rows: three bare lines made a mistyped city or a
                         swapped phone/email impossible to spot. -->
                    <Descriptions :column="1" size="small">
                        <DescriptionsItem :label="t('admin.common.name')">{{ order.customer.name || '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="t('admin.common.email')">
                            <bdi dir="ltr">{{ order.customer.email || '—' }}</bdi>
                        </DescriptionsItem>
                        <DescriptionsItem :label="t('admin.common.phone')">
                            <bdi dir="ltr">{{ order.customer.phone || '—' }}</bdi>
                        </DescriptionsItem>
                    </Descriptions>
                    <Link
                        v-if="order.account"
                        :href="route('admin.customers.show', order.account.id)"
                        class="mt-2 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
                        >{{ t('admin.orders.view_account') }}</Link
                    >
                    <Text v-else type="secondary" class="mt-2 block !text-xs">{{ t('admin.orders.guest_checkout') }}</Text>

                    <Descriptions :title="t('admin.orders.shipping_address')" :column="1" size="small" class="mt-4">
                        <DescriptionsItem v-for="row in addressRows(order.shipping_address)" :key="row.label" :label="row.label">
                            {{ row.value }}
                        </DescriptionsItem>
                    </Descriptions>

                    <template v-if="order.customer_note">
                        <Text strong class="mt-3 block !text-xs">{{ t('admin.orders.customer_note') }}</Text>
                        <Text type="secondary" class="mt-1 block">{{ order.customer_note }}</Text>
                    </template>
                </Card>

                <Card size="small" :title="t('admin.orders.timeline')">
                    <Timeline>
                        <TimelineItem v-for="(entry, i) in order.timeline" :key="i">
                            <p class="text-sm">
                                <!-- One interpolation: Vue condenses the whitespace-only text node
                                     between a closing template tag and the next binding, which ate
                                     the space after the arrow ("Pending →Cancelled"). -->
                                {{
                                    entry.from ? [entry.from, t('admin.orders.transition_arrow'), entry.to].join(' ') : entry.to
                                }}
                            </p>
                            <!-- dir="auto": comments and actor names come from the database in
                                 whatever language they were written, and a Latin line inside the
                                 RTL card otherwise has its trailing punctuation flipped. -->
                            <Text v-if="entry.comment" dir="auto" type="secondary" class="block !text-xs">{{ entry.comment }}</Text>
                            <Text type="secondary" dir="auto" class="block !text-xs">{{ entry.user }} · {{ entry.at }}</Text>
                        </TimelineItem>
                    </Timeline>
                </Card>
            </div>
        </div>

        <!-- Right rail: actions -->
        <div class="space-y-6">
            <Card size="small" :title="t('admin.orders.order_status')">
                <template #extra><StatusBadge :status="order.status" :label="order.status_label" /></template>
                <form v-if="order.allowed_transitions.length > 0" @submit.prevent="submitStatus">
                    <Field :label="t('admin.common.move_to')" :error="statusForm.errors.status">
                        <AdminSelect v-model="statusForm.status" :placeholder="t('admin.common.choose')" :options="order.allowed_transitions" />
                    </Field>
                    <Field :label="t('admin.common.comment')" :error="statusForm.errors.comment">
                        <Input v-model:value="statusForm.comment" />
                    </Field>
                    <Button type="primary" html-type="submit" block :loading="statusForm.processing" :disabled="statusForm.status === ''">
                        {{ t('admin.orders.update_status') }}
                    </Button>
                </form>
                <Text v-else type="secondary">{{ t('admin.orders.final_state') }}</Text>
            </Card>

            <Card size="small" :title="t('admin.common.payment')">
                <template #extra><StatusBadge :status="order.payment_status" :label="order.payment_status_label" /></template>
                <Descriptions :column="1" size="small">
                    <DescriptionsItem :label="t('admin.common.method')">{{ order.payment_method }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.paid')">{{ order.paid_formatted }}</DescriptionsItem>
                    <DescriptionsItem>
                        <template #label>
                            {{ t('admin.common.outstanding') }}
                            <HelpHint :text="t('admin.help.outstanding')" class="ms-1" />
                        </template>
                        {{ order.outstanding_formatted }}
                    </DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.refunded')">{{ order.refunded_formatted }}</DescriptionsItem>
                </Descriptions>
                <template v-if="order.payments.length > 0">
                    <Divider class="!my-3" />
                    <ul class="space-y-1">
                        <li v-for="p in order.payments" :key="p.id">
                            <Text type="secondary" class="!text-xs">
                                {{ p.amount_formatted }} · {{ p.method }} · {{ p.reference ?? 'no ref' }} · {{ p.at }}
                            </Text>
                        </li>
                    </ul>
                </template>
                <Divider class="!my-3" />
                <form @submit.prevent="submitPayment">
                    <Field :label="t('admin.orders.record_payment')" :error="paymentForm.errors.amount">
                        <InputNumber
                            v-model:value="paymentForm.amount"
                            :min="0.01"
                            :step="0.01"
                            :placeholder="t('admin.common.amount')"
                            class="w-full"
                        />
                    </Field>
                    <Input v-model:value="paymentForm.reference" :placeholder="t('admin.common.reference_optional')" dir="ltr" class="mb-4" />
                    <Button html-type="submit" block :loading="paymentForm.processing">
                        {{ t('admin.orders.record_payment') }}
                    </Button>
                </form>
            </Card>

            <Card size="small" :title="t('admin.common.refund')">
                <ul v-if="order.refunds.length > 0" class="mb-3 space-y-1">
                    <li v-for="r in order.refunds" :key="r.id">
                        <Text type="secondary" class="!text-xs">{{ r.amount_formatted }} · {{ r.method }} · {{ r.at }}</Text>
                    </li>
                </ul>
                <form @submit.prevent="submitRefund">
                    <Field :label="t('admin.common.amount')" :error="refundForm.errors.amount">
                        <InputNumber v-model:value="refundForm.amount" :min="0.01" :step="0.01" class="w-full" />
                    </Field>
                    <Field :label="t('admin.common.method')" :error="refundForm.errors.method">
                        <AdminSelect
                            v-model="refundForm.method"
                            :options="[
                                { value: 'cash', label: t('admin.orders.cash') },
                                { value: 'bank_transfer', label: t('admin.orders.bank_transfer') },
                                { value: 'store_credit', label: t('admin.orders.store_credit') },
                                { value: 'original', label: t('admin.orders.original_method') },
                            ]"
                        />
                    </Field>
                    <Input v-model:value="refundForm.reference" :placeholder="t('admin.common.reference_optional')" dir="ltr" class="mb-3" />
                    <Input v-model:value="refundForm.reason" :placeholder="t('admin.common.reason_optional')" class="mb-4" />
                    <Button html-type="submit" block :loading="refundForm.processing">
                        {{ t('admin.orders.issue_refund') }}
                    </Button>
                </form>
            </Card>

            <Card v-if="order.returns.length > 0" size="small" :title="t('admin.nav.returns')">
                <ul class="space-y-2 text-sm">
                    <li v-for="r in order.returns" :key="r.id" class="flex items-center justify-between">
                        <Link :href="route('admin.returns.show', r.id)" class="text-blue-600 hover:underline dark:text-blue-400">
                            {{ r.rma_number }}
                        </Link>
                        <Text type="secondary" class="!text-xs">{{ r.status_label }}</Text>
                    </li>
                </ul>
            </Card>

            <Card size="small" :title="t('admin.orders.internal_note')">
                <form @submit.prevent="submitNote">
                    <Textarea v-model:value="noteForm.internal_note" :rows="3" class="mb-4" />
                    <Button html-type="submit" block :loading="noteForm.processing">
                        {{ t('admin.orders.save_note') }}
                    </Button>
                </form>
            </Card>
        </div>
    </div>
</template>
