<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option } from '@/components/admin/types';
import { btnDanger, btnPrimary, btnSecondary, checkboxClass, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

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
        paid_formatted: string;
        outstanding_formatted: string;
        refunded_formatted: string;
        customer_note: string | null;
        can_resolve_issue: boolean;
        created_at: string;
        items: {
            id: number;
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

/**
 * One button per allowed transition — no dropdown. `returned` is left out
 * whenever the dedicated return/problem form below can handle it, so there is
 * exactly one way to send an order back.
 */
const quickTransitions = computed(() =>
    props.order.allowed_transitions.filter((option) => !(option.value === 'returned' && props.order.can_resolve_issue)),
);

/** Transitions that undo the sale — always ask for a reason before firing. */
const reasonRequired = ['cancelled', 'returned'];

const statusForm = useForm({ status: '', comment: '' });

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

/**
 * The single post-completion action: always records a reason, and refunds only
 * when an amount is entered. Defaults to giving back everything collected.
 */
const issueForm = useForm({
    reason: '',
    amount: props.order.paid_cents / 100,
    method: props.order.default_refund_method,
    restock: true,
    reference: '',
});

const issueOpen = ref(false);

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
    { value: 'store_credit', label: t('admin.orders.store_credit') },
]);

/** Shown wherever the customer left a field empty, so the gap is obvious. */
const blank = '—';
</script>

<template>
    <Head :title="order.number" />

    <PageHeader :title="order.number" :subtitle="t('admin.orders.placed', { date: order.created_at, via: order.placed_via })">
        <template #actions>
            <Link :href="route('admin.orders.index')" :class="btnSecondary">{{ t('admin.common.back') }}</Link>
            <Link :href="route('admin.orders.invoice', order.id)" :class="btnSecondary"
                ><Printer class="h-4 w-4" />{{ t('admin.orders.invoice') }}</Link
            >
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
        <!-- Left: items + notes + timeline -->
        <div class="space-y-6 xl:col-span-2">
            <section class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950/50">
                        <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2.5 text-start">{{ t('admin.common.item') }}</th>
                            <th class="px-3 py-2.5 text-start">{{ t('admin.common.sku') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.qty') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.unit') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.discount') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="item in order.items" :key="item.id">
                            <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                            <td class="px-3 py-2.5 text-gray-500">{{ item.sku }}</td>
                            <td class="px-3 py-2.5 text-end">{{ item.quantity }}</td>
                            <td class="px-3 py-2.5 text-end">{{ item.unit_price_formatted }}</td>
                            <td class="px-3 py-2.5 text-end">{{ item.discount_formatted }}</td>
                            <td class="px-3 py-2.5 text-end font-medium">{{ item.line_total_formatted }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 text-sm dark:border-gray-700">
                        <tr>
                            <td colspan="4" />
                            <td class="px-3 py-1.5 pt-3 text-end text-gray-500">
                                {{ t('admin.common.discount') }}<span v-if="order.coupon_code" class="ms-1 text-xs">({{ order.coupon_code }})</span>
                            </td>
                            <td class="px-3 py-1.5 pt-3 text-end">
                                <bdi dir="ltr">−{{ order.discount_formatted }}</bdi>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" />
                        </tr>
                        <tr class="font-semibold text-gray-900 dark:text-gray-100">
                            <td colspan="4" />
                            <td class="px-3 py-2 text-end">{{ t('admin.common.total') }}</td>
                            <td class="px-3 py-2 text-end">{{ order.total_formatted }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <div class="grid gap-6 md:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.customer') }}</h2>

                    <!-- Every field is labelled and always rendered, so a typo
                         or a missing value is visible instead of blending into
                         an unlabelled run of lines. -->
                    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm">
                        <dt class="text-gray-400">{{ t('admin.common.name') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ order.customer.name || blank }}</dd>
                        <dt class="text-gray-400">{{ t('admin.common.phone') }}</dt>
                        <dd class="text-gray-600 dark:text-gray-300" dir="ltr">{{ order.customer.phone || blank }}</dd>
                        <dt class="text-gray-400">{{ t('admin.common.email') }}</dt>
                        <dd class="text-gray-600 dark:text-gray-300" dir="ltr">{{ order.customer.email || blank }}</dd>
                    </dl>

                    <Link
                        v-if="order.account"
                        :href="route('admin.customers.show', order.account.id)"
                        class="mt-2 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
                        >{{ t('admin.orders.view_account') }}</Link
                    >
                    <p v-else class="mt-2 text-xs text-gray-400">{{ t('admin.orders.guest_checkout') }}</p>

                    <h3 class="mt-4 text-xs font-semibold uppercase text-gray-400">{{ t('admin.orders.shipping_address') }}</h3>
                    <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-sm">
                        <dt class="text-gray-400">{{ t('admin.common.recipient') }}</dt>
                        <dd class="text-gray-600 dark:text-gray-300">{{ order.shipping_address.name || blank }}</dd>
                        <dt class="text-gray-400">{{ t('admin.common.phone') }}</dt>
                        <dd class="text-gray-600 dark:text-gray-300" dir="ltr">{{ order.shipping_address.phone || blank }}</dd>
                        <dt class="text-gray-400">{{ t('admin.common.city') }}</dt>
                        <dd class="text-gray-600 dark:text-gray-300">{{ order.shipping_address.city || blank }}</dd>
                        <dt class="text-gray-400">{{ t('admin.common.area') }}</dt>
                        <dd class="text-gray-600 dark:text-gray-300">{{ order.shipping_address.area || blank }}</dd>
                        <template v-if="order.shipping_address.notes">
                            <dt class="text-gray-400">{{ t('admin.common.notes') }}</dt>
                            <dd class="text-gray-600 dark:text-gray-300">{{ order.shipping_address.notes }}</dd>
                        </template>
                    </dl>

                    <template v-if="order.customer_note">
                        <h3 class="mt-3 text-xs font-semibold uppercase text-gray-400">{{ t('admin.orders.customer_note') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ order.customer_note }}</p>
                    </template>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.orders.timeline') }}</h2>
                    <ol class="space-y-3">
                        <li v-for="(entry, i) in order.timeline" :key="i" class="relative ps-5">
                            <span class="absolute start-0 top-1.5 h-2 w-2 rounded-full bg-blue-500" />
                            <p class="text-sm text-gray-800 dark:text-gray-200">
                                <template v-if="entry.from">{{ entry.from }} {{ t('admin.orders.transition_arrow') }} </template>{{ entry.to }}
                            </p>
                            <p v-if="entry.comment" class="text-xs text-gray-500">{{ entry.comment }}</p>
                            <p class="text-xs text-gray-400">{{ entry.user }} · {{ entry.at }}</p>
                        </li>
                    </ol>
                </section>
            </div>
        </div>

        <!-- Right rail: actions -->
        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.orders.order_status') }}</h2>
                    <StatusBadge :status="order.status" :label="order.status_label" />
                </div>
                <template v-if="quickTransitions.length > 0">
                    <div class="grid gap-2">
                        <button
                            v-for="option in quickTransitions"
                            :key="option.value"
                            type="button"
                            :disabled="statusForm.processing"
                            :class="option.value === 'cancelled' ? btnDanger : btnPrimary"
                            class="justify-center"
                            @click="pickStatus(String(option.value))"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <!-- Undoing a sale needs a written reason; it lands in the timeline. -->
                    <div v-if="pendingStatus" class="mt-3 grid gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <Field :label="t('admin.orders.reason_required')" :error="statusForm.errors.comment">
                            <input v-model="statusForm.comment" type="text" :class="inputClass" />
                        </Field>
                        <button
                            type="button"
                            :disabled="statusForm.processing || statusForm.comment.trim() === ''"
                            :class="btnDanger"
                            class="justify-center"
                            @click="submitStatus(pendingStatus)"
                        >
                            {{ t('admin.common.confirm') }}
                        </button>
                    </div>
                    <p v-if="statusForm.errors.status" class="mt-2 text-sm text-red-600">{{ statusForm.errors.status }}</p>
                </template>
                <p v-else class="text-sm text-gray-400">{{ t('admin.orders.final_state') }}</p>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.payment') }}</h2>
                    <StatusBadge :status="order.payment_status" :label="order.payment_status_label" />
                </div>
                <dl class="mb-3 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.method') }}</dt>
                        <dd>{{ order.payment_method }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.paid') }}</dt>
                        <dd>{{ order.paid_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.outstanding') }}</dt>
                        <dd>{{ order.outstanding_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.refunded') }}</dt>
                        <dd>{{ order.refunded_formatted }}</dd>
                    </div>
                </dl>
                <ul v-if="order.payments.length > 0" class="mb-3 space-y-1 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-gray-800">
                    <li v-for="p in order.payments" :key="p.id">
                        {{ p.amount_formatted }} · {{ p.method }} · {{ p.reference ?? 'no ref' }} · {{ p.at }}
                    </li>
                </ul>
                <p class="mb-3 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-gray-950/50 dark:text-gray-400">
                    {{ t('admin.orders.treasury_hint') }}
                </p>
                <form class="grid gap-3 border-t border-gray-100 pt-3 dark:border-gray-800" @submit.prevent="submitPayment">
                    <Field :label="t('admin.orders.record_payment')" :error="paymentForm.errors.amount">
                        <input
                            v-model.number="paymentForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :placeholder="t('admin.common.amount')"
                            :class="inputClass"
                        />
                    </Field>
                    <input
                        v-model="paymentForm.reference"
                        type="text"
                        :placeholder="t('admin.common.reference_optional')"
                        :class="inputClass"
                        dir="ltr"
                    />
                    <button type="submit" :disabled="paymentForm.processing" :class="btnSecondary" class="justify-center">
                        {{ t('admin.orders.record_payment') }}
                    </button>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.refund') }}</h2>
                <ul v-if="order.refunds.length > 0" class="mb-3 space-y-1 text-xs text-gray-500">
                    <li v-for="r in order.refunds" :key="r.id">
                        {{ r.amount_formatted }} · {{ r.method }} · {{ r.at }}
                        <span v-if="r.reason" class="block text-gray-400">{{ r.reason }}</span>
                    </li>
                </ul>

                <!-- After the customer has the goods, returning and refunding is
                     one action so the reason, the money and the stock stay in sync. -->
                <template v-if="order.can_resolve_issue">
                    <button v-if="!issueOpen" type="button" :class="btnDanger" class="w-full justify-center" @click="issueOpen = true">
                        {{ t('admin.orders.report_issue') }}
                    </button>
                    <form v-else class="grid gap-3" @submit.prevent="submitIssue">
                        <Field :label="t('admin.orders.issue_reason')" :error="issueForm.errors.reason">
                            <textarea v-model="issueForm.reason" rows="2" :class="inputClass" />
                        </Field>
                        <Field :label="t('admin.orders.refund_amount')" :hint="t('admin.orders.refund_amount_hint')" :error="issueForm.errors.amount">
                            <input v-model.number="issueForm.amount" type="number" step="0.01" min="0" :class="inputClass" />
                        </Field>
                        <Field :label="t('admin.common.method')" :error="issueForm.errors.method">
                            <AdminSelect v-model="issueForm.method" :options="refundMethods" />
                        </Field>
                        <input
                            v-model="issueForm.reference"
                            type="text"
                            :placeholder="t('admin.common.reference_optional')"
                            :class="inputClass"
                            dir="ltr"
                        />
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input v-model="issueForm.restock" type="checkbox" :class="checkboxClass" />
                            {{ t('admin.orders.restock_items') }}
                        </label>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="issueForm.processing || issueForm.reason.trim() === ''"
                                :class="btnDanger"
                                class="flex-1 justify-center"
                            >
                                {{ t('admin.common.confirm') }}
                            </button>
                            <button type="button" :class="btnSecondary" @click="issueOpen = false">{{ t('admin.common.cancel') }}</button>
                        </div>
                    </form>
                </template>

                <!-- An order already sent back can still need money returned. -->
                <form v-else class="grid gap-3" @submit.prevent="submitRefund">
                    <Field :label="t('admin.common.amount')" :error="refundForm.errors.amount">
                        <input v-model.number="refundForm.amount" type="number" step="0.01" min="0.01" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.common.method')" :error="refundForm.errors.method">
                        <AdminSelect v-model="refundForm.method" :options="refundMethods" />
                    </Field>
                    <input
                        v-model="refundForm.reference"
                        type="text"
                        :placeholder="t('admin.common.reference_optional')"
                        :class="inputClass"
                        dir="ltr"
                    />
                    <input v-model="refundForm.reason" type="text" :placeholder="t('admin.common.reason_optional')" :class="inputClass" />
                    <button type="submit" :disabled="refundForm.processing" :class="btnSecondary" class="justify-center">
                        {{ t('admin.orders.issue_refund') }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</template>
