<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { btnPrimary, btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface PoItem {
    id: number;
    name: string;
    sku: string;
    quantity_ordered: number;
    quantity_received: number;
    outstanding: number;
    unit_cost_formatted: string;
    line_total_formatted: string;
}

const props = defineProps<{
    purchaseOrder: {
        id: number;
        po_number: string;
        supplier: { id: number; company_name: string; contact_person: string | null; email: string | null; phone: string | null };
        warehouse: { id: number; name: string };
        status: string;
        status_label: string;
        is_editable: boolean;
        is_receivable: boolean;
        subtotal_formatted: string;
        discount_formatted: string;
        tax_formatted: string;
        shipping_formatted: string;
        total_formatted: string;
        expected_at: string | null;
        received_at: string | null;
        notes: string | null;
        created_by: string | null;
        created_at: string;
        items: PoItem[];
    };
}>();

const receiveOpen = ref(false);
const cancelOpen = ref(false);

const receiveForm = useForm({
    quantities: {} as Record<number, number>,
});

function openReceive(): void {
    receiveForm.clearErrors();
    receiveForm.quantities = Object.fromEntries(props.purchaseOrder.items.filter((i) => i.outstanding > 0).map((i) => [i.id, i.outstanding]));
    receiveOpen.value = true;
}

function submitReceive(): void {
    receiveForm.post(route('admin.purchase-orders.receive', props.purchaseOrder.id), {
        preserveScroll: true,
        onSuccess: () => (receiveOpen.value = false),
    });
}

function send(): void {
    router.post(route('admin.purchase-orders.send', props.purchaseOrder.id), {}, { preserveScroll: true });
}

function cancel(): void {
    router.post(
        route('admin.purchase-orders.cancel', props.purchaseOrder.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (cancelOpen.value = false),
        },
    );
}
</script>

<template>
    <Head :title="purchaseOrder.po_number" />

    <PageHeader
        :title="purchaseOrder.po_number"
        :subtitle="t('admin.purchase_orders.created', { date: purchaseOrder.created_at, by: purchaseOrder.created_by ?? '—' })"
    >
        <template #actions>
            <Link :href="route('admin.purchase-orders.index')" :class="btnSecondary">{{ t('admin.common.back') }}</Link>
            <button v-if="purchaseOrder.is_editable" type="button" :class="btnPrimary" @click="send">
                {{ t('admin.purchase_orders.send_to_supplier') }}
            </button>
            <button v-if="purchaseOrder.is_receivable" type="button" :class="btnPrimary" @click="openReceive">
                {{ t('admin.purchase_orders.receive_stock') }}
            </button>
            <button
                v-if="purchaseOrder.status !== 'received' && purchaseOrder.status !== 'cancelled'"
                type="button"
                :class="btnSecondary"
                @click="cancelOpen = true"
            >
                {{ t('admin.purchase_orders.cancel_po') }}
            </button>
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950/50">
                        <tr class="text-start text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2.5 text-start">{{ t('admin.common.item') }}</th>
                            <th class="px-3 py-2.5 text-start">{{ t('admin.common.sku') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.purchase_orders.ordered') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.purchase_orders.received') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.outstanding') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.unit_cost') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="item in purchaseOrder.items" :key="item.id">
                            <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                            <td class="px-3 py-2.5 text-gray-500">{{ item.sku }}</td>
                            <td class="px-3 py-2.5 text-end">{{ item.quantity_ordered }}</td>
                            <td class="px-3 py-2.5 text-end">{{ item.quantity_received }}</td>
                            <td class="px-3 py-2.5 text-end" :class="item.outstanding > 0 ? 'font-semibold text-amber-600' : 'text-emerald-600'">
                                {{ item.outstanding }}
                            </td>
                            <td class="px-3 py-2.5 text-end">{{ item.unit_cost_formatted }}</td>
                            <td class="px-3 py-2.5 text-end font-medium">{{ item.line_total_formatted }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.status') }}</h2>
                    <StatusBadge :status="purchaseOrder.status" :label="purchaseOrder.status_label" />
                </div>
                <dl class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.warehouse') }}</dt>
                        <dd>{{ purchaseOrder.warehouse.name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.expected') }}</dt>
                        <dd>{{ purchaseOrder.expected_at ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.purchase_orders.fully_received') }}</dt>
                        <dd>{{ purchaseOrder.received_at ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.supplier') }}</h2>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ purchaseOrder.supplier.company_name }}</p>
                <p class="text-sm text-gray-500">{{ purchaseOrder.supplier.contact_person }}</p>
                <p class="text-sm text-gray-500">{{ purchaseOrder.supplier.email }}</p>
                <p class="text-sm text-gray-500">{{ purchaseOrder.supplier.phone }}</p>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.purchase_orders.totals') }}</h2>
                <dl class="space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.subtotal') }}</dt>
                        <dd>{{ purchaseOrder.subtotal_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.discount') }}</dt>
                        <dd>−{{ purchaseOrder.discount_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.tax') }}</dt>
                        <dd>{{ purchaseOrder.tax_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.shipping') }}</dt>
                        <dd>{{ purchaseOrder.shipping_formatted }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t border-gray-100 pt-2 text-base font-semibold text-gray-900 dark:border-gray-800 dark:text-gray-100"
                    >
                        <dt>{{ t('admin.common.total') }}</dt>
                        <dd>{{ purchaseOrder.total_formatted }}</dd>
                    </div>
                </dl>
            </section>

            <section
                v-if="purchaseOrder.notes"
                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.notes') }}</h2>
                <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ purchaseOrder.notes }}</p>
            </section>
        </div>
    </div>

    <!-- Receive modal -->
    <FormModal :open="receiveOpen" :title="t('admin.purchase_orders.receive_stock')" @close="receiveOpen = false">
        <form class="grid gap-4" @submit.prevent="submitReceive">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('admin.purchase_orders.receive_hint') }}</p>
            <div v-for="item in purchaseOrder.items.filter((i) => i.outstanding > 0)" :key="item.id" class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</p>
                    <p class="text-xs text-gray-400">{{ t('admin.purchase_orders.outstanding_n', { count: item.outstanding }) }}</p>
                </div>
                <input
                    v-model.number="receiveForm.quantities[item.id]"
                    type="number"
                    min="0"
                    :max="item.outstanding"
                    class="w-24"
                    :class="inputClass"
                />
            </div>
            <p v-if="receiveForm.errors.quantities" class="text-xs text-red-600">{{ receiveForm.errors.quantities }}</p>
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="receiveOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="receiveForm.processing" :class="btnPrimary">{{ t('admin.purchase_orders.receive') }}</button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="cancelOpen"
        :title="t('admin.purchase_orders.cancel_title')"
        :message="t('admin.purchase_orders.cancel_confirm', { number: purchaseOrder.po_number })"
        :confirm-label="t('admin.purchase_orders.cancel_po')"
        danger
        @confirm="cancel"
        @close="cancelOpen = false"
    />
</template>
