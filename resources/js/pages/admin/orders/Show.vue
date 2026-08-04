<script setup lang="ts">
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option } from '@/components/admin/types';
import { btnPrimary, btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
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

function formatAddress(address: Address): string {
    return [address.name, address.phone, [address.area, address.city, address.country].filter(Boolean).join(', '), address.notes]
        .filter(Boolean)
        .join(' · ');
}
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
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.tax') }}</th>
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
                            <td class="px-3 py-2.5 text-end">{{ item.tax_formatted }}</td>
                            <td class="px-3 py-2.5 text-end font-medium">{{ item.line_total_formatted }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-gray-200 text-sm dark:border-gray-700">
                        <tr>
                            <td colspan="5" />
                            <td class="px-3 py-1.5 pt-3 text-end text-gray-500">{{ t('admin.common.subtotal') }}</td>
                            <td class="px-3 py-1.5 pt-3 text-end">{{ order.subtotal_formatted }}</td>
                        </tr>
                        <tr>
                            <td colspan="5" />
                            <td class="px-3 py-1.5 text-end text-gray-500">
                                {{ t('admin.common.discount') }}<span v-if="order.coupon_code" class="ms-1 text-xs">({{ order.coupon_code }})</span>
                            </td>
                            <td class="px-3 py-1.5 text-end">−{{ order.discount_formatted }}</td>
                        </tr>
                        <tr>
                            <td colspan="5" />
                            <td class="px-3 py-1.5 text-end text-gray-500">{{ t('admin.common.tax') }}</td>
                            <td class="px-3 py-1.5 text-end">{{ order.tax_formatted }}</td>
                        </tr>
                        <tr>
                            <td colspan="5" />
                        </tr>
                        <tr class="font-semibold text-gray-900 dark:text-gray-100">
                            <td colspan="5" />
                            <td class="px-3 py-2 text-end">{{ t('admin.common.total') }}</td>
                            <td class="px-3 py-2 text-end">{{ order.total_formatted }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <div class="grid gap-6 md:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.customer') }}</h2>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ order.customer.name }}</p>
                    <p class="text-sm text-gray-500">{{ order.customer.email }}</p>
                    <p class="text-sm text-gray-500">{{ order.customer.phone }}</p>
                    <Link
                        v-if="order.account"
                        :href="route('admin.customers.show', order.account.id)"
                        class="mt-2 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
                        >{{ t('admin.orders.view_account') }}</Link
                    >
                    <p v-else class="mt-2 text-xs text-gray-400">{{ t('admin.orders.guest_checkout') }}</p>

                    <h3 class="mt-4 text-xs font-semibold uppercase text-gray-400">{{ t('admin.orders.shipping_address') }}</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ formatAddress(order.shipping_address) }}</p>

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
                <form v-if="order.allowed_transitions.length > 0" class="grid gap-3" @submit.prevent="submitStatus">
                    <Field :label="t('admin.common.move_to')" :error="statusForm.errors.status">
                        <select v-model="statusForm.status" :class="inputClass">
                            <option value="" disabled>{{ t('admin.common.choose') }}</option>
                            <option v-for="t in order.allowed_transitions" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </Field>
                    <Field :label="t('admin.common.comment')" :error="statusForm.errors.comment">
                        <input v-model="statusForm.comment" type="text" :class="inputClass" />
                    </Field>
                    <button type="submit" :disabled="statusForm.processing || statusForm.status === ''" :class="btnPrimary" class="justify-center">
                        {{ t('admin.orders.update_status') }}
                    </button>
                </form>
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
                    <li v-for="r in order.refunds" :key="r.id">{{ r.amount_formatted }} · {{ r.method }} · {{ r.at }}</li>
                </ul>
                <form class="grid gap-3" @submit.prevent="submitRefund">
                    <Field :label="t('admin.common.amount')" :error="refundForm.errors.amount">
                        <input v-model.number="refundForm.amount" type="number" step="0.01" min="0.01" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.common.method')" :error="refundForm.errors.method">
                        <select v-model="refundForm.method" :class="inputClass">
                            <option value="cash">{{ t('admin.orders.cash') }}</option>
                            <option value="bank_transfer">{{ t('admin.orders.bank_transfer') }}</option>
                            <option value="store_credit">{{ t('admin.orders.store_credit') }}</option>
                            <option value="original">{{ t('admin.orders.original_method') }}</option>
                        </select>
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

            <section
                v-if="order.returns.length > 0"
                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.nav.returns') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li v-for="r in order.returns" :key="r.id" class="flex items-center justify-between">
                        <Link :href="route('admin.returns.show', r.id)" class="text-blue-600 hover:underline dark:text-blue-400">
                            {{ r.rma_number }}
                        </Link>
                        <span class="text-xs text-gray-400">{{ r.status_label }}</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.orders.internal_note') }}</h2>
                <form class="grid gap-3" @submit.prevent="submitNote">
                    <textarea v-model="noteForm.internal_note" rows="3" :class="inputClass" />
                    <button type="submit" :disabled="noteForm.processing" :class="btnSecondary" class="justify-center">
                        {{ t('admin.orders.save_note') }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</template>
