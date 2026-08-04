<script setup lang="ts">
import CitySelect from '@/components/store/CitySelect.vue';
import type { AddressData, CartData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImageOff } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

const props = defineProps<{
    cart: CartData;
    addresses: AddressData[];
    customer: { name: string; email: string; phone: string };
    cities: { en: string; ar: string }[];
    paymentMethods: { value: string; label: string }[];
}>();

const { t } = useI18n();

/**
 * crypto.randomUUID() exists only on secure origins (https / localhost); on
 * plain http (like the .test domain) it is undefined and would take the whole
 * page down. getRandomValues works everywhere, so build the UUID from that.
 */
function makeIdempotencyKey(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

const idempotencyKey = makeIdempotencyKey();

const form = useForm({
    customer: {
        name: props.customer.name,
        email: props.customer.email,
        phone: props.customer.phone,
    },
    shipping_address: {
        country: 'LY',
        city: '',
        area: '',
    },
    payment_method: props.paymentMethods[0]?.value ?? 'cod',
    customer_note: '',
    idempotency_key: idempotencyKey,
});

function fillFromAddress(address: AddressData) {
    form.shipping_address.city = address.city;
    form.shipping_address.area = address.area ?? '';
}

function submit() {
    form.post(route('checkout.store'));
}

/** Flatten nested error keys like `shipping_address.city`. */
function error(key: string): string | undefined {
    return (form.errors as Record<string, string>)[key];
}
</script>

<template>
    <Head :title="t('checkout.title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <h1 class="mb-6 text-2xl font-bold">{{ t('checkout.title') }}</h1>

        <p v-if="error('cart') || error('coupon')" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
            {{ error('cart') ?? error('coupon') }}
        </p>

        <form class="flex flex-col gap-8 lg:flex-row" @submit.prevent="submit">
            <div class="flex-1 space-y-6">
                <!-- Contact + delivery -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 font-semibold">1. {{ t('checkout.contact') }}</h2>

                    <div v-if="addresses.length > 0" class="mb-4">
                        <p class="mb-2 text-sm text-muted-foreground">{{ t('checkout.saved_addresses') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="address in addresses"
                                :key="address.id"
                                type="button"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm transition hover:border-black dark:hover:border-white"
                                @click="fillFromAddress(address)"
                            >
                                {{ address.label ?? [address.city, address.area].filter(Boolean).join(' — ') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm">{{ t('checkout.name') }}</label>
                            <input
                                v-model="form.customer.name"
                                type="text"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            />
                            <p v-if="error('customer.name')" class="mt-1 text-xs text-red-500">{{ error('customer.name') }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm">{{ t('checkout.phone') }}</label>
                            <input
                                v-model="form.customer.phone"
                                type="tel"
                                dir="ltr"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            />
                            <p v-if="error('customer.phone')" class="mt-1 text-xs text-red-500">{{ error('customer.phone') }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm">
                                {{ t('checkout.email') }}
                                <span class="text-muted-foreground">({{ t('common.optional') }})</span>
                            </label>
                            <input
                                v-model="form.customer.email"
                                type="email"
                                dir="ltr"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            />
                            <p v-if="error('customer.email')" class="mt-1 text-xs text-red-500">{{ error('customer.email') }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm">{{ t('checkout.city') }}</label>
                            <CitySelect
                                v-model="form.shipping_address.city"
                                :cities="cities"
                                :has-error="error('shipping_address.city') !== undefined"
                            />
                            <p v-if="error('shipping_address.city')" class="mt-1 text-xs text-red-500">{{ error('shipping_address.city') }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm">{{ t('checkout.area') }}</label>
                            <input
                                v-model="form.shipping_address.area"
                                type="text"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            />
                            <p v-if="error('shipping_address.area')" class="mt-1 text-xs text-red-500">{{ error('shipping_address.area') }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm">{{ t('checkout.order_note') }}</label>
                            <textarea
                                v-model="form.customer_note"
                                rows="2"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            />
                            <p v-if="error('customer_note')" class="mt-1 text-xs text-red-500">{{ error('customer_note') }}</p>
                        </div>
                    </div>
                </section>

                <!-- Payment -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-4 font-semibold">2. {{ t('checkout.payment') }}</h2>
                    <div class="space-y-2">
                        <label
                            v-for="method in paymentMethods"
                            :key="method.value"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                            :class="
                                form.payment_method === method.value
                                    ? 'border-black bg-neutral-100 dark:border-white dark:bg-neutral-800'
                                    : 'border-border'
                            "
                        >
                            <input v-model="form.payment_method" type="radio" :value="method.value" class="h-4 w-4 text-black dark:text-white" />
                            <span class="text-sm font-medium">{{ method.label }}</span>
                        </label>
                    </div>
                    <p v-if="error('payment_method')" class="mt-1 text-xs text-red-500">{{ error('payment_method') }}</p>
                </section>
            </div>

            <!-- Review / summary -->
            <aside class="h-fit w-full rounded-xl border border-border bg-card p-5 lg:w-96">
                <h2 class="mb-4 font-semibold">3. {{ t('checkout.review') }}</h2>

                <ul class="mb-4 max-h-80 space-y-3 overflow-y-auto text-sm">
                    <li v-for="item in cart.items" :key="item.id" class="flex items-center gap-3">
                        <Link
                            :href="route('products.show', item.slug)"
                            class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted"
                        >
                            <img v-if="item.image" :src="item.image" :alt="item.name" class="h-full w-full object-contain" />
                            <ImageOff v-else class="h-6 w-6 text-muted-foreground" />
                        </Link>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 font-medium leading-snug">{{ item.name }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground" dir="auto">{{ item.quantity }} × {{ item.unit_price_formatted }}</p>
                        </div>
                        <span class="shrink-0 font-semibold">{{ item.line_subtotal_formatted }}</span>
                    </li>
                </ul>

                <dl class="space-y-2 border-t border-border pt-3 text-sm">
                    <div v-if="cart.discount_cents > 0" class="flex justify-between text-green-600">
                        <dt>
                            {{ t('cart.discount') }} <template v-if="cart.coupon">({{ cart.coupon.code }})</template>
                        </dt>
                        <dd>-{{ cart.discount_formatted }}</dd>
                    </div>
                    <div v-if="cart.tax_cents > 0" class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('cart.tax') }}</dt>
                        <dd>{{ cart.tax_formatted }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-border pt-3 text-base font-bold">
                        <dt>{{ t('cart.total') }}</dt>
                        <dd>{{ cart.total_formatted }}</dd>
                    </div>
                </dl>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-5 w-full rounded-xl bg-black py-3 font-semibold text-white transition hover:opacity-85 disabled:opacity-50 dark:bg-white dark:text-black"
                >
                    {{ form.processing ? t('common.loading') : t('checkout.place_order') }}
                </button>
            </aside>
        </form>
    </div>
</template>
