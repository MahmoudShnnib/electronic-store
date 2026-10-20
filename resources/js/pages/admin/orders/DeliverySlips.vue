<script setup lang="ts">
import DeliverySlipPaper from '@/components/admin/DeliverySlipPaper.vue';
import { Head } from '@inertiajs/vue3';
import { Button } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

/**
 * Every slip of a driver's run on one sheet, each on its own page, through
 * the browser's print dialog (the thermal printer takes one raster at a time,
 * so a run goes through the dialog with the roll selected).
 */
const { t } = useI18n();

type SlipOrder = InstanceType<typeof DeliverySlipPaper>['$props']['order'];

defineProps<{
    orders: SlipOrder[];
    store: { name: string; details: string | null; logo: string | null };
}>();

function printAll(): void {
    window.print();
}
</script>

<template>
    <Head :title="t('admin.orders.delivery_slips_count', { count: orders.length })" />

    <div class="min-h-screen bg-gray-100 py-6 print:bg-white print:py-0">
        <div class="mx-auto mb-4 max-w-[80mm] print:hidden">
            <Button type="primary" block @click="printAll">
                {{ t('admin.orders.print_delivery_slips', { count: orders.length }) }}
            </Button>
        </div>

        <div class="space-y-4 print:space-y-0">
            <!-- One slip per printed page: a run is torn apart and taped to parcels. -->
            <div v-for="order in orders" :key="order.id" class="break-after-page">
                <DeliverySlipPaper :order="order" :store="store" />
            </div>
        </div>
    </div>
</template>
