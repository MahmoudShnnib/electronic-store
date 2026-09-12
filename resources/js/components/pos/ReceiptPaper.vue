<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The 80mm slip itself, and nothing around it.
 *
 * Extracted from the receipt page because it is now rendered in two places: on
 * that page for a reprint, and off-screen on the register so a sale can print
 * without the cashier ever leaving the till. One copy of the markup means the
 * paper cannot drift between the two.
 *
 * `el` is exposed so a caller can hand the node to html2canvas.
 */
const { t } = useI18n();

defineProps<{
    order: ReceiptOrder;
    store: ReceiptStore;
}>();

const el = ref<HTMLElement | null>(null);

defineExpose({ el });
</script>

<script lang="ts">
export interface ReceiptOrder {
    id: number;
    number: string;
    created_at: string;
    payment_method: string;
    cashier: string | null;
    customer_name: string | null;
    items: { name: string; quantity: number; unit_price_formatted: string; line_total_formatted: string }[];
    subtotal_formatted: string;
    discount_cents: number;
    discount_formatted: string;
    coupon_code: string | null;
    total_formatted: string;
    paid_formatted: string;
}

export interface ReceiptStore {
    name: string;
    details: string | null;
    /** A converted, inlined black-on-white PNG, or null to print the name. */
    logo: string | null;
}
</script>

<template>
    <div ref="el" class="receipt mx-auto w-[80mm] bg-white px-3 pt-1 pb-3 text-[13px] leading-snug text-black">
        <!-- dir="auto" throughout: the store address is routinely Arabic,
             and without it a right-to-left line inherits the page's LTR
             direction and hangs its punctuation on the wrong end. Each line
             decides for itself from its own first strong character. -->
        <div class="text-center">
            <!-- The logo already carries the shop's name, so it replaces the
                 text rather than sitting above it. `alt` is the name, so a slip
                 whose logo somehow fails to decode still says who issued it. -->
            <img v-if="store.logo" :src="store.logo" :alt="store.name" class="mx-auto max-h-[120px] w-auto" />
            <p v-else dir="auto" class="text-[17px] font-bold uppercase leading-tight">{{ store.name }}</p>
            <p v-if="store.details" dir="auto" class="mt-1 whitespace-pre-line text-[14px]">{{ store.details }}</p>
        </div>

        <hr class="my-2 border-dashed border-black" />

        <!-- Label then value, in that source order. The document is `dir=rtl`
             in Arabic, so `justify-between` lands the label on the right and
             the value on the left without a second layout. -->
        <div class="space-y-1 text-[12px]">
            <div class="flex justify-between gap-2">
                <span>{{ t('admin.pos.receipt_no') }}:</span>
                <span class="font-bold">{{ order.number }}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span>{{ t('admin.pos.date') }}:</span>
                <span>{{ order.created_at }}</span>
            </div>
            <div v-if="order.cashier" class="flex justify-between gap-2">
                <span>{{ t('admin.pos.cashier') }}:</span>
                <span dir="auto">{{ order.cashier }}</span>
            </div>
            <div v-if="order.customer_name" class="flex justify-between gap-2">
                <span>{{ t('admin.pos.customer') }}:</span>
                <span dir="auto">{{ order.customer_name }}</span>
            </div>
        </div>

        <hr class="my-2 border-dashed border-black" />

        <!-- Four columns rather than a stacked line, so the eye can compare
             quantities and amounts down a column. `table-fixed` with explicit
             widths is what keeps 80mm from collapsing the numeric columns to
             fit a long product name — the name wraps instead. -->
        <table class="w-full table-fixed border-collapse">
            <thead>
                <tr class="border-y border-black bg-gray-100 text-[11px] font-bold">
                    <th class="w-[44%] py-1 text-start">{{ t('admin.pos.item') }}</th>
                    <th class="w-[22%] py-1 text-center">{{ t('admin.pos.price') }}</th>
                    <th class="w-[12%] py-1 text-center">{{ t('admin.pos.qty') }}</th>
                    <th class="w-[22%] py-1 text-center">{{ t('admin.pos.line_total') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, index) in order.items" :key="index" class="align-top">
                    <td dir="auto" class="break-words py-1.5 text-start">{{ item.name }}</td>
                    <td class="py-1.5 text-center text-[11px]">{{ item.unit_price_formatted }}</td>
                    <td class="py-1.5 text-center text-[11px]">{{ item.quantity }}</td>
                    <td class="py-1.5 text-center text-[11px] font-semibold">{{ item.line_total_formatted }}</td>
                </tr>
            </tbody>
        </table>

        <hr class="my-2 border-dashed border-black" />

        <div class="space-y-1">
            <!-- Only worth the paper when a discount actually moved the
                 figure; otherwise the subtotal just repeats the total. -->
            <template v-if="order.discount_cents > 0">
                <div class="flex justify-between gap-2 text-[12px]">
                    <span>{{ t('admin.pos.subtotal') }}:</span>
                    <span>{{ order.subtotal_formatted }}</span>
                </div>
                <div class="flex justify-between gap-2 text-[12px]">
                    <span
                        >{{ t('admin.pos.discount') }}<template v-if="order.coupon_code"> ({{ order.coupon_code }})</template>:</span
                    >
                    <span>-{{ order.discount_formatted }}</span>
                </div>
            </template>

            <div class="flex items-baseline justify-between gap-2 text-[17px] font-bold">
                <span>{{ t('admin.pos.total') }}:</span>
                <span>{{ order.total_formatted }}</span>
            </div>

            <hr class="border-black" />

            <div class="flex justify-between gap-2 text-[12px]">
                <span>{{ t('admin.pos.payment') }}:</span>
                <span dir="auto">{{ order.payment_method }}</span>
            </div>

            <!-- Normally silent: a counter sale is paid in full, so this only
                 appears when the money taken does not match the total. -->
            <div v-if="order.paid_formatted !== order.total_formatted" class="flex justify-between gap-2 text-[12px]">
                <span>{{ t('admin.pos.paid') }}:</span>
                <span>{{ order.paid_formatted }}</span>
            </div>
        </div>

        <hr class="my-2 border-dashed border-black" />

        <p dir="auto" class="text-center text-[12px]">{{ t('admin.pos.thank_you') }}</p>
    </div>
</template>

<style>
/*
 * Latin from a monospace face so columns of figures line up; Arabic from a real
 * Arabic face rather than whatever the monospace fallback happens to be. The
 * browser takes each glyph from the first family that actually has it, so the
 * order here is the whole mechanism.
 *
 * Arabic also sits optically smaller than Latin at the same pixel size, which is
 * why the slip's type scale as a whole is larger than a Latin-only receipt would
 * need. A taller slip costs a little more paper per sale — that is the trade.
 */
.receipt {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, 'Geeza Pro', 'Noto Naskh Arabic', 'Segoe UI', Tahoma, monospace;
}

/*
 * 80mm roll: the page box has to match the paper or the driver scales the
 * slip down and the barcode-sized type becomes unreadable. Margins are zero
 * because thermal printers feed edge to edge.
 */
@media print {
    @page {
        size: 80mm auto;
        margin: 0;
    }

    /*
     * The register parks the paper off-screen so it can be photographed without
     * being seen. `.receipt` positions itself against its nearest positioned
     * ancestor, so that wrapper has to stand down at print time or the slip
     * would be laid out ten thousand pixels to the left of the page.
     */
    .receipt-offscreen {
        position: static !important;
    }

    body * {
        visibility: hidden;
    }

    .receipt,
    .receipt * {
        visibility: visible;
    }

    .receipt {
        position: absolute;
        inset-inline-start: 0;
        top: 0;
        width: 80mm;
        /* Almost nothing on top: the roll already starts where the head does,
           and a leading band of blank paper on every slip adds up. */
        padding: 0.5mm 2mm 2mm;
    }
}
</style>
