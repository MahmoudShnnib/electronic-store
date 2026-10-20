<script setup lang="ts">
import DeliverySlipPaper from '@/components/admin/DeliverySlipPaper.vue';
import { usePosPrinting } from '@/composables/usePosPrinting';
import { Head } from '@inertiajs/vue3';
import { Button, message } from 'ant-design-vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The paper that rides with the parcel, cut to the counter printer's 80mm roll.
 *
 * It prints through the same head as a POS receipt — one printer at the desk,
 * two kinds of slip — but it is laid out as cards rather than a receipt's
 * dashed rules: a driver reads it at arm's length off a taped-on label, so the
 * recipient block has to be found without being read.
 *
 * Everything that must survive the print is black or a hard border: the raster
 * burns at luminance 160, so the grey card fills threshold away to white and
 * only the outlines and the black bars are left on paper.
 */
const { t } = useI18n();

const props = defineProps<{
    order: {
        id: number;
        number: string;
        created_at: string;
        recipient: { name?: string | null; phone?: string | null; address: string[]; notes?: string | null };
        items: { name: string; sku: string; quantity: number }[];
        payment_method: string;
        total_formatted: string;
        collect_formatted: string | null;
        customer_note: string | null;
    };
    store: { name: string; details: string | null; logo: string | null };
    printer: { direct: boolean; width: number };
}>();

const paper = ref<InstanceType<typeof DeliverySlipPaper> | null>(null);
const { sending, printSlip } = usePosPrinting(() => props.printer.width);

function printViaBrowser(): void {
    window.print();
}

async function printDirect(): Promise<void> {
    const printed = await printSlip((paper.value?.$el as HTMLElement | undefined) ?? null, props.order.id, 'admin.orders.slip.print');

    if (printed) {
        message.success(t('admin.pos.printed'));

        return;
    }

    // A parcel cannot wait on a printer that will not answer: fall back to the
    // browser dialog rather than leaving the packer with nothing.
    message.warning(t('admin.pos.print_failed'));
    printViaBrowser();
}
</script>

<template>
    <Head :title="t('admin.orders.delivery_slip_number', { number: order.number })" />

    <div class="min-h-screen bg-gray-100 py-6 print:bg-white print:py-0">
        <div class="mx-auto mb-4 flex max-w-[80mm] flex-wrap gap-2 print:hidden">
            <Button v-if="printer.direct" type="primary" block :loading="sending" @click="printDirect">
                {{ t('admin.orders.print_delivery_slip') }}
            </Button>
            <Button v-else type="primary" block @click="printViaBrowser">{{ t('admin.orders.print_delivery_slip') }}</Button>
            <Button v-if="printer.direct" type="link" size="small" class="basis-full" @click="printViaBrowser">
                {{ t('admin.pos.print_browser') }}
            </Button>
        </div>

        <DeliverySlipPaper ref="paper" :order="order" :store="store" />
    </div>
</template>
