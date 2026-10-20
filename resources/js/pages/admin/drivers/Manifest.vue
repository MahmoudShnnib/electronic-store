<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

/**
 * A driver's run sheet on A4: one row per parcel, the cash to collect, and a
 * signature box — signed at the door, counted against the total when the
 * driver comes back. Printed through the browser.
 */
const { t } = useI18n();

defineProps<{
    driver: { id: number; name: string; phone: string | null };
    store: string;
    printed_at: string;
    orders: {
        id: number;
        number: string;
        name: string;
        phone: string | null;
        address: string;
        collect_formatted: string | null;
        shipped_at: string;
    }[];
    total_formatted: string;
}>();

function print(): void {
    window.print();
}
</script>

<template>
    <Head :title="t('admin.drivers.manifest_title', { name: driver.name })" />

    <div class="min-h-screen bg-gray-100 p-6 text-black print:bg-white print:p-0">
        <div class="mx-auto max-w-4xl">
            <div class="mb-4 flex justify-end print:hidden">
                <Button type="primary" @click="print">{{ t('admin.drivers.print') }}</Button>
            </div>

            <div class="bg-white p-8 print:p-0">
                <header class="mb-6 flex items-start justify-between gap-4 border-b border-black pb-4">
                    <div>
                        <h1 class="text-xl font-bold">{{ t('admin.drivers.manifest_title', { name: driver.name }) }}</h1>
                        <p class="text-sm">
                            <bdi dir="ltr">{{ driver.phone }}</bdi>
                        </p>
                    </div>
                    <div class="text-end text-sm">
                        <p class="font-bold">{{ store }}</p>
                        <p>{{ t('admin.drivers.printed_at', { at: printed_at }) }}</p>
                    </div>
                </header>

                <p v-if="orders.length === 0" class="py-10 text-center">{{ t('admin.drivers.empty_manifest') }}</p>

                <table v-else class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b-2 border-black text-start">
                            <th class="w-8 py-2 text-start">#</th>
                            <th class="py-2 text-start">{{ t('admin.drivers.order') }}</th>
                            <th class="py-2 text-start">{{ t('admin.drivers.recipient') }}</th>
                            <th class="py-2 text-start">{{ t('admin.drivers.address') }}</th>
                            <th class="py-2 text-end">{{ t('admin.drivers.collect') }}</th>
                            <th class="w-32 py-2 text-start">{{ t('admin.drivers.signature') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(order, index) in orders" :key="order.id" class="break-inside-avoid border-b border-gray-400 align-top">
                            <td class="py-3">{{ index + 1 }}</td>
                            <td class="py-3 font-semibold">{{ order.number }}</td>
                            <td class="py-3">
                                <span dir="auto" class="block">{{ order.name }}</span>
                                <bdi dir="ltr" class="font-semibold">{{ order.phone }}</bdi>
                            </td>
                            <td dir="auto" class="py-3">{{ order.address }}</td>
                            <td class="py-3 text-end font-bold">{{ order.collect_formatted ?? t('admin.drivers.paid') }}</td>
                            <td class="py-3"><span class="block h-8 border-b border-gray-500" /></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-black">
                            <td colspan="4" class="py-3 font-bold">{{ t('admin.drivers.total_collect') }}</td>
                            <td class="py-3 text-end text-lg font-bold">{{ total_formatted }}</td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>
