<script setup lang="ts">
import ReceiptPaper, { type ReceiptOrder, type ReceiptStore } from '@/components/pos/ReceiptPaper.vue';
import { usePosPrinting } from '@/composables/usePosPrinting';
import { Head, Link } from '@inertiajs/vue3';
import { Button, message } from 'ant-design-vue';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The reprint page.
 *
 * A sale rung up at the register no longer comes here — it prints from the till
 * and the cashier never leaves the screen. This is what "recent sales" links to
 * when a slip needs printing again, and it is also the browser-dialog fallback
 * for a shop with no counter printer wired up.
 */
const { t } = useI18n();

const props = defineProps<{
    order: ReceiptOrder;
    store: ReceiptStore;
    printer: { direct: boolean; width: number };
    /** True only for `?print=1` — see ReceiptController. */
    autoPrint: boolean;
}>();

const paper = ref<InstanceType<typeof ReceiptPaper> | null>(null);
const { sending, printSlip } = usePosPrinting(() => props.printer.width);

function printViaBrowser(): void {
    window.print();
}

async function printDirect(): Promise<void> {
    const printed = await printSlip(paper.value?.el ?? null, props.order.id);

    if (printed) {
        message.success(t('admin.pos.printed'));

        return;
    }

    // The sale already happened; the customer is waiting for paper, so a printer
    // that will not answer falls back rather than failing.
    message.warning(t('admin.pos.print_failed'));
    printViaBrowser();
}

/*
 * Deliberately silent on arrival.
 *
 * The register prints its own sales now, so the only reasons to open this page
 * are to look at a slip or to ask for another copy — and firing the printer at
 * someone who wanted to look is how a customer ends up with two receipts. Paper
 * comes from the buttons, or from `?print=1`.
 */
onMounted(() => {
    if (!props.autoPrint) return;

    return props.printer.direct ? printDirect() : printViaBrowser();
});
</script>

<template>
    <Head :title="t('admin.pos.receipt_for', { number: order.number })" />

    <div class="min-h-screen bg-gray-100 py-6 print:bg-white print:py-0">
        <div class="mx-auto mb-4 flex max-w-[80mm] flex-wrap gap-2 print:hidden">
            <Link :href="route('admin.pos.register')" class="flex-1">
                <Button type="primary" block>{{ t('admin.pos.next_sale') }}</Button>
            </Link>
            <Button v-if="printer.direct" :loading="sending" @click="printDirect">{{ t('admin.pos.print') }}</Button>
            <Button v-else @click="printViaBrowser">{{ t('admin.pos.print') }}</Button>
            <Button v-if="printer.direct" type="link" size="small" class="basis-full" @click="printViaBrowser">
                {{ t('admin.pos.print_browser') }}
            </Button>
        </div>

        <ReceiptPaper ref="paper" :order="order" :store="store" />
    </div>
</template>
