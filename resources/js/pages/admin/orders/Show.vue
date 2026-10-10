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
    Checkbox,
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
import { computed, ref } from 'vue';
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
        can: { manage: boolean; refund: boolean };
        payment_status: string;
        payment_status_label: string;
        payment_method: string;
        default_refund_method: string;
        placed_via: string;
        customer: { name?: string; email?: string; phone?: string };
        account: { id: number; name: string; email: string; phone: string | null } | null;
        shipping_address: Address;
        billing_address: Address;
        discount_formatted: string;
        coupon_code: string | null;
        total_formatted: string;
        total_cents: number;
        paid_cents: number;
        refunded_cents: number;
        paid_formatted: string;
        outstanding_formatted: string;
        outstanding_cents: number;
        refunded_formatted: string;
        customer_note: string | null;
        can_resolve_issue: boolean;
        created_at: string;
        items: {
            id: number;
            returnable: number;
            name: string;
            sku: string;
            quantity: number;
            unit_price_formatted: string;
            discount_formatted: string;
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
    };
}>();

/** `!text-end` beats AntD's own cell alignment; `end` keeps RTL honest. */
const itemColumns: Column[] = [
    { key: 'name', label: t('admin.common.item') },
    { key: 'sku', label: t('admin.common.sku') },
    { key: 'quantity', label: t('admin.common.qty'), class: '!text-end' },
    { key: 'unit_price_formatted', label: t('admin.common.unit'), class: '!text-end' },
    { key: 'discount_formatted', label: t('admin.common.discount'), class: '!text-end' },
    { key: 'line_total_formatted', label: t('admin.common.total'), class: '!text-end' },
];

/**
 * One button per allowed transition — no dropdown. `returned` is left out
 * whenever the dedicated return/problem form below can handle it, so there is
 * exactly one way to send an order back.
 */
const quickTransitions = computed(() =>
    props.order.can.manage ? props.order.allowed_transitions.filter((option) => !(option.value === 'returned' && props.order.can_resolve_issue)) : [],
);

/** Transitions that undo the sale — always ask for a reason before firing. */
const reasonRequired = ['cancelled', 'returned'];

const statusForm = useForm({ status: '', comment: '' });

/** Cancelling reports under "order" (e.g. "refund the payment first"), status changes under "status". */
const statusError = computed(() => {
    const errors = statusForm.errors as Record<string, string | undefined>;

    return errors.status ?? errors.order;
});

/** The transition awaiting a typed reason, if any. */
const pendingStatus = ref<string | null>(null);

function pickStatus(status: string): void {
    if (reasonRequired.includes(status)) {
        pendingStatus.value = pendingStatus.value === status ? null : status;
        statusForm.comment = '';

        return;
    }

    submitStatus(status);
}

function submitStatus(status: string): void {
    statusForm.status = status;

    statusForm.post(route('admin.orders.status', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            statusForm.reset();
            pendingStatus.value = null;
        },
    });
}

const paymentForm = useForm({ amount: null as number | null, reference: '' });

/** Customers pay the whole order at once: the only payment is the full amount owed. */
function submitPayment(): void {
    paymentForm.amount = props.order.outstanding_cents / 100;
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

/**
 * The single post-completion action: always records a reason, and refunds only
 * when an amount is entered. Defaults to giving back everything collected.
 */
const issueForm = useForm({
    reason: '',
    // What is still held after earlier refunds — the most that can go back.
    amount: Math.max(0, props.order.paid_cents - props.order.refunded_cents) / 100,
    // How many of each line came back; only these go back on the shelf.
    items: Object.fromEntries(props.order.items.map((item) => [item.id, item.returnable])) as Record<number, number>,
    method: props.order.default_refund_method,
    restock: true,
    reference: '',
});

const issueOpen = ref(false);

/** Opens the return form starting from the order as it is now (a payment may have landed since the page loaded). */
function openIssue(): void {
    issueForm.amount = Math.max(0, props.order.paid_cents - props.order.refunded_cents) / 100;
    issueForm.items = Object.fromEntries(props.order.items.map((item) => [item.id, item.returnable]));
    issueOpen.value = true;
}

function submitIssue(): void {
    issueForm.post(route('admin.orders.issue', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            issueForm.reset();
            issueOpen.value = false;
        },
    });
}

const refundMethods = computed(() => [
    { value: 'cash', label: t('admin.orders.cash') },
    { value: 'bank_transfer', label: t('admin.orders.bank_transfer') },
]);

/** Shown wherever the customer left a field empty, so the gap is obvious. */
const blank = '—';

/**
 * One labelled row per field, always rendered. Joining the address into a single
 * ` · `-separated string made a mistyped city indistinguishable from an area or
 * a delivery note, and dropping the empty rows hid the missing value entirely.
 */
function addressRows(address: Address): { label: string; value: string }[] {
    const rows = [
        { label: t('admin.common.recipient'), value: address.name },
        { label: t('admin.common.phone'), value: address.phone },
        { label: t('admin.common.city'), value: address.city },
        { label: t('admin.common.area'), value: address.area },
    ];

    if (address.notes) {
        rows.push({ label: t('admin.common.notes'), value: address.notes });
    }

    return rows.map((row) => ({ label: row.label, value: row.value ? String(row.value) : blank }));
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
            <Link :href="route('admin.orders.slip', order.id)">
                <Button>
                    <template #icon><Printer class="h-4 w-4" /></template>
                    {{ t('admin.orders.delivery_slip') }}
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
                        <DescriptionsItem>
                            <template #label>
                                {{ t('admin.common.discount') }}<span v-if="order.coupon_code" class="ms-1 text-xs">({{ order.coupon_code }})</span>
                            </template>
                            <bdi dir="ltr">−{{ order.discount_formatted }}</bdi>
                        </DescriptionsItem>
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
                        <DescriptionsItem :label="t('admin.common.name')">{{ order.customer.name || blank }}</DescriptionsItem>
                        <DescriptionsItem :label="t('admin.common.email')">
                            <bdi dir="ltr">{{ order.customer.email || blank }}</bdi>
                        </DescriptionsItem>
                        <DescriptionsItem :label="t('admin.common.phone')">
                            <bdi dir="ltr">{{ order.customer.phone || blank }}</bdi>
                        </DescriptionsItem>
                    </Descriptions>
                    <Link
                        v-if="order.account"
                        :href="route('admin.customers.show', order.account.id)"
                        class="admin-link mt-2 inline-block text-sm hover:underline"
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
                                {{ entry.from ? [entry.from, t('admin.orders.transition_arrow'), entry.to].join(' ') : entry.to }}
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
                <template v-if="quickTransitions.length > 0">
                    <div class="grid gap-2">
                        <Button
                            v-for="option in quickTransitions"
                            :key="option.value"
                            :type="option.value === 'cancelled' ? 'default' : 'primary'"
                            :danger="option.value === 'cancelled'"
                            block
                            :loading="statusForm.processing"
                            @click="pickStatus(String(option.value))"
                        >
                            {{ option.label }}
                        </Button>
                    </div>

                    <!-- Undoing a sale needs a written reason; it lands in the timeline. -->
                    <template v-if="pendingStatus">
                        <Divider class="!my-3" />
                        <Field :label="t('admin.orders.reason_required')" :error="statusForm.errors.comment">
                            <Input v-model:value="statusForm.comment" />
                        </Field>
                        <Button
                            danger
                            block
                            :loading="statusForm.processing"
                            :disabled="statusForm.comment.trim() === ''"
                            @click="submitStatus(pendingStatus)"
                        >
                            {{ t('admin.common.confirm') }}
                        </Button>
                    </template>
                    <!-- Cancelling reports under "order" (e.g. "refund the payment first"). -->
                    <Text v-if="statusError" type="danger" class="mt-2 block !text-sm">{{ statusError }}</Text>
                </template>
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
                <!-- Every payment and refund posts to the treasury, so what is
                     recorded here is what the accounts will show. -->
                <Text type="secondary" class="mb-3 block !text-xs">{{ t('admin.orders.treasury_hint') }}</Text>
                <form v-if="order.can.manage && order.outstanding_cents > 0" @submit.prevent="submitPayment">
                    <Field :label="t('admin.orders.record_payment')" :error="paymentForm.errors.amount">
                        <Input v-model:value="paymentForm.reference" :placeholder="t('admin.common.reference_optional')" dir="ltr" />
                    </Field>
                    <Button html-type="submit" block :loading="paymentForm.processing">
                        {{ t('admin.orders.record_full_payment', { amount: order.outstanding_formatted }) }}
                    </Button>
                </form>
            </Card>

            <Card size="small" :title="t('admin.common.refund')">
                <ul v-if="order.refunds.length > 0" class="mb-3 space-y-1">
                    <li v-for="r in order.refunds" :key="r.id">
                        <Text type="secondary" class="!text-xs">{{ r.amount_formatted }} · {{ r.method }} · {{ r.at }}</Text>
                        <Text v-if="r.reason" type="secondary" class="block !text-xs opacity-70">{{ r.reason }}</Text>
                    </li>
                </ul>

                <!-- After the customer has the goods, returning and refunding is
                     one action so the reason, the money and the stock stay in sync. -->
                <template v-if="order.can.refund && order.can_resolve_issue">
                    <Button v-if="!issueOpen" danger block @click="openIssue">
                        {{ t('admin.orders.report_issue') }}
                    </Button>
                    <form v-else @submit.prevent="submitIssue">
                        <Field :label="t('admin.orders.issue_reason')" :error="issueForm.errors.reason">
                            <Textarea v-model:value="issueForm.reason" :rows="2" />
                        </Field>
                        <Field :label="t('admin.orders.refund_amount')" :hint="t('admin.orders.refund_amount_hint')" :error="issueForm.errors.amount">
                            <InputNumber v-model:value="issueForm.amount" :min="0" :step="0.01" class="w-full" />
                        </Field>
                        <Field :label="t('admin.common.method')" :error="issueForm.errors.method">
                            <AdminSelect v-model="issueForm.method" :options="refundMethods" />
                        </Field>
                        <Input v-model:value="issueForm.reference" :placeholder="t('admin.common.reference_optional')" dir="ltr" class="mb-3" />
                        <Checkbox v-model:checked="issueForm.restock" class="mb-2">{{ t('admin.orders.restock_items') }}</Checkbox>
                        <div v-if="issueForm.restock" class="mb-4 space-y-2">
                            <Text type="secondary" class="block !text-xs">{{ t('admin.orders.items_back') }}</Text>
                            <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between gap-2">
                                <Text class="!text-sm">{{ item.name }}</Text>
                                <InputNumber v-model:value="issueForm.items[item.id]" :min="0" :max="item.returnable" :precision="0" class="!w-20" />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                danger
                                html-type="submit"
                                class="flex-1"
                                :loading="issueForm.processing"
                                :disabled="issueForm.reason.trim() === ''"
                            >
                                {{ t('admin.common.confirm') }}
                            </Button>
                            <Button @click="issueOpen = false">{{ t('admin.common.cancel') }}</Button>
                        </div>
                    </form>
                </template>

                <!-- An order already sent back can still need money returned. -->
                <form v-else-if="order.can.refund" @submit.prevent="submitRefund">
                    <Field :label="t('admin.common.amount')" :error="refundForm.errors.amount">
                        <InputNumber v-model:value="refundForm.amount" :min="0.01" :step="0.01" class="w-full" />
                    </Field>
                    <Field :label="t('admin.common.method')" :error="refundForm.errors.method">
                        <AdminSelect v-model="refundForm.method" :options="refundMethods" />
                    </Field>
                    <Input v-model:value="refundForm.reference" :placeholder="t('admin.common.reference_optional')" dir="ltr" class="mb-3" />
                    <Input v-model:value="refundForm.reason" :placeholder="t('admin.common.reason_optional')" class="mb-4" />
                    <Button html-type="submit" block :loading="refundForm.processing">
                        {{ t('admin.orders.issue_refund') }}
                    </Button>
                </form>
            </Card>
        </div>
    </div>
</template>
