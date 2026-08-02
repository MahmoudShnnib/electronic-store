<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface AddressSnapshot {
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
        payment_status: string;
        payment_method: string;
        customer: { name?: string; email?: string; phone?: string };
        shipping_address: AddressSnapshot;
        billing_address: AddressSnapshot;
        subtotal_formatted: string;
        discount_formatted: string;
        tax_formatted: string;
        total_formatted: string;
        coupon_code: string | null;
        customer_note: string | null;
        created_at: string;
        cancellable: boolean;
        items: {
            id: number;
            name: string;
            sku: string;
            quantity: number;
            unit_price_formatted: string;
            line_total_formatted: string;
        }[];
        timeline: { status: string; comment: string | null; at: string }[];
        refunds: { amount_formatted: string; method: string; at: string }[];
    };
}>();

const { t } = useI18n();

function cancelOrder() {
    if (confirm(t('account.cancel_confirm'))) {
        router.post(route('account.orders.cancel', props.order.id), {}, { preserveScroll: true });
    }
}

function formatAddress(address: AddressSnapshot): string {
    return [address.name, address.phone, address.area, address.city, address.country].filter(Boolean).join(' · ');
}
</script>

<template>
    <Head :title="`${t('account.order')} ${order.number}`" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link :href="route('account.orders.index')" class="rounded-lg border border-border p-2 hover:bg-muted" :aria-label="t('common.back')">
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                </Link>
                <div>
                    <h1 class="text-xl font-bold">{{ order.number }}</h1>
                    <p class="text-sm text-muted-foreground">{{ order.created_at }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    v-if="['delivered', 'completed'].includes(order.status)"
                    :href="route('account.returns.create', order.id)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-sm font-medium transition hover:bg-muted"
                >
                    <RotateCcw class="h-4 w-4" />
                    {{ t('account.request_return') }}
                </Link>
                <button
                    v-if="order.cancellable"
                    type="button"
                    class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950"
                    @click="cancelOrder"
                >
                    {{ t('account.cancel_order') }}
                </button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Items -->
                <section class="overflow-hidden rounded-xl border border-border bg-card">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="item in order.items" :key="item.id" class="border-b border-border last:border-0">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ item.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ item.sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ item.unit_price_formatted }} × {{ item.quantity }}</td>
                                <td class="px-4 py-3 text-end font-semibold">{{ item.line_total_formatted }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- Timeline -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 font-semibold">{{ t('account.timeline') }}</h2>
                    <ol class="relative space-y-4 border-s border-border ps-5">
                        <li v-for="(entry, index) in order.timeline" :key="index" class="relative">
                            <span
                                class="absolute -start-[26px] top-1 h-3 w-3 rounded-full"
                                :class="index === 0 ? 'bg-black dark:bg-white dark:text-black' : 'bg-border'"
                            />
                            <p class="text-sm font-medium">{{ entry.status }}</p>
                            <p v-if="entry.comment" class="text-xs text-muted-foreground">{{ entry.comment }}</p>
                            <p class="text-xs text-muted-foreground">{{ entry.at }}</p>
                        </li>
                    </ol>
                </section>
            </div>

            <aside class="space-y-6">
                <!-- Totals -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('cart.subtotal') }}</dt>
                            <dd>{{ order.subtotal_formatted }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">
                                {{ t('cart.discount') }} <template v-if="order.coupon_code">({{ order.coupon_code }})</template>
                            </dt>
                            <dd>{{ order.discount_formatted }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">{{ t('cart.tax') }}</dt>
                            <dd>{{ order.tax_formatted }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-border pt-3 font-bold">
                            <dt>{{ t('cart.total') }}</dt>
                            <dd>{{ order.total_formatted }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-sm text-muted-foreground">{{ t('account.payment') }}: {{ order.payment_method }}</p>

                    <div v-if="order.refunds.length > 0" class="mt-3 border-t border-border pt-3">
                        <p v-for="(refund, index) in order.refunds" :key="index" class="text-sm text-muted-foreground">
                            ↩ {{ refund.amount_formatted }} — {{ refund.method }} ({{ refund.at }})
                        </p>
                    </div>
                </section>

                <!-- Addresses -->
                <section class="rounded-xl border border-border bg-card p-5 text-sm">
                    <h2 class="mb-2 font-semibold">{{ t('checkout.shipping_address') }}</h2>
                    <p class="text-muted-foreground">{{ formatAddress(order.shipping_address) }}</p>

                </section>
            </aside>
        </div>
    </div>
</template>
