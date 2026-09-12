<script setup lang="ts">
import { usePosPrinting } from '@/composables/usePosPrinting';
import { Head } from '@inertiajs/vue3';
import { Button, message } from 'ant-design-vue';
import { Calendar, MapPin, Phone, Receipt, User, Wallet } from 'lucide-vue-next';
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

const paper = ref<HTMLElement | null>(null);
const { sending, printSlip } = usePosPrinting(() => props.printer.width);

function printViaBrowser(): void {
    window.print();
}

async function printDirect(): Promise<void> {
    const printed = await printSlip(paper.value, props.order.id, 'admin.orders.slip.print');

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

        <!-- dir="auto" throughout: an Arabic recipient name inside an English
             shell (or the reverse) has to decide its own direction, or its
             punctuation hangs off the wrong end. -->
        <div ref="paper" class="receipt mx-auto w-[80mm] space-y-2 bg-white p-2 text-[13px] leading-snug text-black">
            <div class="flex items-center gap-2">
                <!-- The logo carries the shop's name, so it replaces the text
                     rather than sitting above it. With no logo the name takes
                     the same corner. -->
                <div class="w-[34%] shrink-0 text-center">
                    <img v-if="store.logo" :src="store.logo" :alt="store.name" class="mx-auto max-h-[80px] w-auto" />
                    <template v-else>
                        <p dir="auto" class="text-[15px] font-bold uppercase leading-tight">{{ store.name }}</p>
                        <p v-if="store.details" dir="auto" class="mt-1 whitespace-pre-line text-[10px]">{{ store.details }}</p>
                    </template>
                </div>

                <section class="flex-1 rounded-xl border border-gray-500 p-2">
                    <h2 class="mb-1.5 text-center text-[14px] font-bold">{{ t('admin.orders.delivery_details') }}</h2>

                    <!-- Label, value, icon — the icon lands on the far side of
                         the line in either direction, and the value keeps the
                         middle where the eye lands first. -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px]">{{ t('admin.common.name') }}:</span>
                            <span dir="auto" class="flex-1 text-[14px] font-bold">{{ order.recipient.name }}</span>
                            <User class="h-4 w-4 shrink-0" :stroke-width="2.5" />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px]">{{ t('admin.common.phone') }}:</span>
                            <span class="flex-1 text-[15px] font-bold">
                                <bdi dir="ltr">{{ order.recipient.phone }}</bdi>
                            </span>
                            <Phone class="h-4 w-4 shrink-0" :stroke-width="2.5" />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px]">{{ t('admin.common.city') }}:</span>
                            <span dir="auto" class="flex-1 text-[13px] font-semibold">{{ order.recipient.address.join(' — ') }}</span>
                            <MapPin class="h-4 w-4 shrink-0" :stroke-width="2.5" />
                        </div>
                        <p v-if="order.recipient.notes" dir="auto" class="text-[11px]">{{ order.recipient.notes }}</p>
                    </div>
                </section>
            </div>

            <section class="space-y-1 rounded-xl border border-gray-500 p-2">
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px]">{{ t('admin.pos.receipt_no') }}:</span>
                    <span class="flex-1 text-[13px] font-bold">{{ order.number }}</span>
                    <Receipt class="h-4 w-4 shrink-0" :stroke-width="2.5" />
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px]">{{ t('admin.pos.date') }}:</span>
                    <span class="flex-1 text-[13px] font-semibold">{{ order.created_at }}</span>
                    <Calendar class="h-4 w-4 shrink-0" :stroke-width="2.5" />
                </div>
            </section>

            <!-- Quantities but no prices: the slip is handled by a driver, and
                 sometimes read by the customer. table-fixed keeps the qty
                 column from collapsing under a long product name. -->
            <table class="w-full table-fixed border-collapse overflow-hidden rounded-xl">
                <thead>
                    <tr class="bg-black text-[12px] font-bold text-white">
                        <th class="w-[78%] px-2 py-1.5 text-start">{{ t('admin.pos.item') }}</th>
                        <th class="w-[22%] px-2 py-1.5 text-center">{{ t('admin.pos.qty') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in order.items" :key="index" class="border-b border-gray-500 align-middle last:border-0">
                        <td dir="auto" class="break-words px-2 py-1.5 text-start">
                            <span class="font-bold">{{ item.name }}</span>
                            <span class="block text-[10px]">{{ item.sku }}</span>
                        </td>
                        <td class="px-2 py-1.5 text-center text-[14px] font-bold">{{ item.quantity }}</td>
                    </tr>
                </tbody>
            </table>

            <section class="flex items-center gap-2 rounded-xl border border-gray-500 p-2">
                <div class="flex-1">
                    <p class="text-[12px]">{{ order.payment_method }}</p>
                    <p v-if="order.collect_formatted" class="text-[12px]">{{ t('admin.orders.collect_on_delivery') }}:</p>
                </div>
                <Wallet class="h-5 w-5 shrink-0" :stroke-width="2.5" />
                <!-- The one figure the driver is accountable for, or the order
                     total when the money is already in. -->
                <p class="w-[38%] shrink-0 text-center text-[20px] font-bold leading-tight">
                    {{ order.collect_formatted ?? order.total_formatted }}
                </p>
            </section>

            <section v-if="order.customer_note" class="rounded-xl border border-gray-500 p-2">
                <p class="text-[11px] font-bold">{{ t('admin.orders.customer_note') }}</p>
                <p dir="auto" class="whitespace-pre-line text-[12px]">{{ order.customer_note }}</p>
            </section>
        </div>
    </div>
</template>
