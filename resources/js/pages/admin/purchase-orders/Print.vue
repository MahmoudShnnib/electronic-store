<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    purchaseOrder: {
        po_number: string;
        created_at: string;
        status_label: string;
        sent_at: string | null;
        expected_at: string | null;
        supplier: { company_name: string; contact_person: string | null; email: string | null; phone: string | null; address: string | null };
        warehouse: { name: string; address: string | null };
        notes: string | null;
        items: { name: string; sku: string; quantity_ordered: number; unit_cost_formatted: string; line_total_formatted: string }[];
        discount_formatted: string;
        shipping_formatted: string;
        total_formatted: string;
    };
    store: { name: string; details: string | null };
}>();

function printDocument(): void {
    window.print();
}
</script>

<template>
    <Head :title="purchaseOrder.po_number" />

    <div class="min-h-screen bg-white p-8 text-gray-900 print:p-0">
        <div class="mx-auto max-w-3xl">
            <div class="mb-8 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ store.name }}</h1>
                    <p v-if="store.details" dir="auto" class="mt-1 whitespace-pre-line text-sm text-gray-500">{{ store.details }}</p>
                </div>
                <div class="text-end">
                    <h2 class="text-xl font-semibold">{{ t('admin.purchase_orders.document_title') }}</h2>
                    <p class="text-sm text-gray-500">{{ purchaseOrder.po_number }}</p>
                    <p class="text-sm text-gray-500">{{ purchaseOrder.created_at }}</p>
                </div>
            </div>

            <div class="mb-8 grid grid-cols-2 gap-8 text-sm">
                <div>
                    <h3 class="mb-1 font-semibold uppercase tracking-wide text-gray-400">{{ t('admin.purchase_orders.supplier_label') }}</h3>
                    <p class="font-medium">{{ purchaseOrder.supplier.company_name }}</p>
                    <p v-if="purchaseOrder.supplier.contact_person">{{ purchaseOrder.supplier.contact_person }}</p>
                    <p v-if="purchaseOrder.supplier.address" class="whitespace-pre-line">{{ purchaseOrder.supplier.address }}</p>
                    <p v-if="purchaseOrder.supplier.phone">{{ purchaseOrder.supplier.phone }}</p>
                    <p v-if="purchaseOrder.supplier.email">{{ purchaseOrder.supplier.email }}</p>
                </div>
                <div>
                    <h3 class="mb-1 font-semibold uppercase tracking-wide text-gray-400">{{ t('admin.purchase_orders.deliver_to') }}</h3>
                    <p class="font-medium">{{ purchaseOrder.warehouse.name }}</p>
                    <p v-if="purchaseOrder.warehouse.address" class="whitespace-pre-line">{{ purchaseOrder.warehouse.address }}</p>
                    <p v-if="purchaseOrder.expected_at" class="mt-2 text-gray-500">
                        {{ t('admin.purchase_orders.expected_by', { date: purchaseOrder.expected_at }) }}
                    </p>
                </div>
            </div>

            <table class="mb-8 w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-900 text-xs font-semibold uppercase tracking-wide">
                        <th class="py-2 text-start">{{ t('admin.common.item') }}</th>
                        <th class="py-2 text-start">{{ t('admin.common.sku') }}</th>
                        <th class="py-2 text-end">{{ t('admin.common.qty') }}</th>
                        <th class="py-2 text-end">{{ t('admin.common.unit_cost') }}</th>
                        <th class="py-2 text-end">{{ t('admin.common.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, i) in purchaseOrder.items" :key="i" class="border-b border-gray-200">
                        <td class="py-2">{{ item.name }}</td>
                        <td class="py-2 text-gray-500">{{ item.sku }}</td>
                        <td class="py-2 text-end">{{ item.quantity_ordered }}</td>
                        <td class="py-2 text-end">{{ item.unit_cost_formatted }}</td>
                        <td class="py-2 text-end">{{ item.line_total_formatted }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mb-8 flex justify-end">
                <dl class="w-64 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ t('admin.common.discount') }}</dt>
                        <dd>
                            <bdi dir="ltr">−{{ purchaseOrder.discount_formatted }}</bdi>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">{{ t('admin.common.shipping') }}</dt>
                        <dd>{{ purchaseOrder.shipping_formatted }}</dd>
                    </div>
                    <div class="flex justify-between border-t-2 border-gray-900 pt-2 text-base font-bold">
                        <dt>{{ t('admin.common.total') }}</dt>
                        <dd>{{ purchaseOrder.total_formatted }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="purchaseOrder.notes" class="mb-12 text-sm">
                <h3 class="mb-1 font-semibold uppercase tracking-wide text-gray-400">{{ t('admin.common.notes') }}</h3>
                <p class="whitespace-pre-line">{{ purchaseOrder.notes }}</p>
            </div>

            <div class="mt-8 text-center print:hidden">
                <Button type="primary" @click="printDocument">{{ t('admin.purchase_orders.print') }}</Button>
            </div>
        </div>
    </div>
</template>
