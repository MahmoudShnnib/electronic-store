<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Address {
    name?: string;
    phone?: string;
    country?: string;
    city?: string;
    area?: string | null;
}

defineProps<{
    order: {
        number: string;
        created_at: string;
        customer: { name?: string; email?: string; phone?: string };
        billing_address: Address;
        shipping_address: Address;
        payment_method: string;
        items: { name: string; sku: string; quantity: number; unit_price_formatted: string; line_total_formatted: string }[];
        subtotal_formatted: string;
        discount_formatted: string;
        tax_formatted: string;
        total_formatted: string;
    };
    store: { name: string; details: string | null };
}>();

function formatAddress(address: Address): string {
    return [address.area, address.city, address.country].filter(Boolean).join(', ');
}

function printInvoice(): void {
    window.print();
}
</script>

<template>
    <Head :title="t('admin.orders.invoice_number', { number: order.number })" />

    <div class="min-h-screen bg-white p-8 text-gray-900 print:p-0">
        <div class="mx-auto max-w-3xl">
            <div class="mb-8 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ store.name }}</h1>
                    <p v-if="store.details" class="mt-1 whitespace-pre-line text-sm text-gray-500">{{ store.details }}</p>
                </div>
                <div class="text-end">
                    <h2 class="text-xl font-semibold">{{ t('admin.orders.invoice') }}</h2>
                    <p class="text-sm text-gray-500">{{ order.number }}</p>
                    <p class="text-sm text-gray-500">{{ order.created_at }}</p>
                </div>
            </div>

            <div class="mb-8 grid grid-cols-2 gap-8 text-sm">
                <div>
                    <h3 class="mb-1 font-semibold uppercase tracking-wide text-gray-400">{{ t('admin.orders.bill_to') }}</h3>
                    <p class="font-medium">{{ order.billing_address.name ?? order.customer.name }}</p>
                    <p>{{ formatAddress(order.billing_address) }}</p>
                    <p>{{ order.billing_address.phone ?? order.customer.phone }}</p>
                    <p>{{ order.customer.email }}</p>
                </div>
                <div>
                    <h3 class="mb-1 font-semibold uppercase tracking-wide text-gray-400">{{ t('admin.orders.ship_to') }}</h3>
                    <p class="font-medium">{{ order.shipping_address.name }}</p>
                    <p>{{ formatAddress(order.shipping_address) }}</p>
                    <p>{{ order.shipping_address.phone }}</p>
                    <p class="mt-2 text-gray-500">{{ t('admin.orders.payment_label', { method: order.payment_method }) }}</p>
                </div>
            </div>

            <table class="mb-8 w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-900 text-start text-xs font-semibold uppercase tracking-wide">
                        <th class="py-2 text-start">{{ t('admin.common.item') }}</th>
                        <th class="py-2 text-start">{{ t('admin.common.sku') }}</th>
                        <th class="py-2 text-end">{{ t('admin.common.qty') }}</th>
                        <th class="py-2 text-end">{{ t('admin.common.unit_price') }}</th>
                        <th class="py-2 text-end">{{ t('admin.common.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, i) in order.items" :key="i" class="border-b border-gray-200">
                        <td class="py-2">{{ item.name }}</td>
                        <td class="py-2 text-gray-500">{{ item.sku }}</td>
                        <td class="py-2 text-end">{{ item.quantity }}</td>
                        <td class="py-2 text-end">{{ item.unit_price_formatted }}</td>
                        <td class="py-2 text-end">{{ item.line_total_formatted }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mb-12 flex justify-end">
                <dl class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ t('admin.common.subtotal') }}</dt>
                        <dd>{{ order.subtotal_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ t('admin.common.discount') }}</dt>
                        <dd>−{{ order.discount_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ t('admin.common.tax') }}</dt>
                        <dd>{{ order.tax_formatted }}</dd>
                    </div>
                    <div class="flex justify-between border-t-2 border-gray-900 pt-2 text-base font-bold">
                        <dt>{{ t('admin.common.total') }}</dt>
                        <dd>{{ order.total_formatted }}</dd>
                    </div>
                </dl>
            </div>

            <p class="text-center text-xs text-gray-400">{{ t('admin.orders.thank_you') }}</p>

            <div class="mt-8 text-center print:hidden">
                <button type="button" class="rounded-md bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="printInvoice">
                    {{ t('admin.orders.print_invoice') }}
                </button>
            </div>
        </div>
    </div>
</template>
