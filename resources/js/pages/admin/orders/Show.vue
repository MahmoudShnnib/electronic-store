<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import HelpHint from '@/components/admin/HelpHint.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PhoneLinks from '@/components/admin/PhoneLinks.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatMoney } from '@/lib/money';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button, Card, Checkbox, Descriptions, DescriptionsItem, Divider, Input, Textarea, Timeline, TimelineItem, Typography } from 'ant-design-vue';
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
        /** The part keyed in by hand at the register, with the cashier's reason. */
        manual_discount_formatted: string | null;
        manual_discount_reason: string | null;
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
        /** Rung up at a till: returned there, not here. */
        is_counter_sale: boolean;
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
        driver: { id: number; name: string; phone: string | null } | null;
        shipped_at: string | null;
        confirmed_at: string | null;
        needs_confirmation: boolean;
        call_attempts: { id: number; outcome: 'confirmed' | 'no_answer'; note: string | null; user: string | null; at: string }[];
    };
    drivers: { id: number; name: string }[];
    unconfirmedCancelHours: number;
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

/**
 * Actions that move money (completing books the outstanding cash, payments and
 * refunds post to the treasury) and can't be undone from here, so each one asks
 * first and names the amount.
 */
const pendingConfirm = ref<{ title: string; message: string; ok: string; danger?: boolean; run: () => void } | null>(null);

function runConfirmed(): void {
    const action = pendingConfirm.value;
    pendingConfirm.value = null;
    action?.run();
}

function pickStatus(status: string): void {
    if (reasonRequired.includes(status)) {
        pendingStatus.value = pendingStatus.value === status ? null : status;
        statusForm.comment = '';

        return;
    }

    if (status === 'completed') {
        pendingConfirm.value = {
            title: t('admin.orders.confirm_complete_title'),
            message:
                props.order.outstanding_cents > 0
                    ? t('admin.orders.confirm_complete_cash', { amount: props.order.outstanding_formatted })
                    : t('admin.orders.confirm_complete_paid'),
            ok: t('admin.orders.confirm_complete_ok'),
            run: () => submitStatus(status),
        };

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
function confirmPayment(): void {
    pendingConfirm.value = {
        title: t('admin.orders.confirm_payment_title'),
        message: t('admin.orders.confirm_payment_message', { amount: props.order.outstanding_formatted }),
        ok: t('admin.orders.confirm_payment_ok', { amount: props.order.outstanding_formatted }),
        run: submitPayment,
    };
}

function submitPayment(): void {
    paymentForm.amount = props.order.outstanding_cents / 100;
    paymentForm.post(route('admin.orders.payments.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset(),
    });
}

const refundForm = useForm({ amount: null as number | null, method: 'cash', reference: '', reason: '' });

/** No part refunds: what goes back is always everything still held. */
const heldCents = computed(() => Math.max(0, props.order.paid_cents - props.order.refunded_cents));

const page = usePage();
const heldLabel = computed(() => formatMoney(heldCents.value / 100, (page.props.locale as string) ?? 'en'));

function confirmRefund(): void {
    const method = refundMethods.value.find((option) => option.value === refundForm.method)?.label ?? refundForm.method;

    pendingConfirm.value = {
        title: t('admin.orders.confirm_refund_title'),
        // No part refunds: everything still held goes back.
        message: t('admin.orders.confirm_refund_message', { amount: heldLabel.value, method }),
        ok: t('admin.orders.confirm_refund_ok', { amount: heldLabel.value }),
        danger: true,
        run: submitRefund,
    };
}

function submitRefund(): void {
    refundForm.amount = heldCents.value / 100;
    refundForm.post(route('admin.refunds.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => refundForm.reset(),
    });
}

/**
 * The single post-completion action: always records a reason. Returns are whole:
 * ticking "full return" takes every unit back and refunds everything held; left
 * unticked, the reason is only logged and no money or stock moves.
 */
const issueForm = useForm({
    reason: '',
    amount: 0,
    items: {} as Record<number, number>,
    method: props.order.default_refund_method,
    restock: false,
    reference: '',
});

const issueOpen = ref(false);

/** Opens the return form starting from the order as it is now (a payment may have landed since the page loaded). */
function openIssue(): void {
    issueForm.restock = false;
    issueOpen.value = true;
}

function submitIssue(): void {
    issueForm.amount = issueForm.restock ? heldCents.value / 100 : 0;
    issueForm.items = issueForm.restock ? Object.fromEntries(props.order.items.map((item) => [item.id, item.returnable])) : {};
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

/** A confirmation call: confirmed, or another unanswered try. */
const callForm = useForm({ outcome: '', note: '' });

function recordCall(outcome: 'confirmed' | 'no_answer'): void {
    callForm.outcome = outcome;
    callForm.post(route('admin.orders.calls.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => callForm.reset(),
    });
}

/** Handing the parcel to one of our drivers, or taking it back. */
const dispatchForm = useForm({ driver_id: null as number | string | null });

const driverOptions = computed(() => props.drivers.map((driver) => ({ value: driver.id, label: driver.name })));

function handToDriver(): void {
    dispatchForm.post(route('admin.orders.dispatch', props.order.id), { preserveScroll: true, onSuccess: () => dispatchForm.reset() });
}

function recallFromDriver(): void {
    dispatchForm.delete(route('admin.orders.dispatch.recall', props.order.id), { preserveScroll: true });
}

/** Shown wherever the customer left a field empty, so the gap is obvious. */
const blank = '—';

/**
 * One labelled row per field, always rendered. Joining the address into a single
 * ` · `-separated string made a mistyped city indistinguishable from an area or
 * a delivery note, and dropping the empty rows hid the missing value entirely.
 */
function addressRows(address: Address): { label: string; value: string; phone?: boolean }[] {
    const rows: { label: string; value: string | null | undefined; phone?: boolean }[] = [
        { label: t('admin.common.recipient'), value: address.name },
        { label: t('admin.common.phone'), value: address.phone, phone: true },
        { label: t('admin.common.city'), value: address.city },
        { label: t('admin.common.area'), value: address.area },
    ];

    if (address.notes) {
        rows.push({ label: t('admin.common.notes'), value: address.notes });
    }

    return rows.map((row) => ({ label: row.label, value: row.value ? String(row.value) : blank, phone: row.phone }));
}

/** The opening line staff send on WhatsApp — names the shop and the order so the customer knows who it is. */
const whatsappMessage = computed(() =>
    t('admin.orders.whatsapp_message', {
        name: props.order.customer.name ?? '',
        store: (page.props.name as string) ?? '',
        number: props.order.number,
    }),
);
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
                        <DescriptionsItem v-if="order.manual_discount_formatted" :label="t('admin.orders.manual_discount')">
                            <span class="flex flex-col items-end">
                                <bdi dir="ltr">−{{ order.manual_discount_formatted }}</bdi>
                                <span v-if="order.manual_discount_reason" class="text-xs text-ad-muted" dir="auto">{{
                                    order.manual_discount_reason
                                }}</span>
                            </span>
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
                            <PhoneLinks :phone="order.customer.phone" :message="whatsappMessage" />
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
                            <PhoneLinks v-if="row.phone" :phone="row.value === blank ? null : row.value" :message="whatsappMessage" />
                            <template v-else>{{ row.value }}</template>
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
            <!-- Before it leaves: confirm by phone; then: which driver has it. -->
            <Card v-if="!order.is_counter_sale" size="small" :title="t('admin.orders.delivery')">
                <div class="space-y-3">
                    <div>
                        <Text strong class="block !text-xs">{{ t('admin.orders.confirmation') }}</Text>
                        <Text v-if="order.confirmed_at" type="success">{{ t('admin.orders.confirmed_at', { at: order.confirmed_at }) }}</Text>
                        <template v-else>
                            <Text type="warning" class="block">{{ t('admin.orders.not_confirmed') }}</Text>
                            <template v-if="order.needs_confirmation && order.can.manage">
                                <Text type="secondary" class="mb-2 block !text-xs">
                                    {{ t('admin.orders.confirm_hint', { hours: unconfirmedCancelHours }) }}
                                </Text>
                                <Input v-model:value="callForm.note" :placeholder="t('admin.orders.call_note')" class="mb-2" :maxlength="500" />
                                <div class="flex gap-2">
                                    <Button type="primary" class="flex-1" :loading="callForm.processing" @click="recordCall('confirmed')">
                                        {{ t('admin.orders.mark_confirmed') }}
                                    </Button>
                                    <Button class="flex-1" :loading="callForm.processing" @click="recordCall('no_answer')">
                                        {{ t('admin.orders.mark_no_answer') }}
                                    </Button>
                                </div>
                                <Text v-if="callForm.errors.outcome" type="danger" class="mt-1 block !text-xs">{{ callForm.errors.outcome }}</Text>
                            </template>
                        </template>
                        <ul v-if="order.call_attempts.length > 0" class="mt-2 space-y-1">
                            <li v-for="call in order.call_attempts" :key="call.id">
                                <Text type="secondary" class="!text-xs">
                                    {{ call.outcome === 'confirmed' ? t('admin.orders.call_confirmed') : t('admin.orders.call_no_answer') }} ·
                                    {{ call.user ?? '—' }} · {{ call.at }}<template v-if="call.note"> · {{ call.note }}</template>
                                </Text>
                            </li>
                        </ul>
                    </div>

                    <Divider class="!my-2" />

                    <div>
                        <Text strong class="block !text-xs">{{ t('admin.orders.driver') }}</Text>
                        <template v-if="order.driver && order.shipped_at">
                            <Text class="block">{{ t('admin.orders.with_driver', { name: order.driver.name, at: order.shipped_at }) }}</Text>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a :href="route('admin.drivers.manifest', order.driver.id)" target="_blank" rel="noopener">
                                    <Button size="small">{{ t('admin.orders.view_manifest') }}</Button>
                                </a>
                                <Button
                                    v-if="order.can.manage && order.status === 'in_progress'"
                                    size="small"
                                    :loading="dispatchForm.processing"
                                    @click="recallFromDriver"
                                >
                                    {{ t('admin.orders.recall_from_driver') }}
                                </Button>
                            </div>
                        </template>
                        <template v-else>
                            <Text type="secondary" class="block">{{ t('admin.orders.in_shop') }}</Text>
                            <template v-if="order.can.manage && order.status === 'in_progress'">
                                <div v-if="drivers.length > 0" class="mt-2 flex gap-2">
                                    <AdminSelect
                                        v-model="dispatchForm.driver_id"
                                        :options="driverOptions"
                                        :empty-label="t('admin.orders.choose_driver')"
                                        class="flex-1"
                                    />
                                    <Button
                                        type="primary"
                                        :disabled="!dispatchForm.driver_id"
                                        :loading="dispatchForm.processing"
                                        @click="handToDriver"
                                    >
                                        {{ t('admin.orders.hand_to_driver') }}
                                    </Button>
                                </div>
                                <Link v-else :href="route('admin.drivers.index')" class="admin-link mt-2 inline-block text-sm hover:underline">
                                    {{ t('admin.orders.add_driver') }}
                                </Link>
                                <Text v-if="dispatchForm.errors.driver_id" type="danger" class="mt-1 block !text-xs">{{
                                    dispatchForm.errors.driver_id
                                }}</Text>
                            </template>
                        </template>
                    </div>
                </div>
            </Card>

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
                <form v-if="order.can.manage && order.outstanding_cents > 0" @submit.prevent="confirmPayment">
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
                        <Checkbox v-model:checked="issueForm.restock" class="mb-2">{{ t('admin.orders.full_return') }}</Checkbox>
                        <Text v-if="!issueForm.restock" type="secondary" class="mb-3 block !text-xs">{{ t('admin.orders.log_only_hint') }}</Text>
                        <div v-else class="mb-4 space-y-2">
                            <Text type="secondary" class="block !text-xs">{{ t('admin.orders.items_back_all') }}</Text>
                            <div v-for="item in order.items" :key="item.id" class="flex items-center justify-between gap-2">
                                <Text class="!text-sm">{{ item.name }}</Text>
                                <Text class="!text-sm">× {{ item.returnable }}</Text>
                            </div>
                            <Text v-if="issueForm.errors.items" type="danger" class="block !text-xs">{{ issueForm.errors.items }}</Text>
                            <Text v-if="issueForm.errors.amount" type="danger" class="block !text-xs">{{ issueForm.errors.amount }}</Text>
                            <Field :label="t('admin.common.method')" :error="issueForm.errors.method">
                                <AdminSelect v-model="issueForm.method" :options="refundMethods" />
                            </Field>
                            <Input v-model:value="issueForm.reference" :placeholder="t('admin.common.reference_optional')" dir="ltr" />
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

                <Text v-else-if="order.is_counter_sale && order.status === 'completed'" type="secondary" class="block !text-xs">
                    {{ t('admin.orders.counter_sale_return_hint') }}
                </Text>

                <!-- An order already sent back can still need money returned. -->
                <form v-else-if="order.can.refund && heldCents > 0 && !order.is_counter_sale" @submit.prevent="confirmRefund">
                    <Text v-if="refundForm.errors.amount" type="danger" class="mb-2 block !text-xs">{{ refundForm.errors.amount }}</Text>
                    <Field :label="t('admin.common.method')" :error="refundForm.errors.method">
                        <AdminSelect v-model="refundForm.method" :options="refundMethods" />
                    </Field>
                    <Input v-model:value="refundForm.reference" :placeholder="t('admin.common.reference_optional')" dir="ltr" class="mb-3" />
                    <Input v-model:value="refundForm.reason" :placeholder="t('admin.common.reason_optional')" class="mb-4" />
                    <Button html-type="submit" block :loading="refundForm.processing">
                        {{ t('admin.orders.refund_everything') }}
                    </Button>
                </form>
            </Card>
        </div>
    </div>

    <ConfirmDialog
        :open="pendingConfirm !== null"
        :title="pendingConfirm?.title"
        :message="pendingConfirm?.message ?? ''"
        :confirm-label="pendingConfirm?.ok"
        :danger="pendingConfirm?.danger"
        @confirm="runConfirmed"
        @close="pendingConfirm = null"
    />
</template>
