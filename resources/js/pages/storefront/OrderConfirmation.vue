<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

defineProps<{
    order: {
        number: string;
        total_formatted: string;
        payment_method: string;
        status: string;
        items: { name: string; quantity: number; line_total_formatted: string }[];
    };
    paymentInstructions: string | null;
}>();

const { t } = useI18n();
const page = usePage();
const authenticated = computed(() => (page.props.auth as { user: unknown | null }).user !== null);
</script>

<template>
    <Head :title="t('checkout.confirmation_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mx-auto max-w-xl py-8 text-center">
            <CheckCircle2 class="mx-auto h-16 w-16 text-green-500" />
            <h1 class="mt-4 text-2xl font-bold">{{ t('checkout.confirmation_title') }}</h1>
            <p class="mt-2 text-muted-foreground">{{ t('checkout.confirmation_subtitle', { number: order.number }) }}</p>

            <div class="mt-8 rounded-xl border border-border bg-card p-5 text-start">
                <ul class="space-y-2 text-sm">
                    <li v-for="(item, index) in order.items" :key="index" class="flex justify-between gap-2">
                        <span>{{ item.name }} × {{ item.quantity }}</span>
                        <span class="font-medium">{{ item.line_total_formatted }}</span>
                    </li>
                </ul>
                <div class="mt-4 flex justify-between border-t border-border pt-3 font-bold">
                    <span>{{ t('cart.total') }}</span>
                    <span>{{ order.total_formatted }}</span>
                </div>
                <p class="mt-2 text-sm text-muted-foreground">{{ t('checkout.payment') }}: {{ order.payment_method }}</p>
            </div>

            <div
                v-if="paymentInstructions"
                class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-start text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                <h2 class="mb-1 font-semibold">{{ t('checkout.payment_instructions') }}</h2>
                <p>{{ paymentInstructions }}</p>
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Link
                    v-if="authenticated"
                    :href="route('account.orders.index')"
                    class="rounded-xl bg-black px-5 py-2.5 font-semibold text-white transition hover:opacity-85 dark:bg-white dark:text-black"
                >
                    {{ t('checkout.view_orders') }}
                </Link>
                <Link :href="route('catalog.index')" class="rounded-xl border border-border px-5 py-2.5 font-semibold transition hover:bg-muted">
                    {{ t('cart.continue_shopping') }}
                </Link>
            </div>
        </div>
    </div>
</template>
