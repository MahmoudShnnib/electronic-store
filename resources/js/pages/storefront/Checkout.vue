<script setup lang="ts">
import CitySelect from '@/components/store/CitySelect.vue';
import type { AddressData, CartData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Alert, Button, Card, Form, FormItem, Input, Radio, RadioGroup, Textarea, Typography } from 'ant-design-vue';
import { ImageOff, Pencil } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

const props = defineProps<{
    cart: CartData;
    addresses: AddressData[];
    customer: { name: string; email: string; phone: string };
    cities: { en: string; ar: string }[];
    paymentMethods: { value: string; label: string }[];
}>();

const { Title, Text } = Typography;

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

/**
 * Errors come back from the server against nested keys, and the fields they
 * belong to are AntD components rather than plain inputs — so the first bad one
 * is found by walking the form in DOM order and focusing whatever control sits
 * inside the `FormItem` AntD has marked as errored.
 */
function focusFirstError() {
    nextTick(() => {
        // AntD's Select is a div with a hidden search input inside it; that input is
        // the focusable element, not `.ant-select-selector`.
        const field = document.querySelector<HTMLElement>('.ant-form-item-has-error input, .ant-form-item-has-error textarea');

        field?.focus();
        field?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    });
}

function submit() {
    // The city picker is a custom control, so native `required` can't reach it.
    if (form.shipping_address.city === '') {
        form.setError('shipping_address.city', t('checkout.city_required'));
        focusFirstError();

        return;
    }

    form.post(route('checkout.store'), { onError: focusFirstError });
}

/**
 * Half-filled checkout details are worth a prompt: the form is long, it is the
 * last step before an order, and a stray back-swipe used to drop all of it.
 */
function warnOnUnsavedChanges(event: BeforeUnloadEvent) {
    if (form.isDirty && !form.processing) {
        event.preventDefault();
    }
}

onMounted(() => window.addEventListener('beforeunload', warnOnUnsavedChanges));
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnOnUnsavedChanges));

/** Flatten nested error keys like `shipping_address.city`. */
function error(key: string): string | undefined {
    return (form.errors as Record<string, string>)[key];
}
</script>

<template>
    <Head :title="t('checkout.title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <Title :level="1" class="!mb-6 !text-2xl !font-bold">{{ t('checkout.title') }}</Title>

        <Alert
            v-if="error('cart') || error('coupon') || cart.coupon_error"
            type="error"
            show-icon
            class="!mb-4"
            :message="error('cart') ?? error('coupon') ?? cart.coupon_error"
        />

        <Form layout="vertical" class="flex flex-col gap-8 lg:flex-row" @submit.prevent="submit">
            <div class="flex-1 space-y-6">
                <!-- Contact + delivery -->
                <Card>
                    <template #title>
                        <span class="store-step">1</span>
                        {{ t('checkout.contact') }}
                    </template>

                    <div v-if="addresses.length > 0" class="mb-5">
                        <Text class="!mb-2 !block !text-sm opacity-60">{{ t('checkout.saved_addresses') }}</Text>
                        <div class="flex flex-wrap gap-2">
                            <!-- A saved address fills the form, so the chip is a real button
                                 rather than a Tag with a click handler on a <span>. -->
                            <button
                                v-for="address in addresses"
                                :key="address.id"
                                type="button"
                                class="store-filter-tag ant-tag"
                                @click="fillFromAddress(address)"
                            >
                                {{ address.label ?? [address.city, address.area].filter(Boolean).join(' — ') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-x-4 sm:grid-cols-2">
                        <FormItem
                            :label="t('checkout.name')"
                            required
                            :validate-status="error('customer.name') ? 'error' : undefined"
                            :help="error('customer.name')"
                        >
                            <Input v-model:value="form.customer.name" name="name" autocomplete="name" :maxlength="120" />
                        </FormItem>

                        <FormItem
                            :label="t('checkout.phone')"
                            required
                            :validate-status="error('customer.phone') ? 'error' : undefined"
                            :help="error('customer.phone') ?? t('checkout.phone_hint')"
                        >
                            <Input
                                v-model:value="form.customer.phone"
                                type="tel"
                                name="tel"
                                autocomplete="tel"
                                spellcheck="false"
                                dir="ltr"
                                inputmode="tel"
                                placeholder="0912345678"
                            />
                        </FormItem>

                        <FormItem
                            class="sm:col-span-2"
                            :validate-status="error('customer.email') ? 'error' : undefined"
                            :help="error('customer.email')"
                        >
                            <template #label>
                                {{ t('checkout.email') }}
                                <Text class="!ms-1 !text-xs opacity-55">({{ t('common.optional') }})</Text>
                            </template>
                            <Input v-model:value="form.customer.email" type="email" name="email" autocomplete="email" spellcheck="false" dir="ltr" />
                        </FormItem>

                        <FormItem
                            :label="t('checkout.city')"
                            required
                            :validate-status="error('shipping_address.city') ? 'error' : undefined"
                            :help="error('shipping_address.city')"
                        >
                            <CitySelect
                                v-model="form.shipping_address.city"
                                :cities="cities"
                                :has-error="error('shipping_address.city') !== undefined"
                            />
                        </FormItem>

                        <FormItem
                            :label="t('checkout.area')"
                            required
                            :validate-status="error('shipping_address.area') ? 'error' : undefined"
                            :help="error('shipping_address.area')"
                        >
                            <Input v-model:value="form.shipping_address.area" name="address-level1" autocomplete="address-level1" :maxlength="120" />
                        </FormItem>

                        <FormItem
                            class="sm:col-span-2"
                            :label="t('checkout.order_note')"
                            :validate-status="error('customer_note') ? 'error' : undefined"
                            :help="error('customer_note')"
                        >
                            <Textarea v-model:value="form.customer_note" :rows="2" />
                        </FormItem>
                    </div>
                </Card>

                <!-- Payment -->
                <Card>
                    <template #title>
                        <span class="store-step">2</span>
                        {{ t('checkout.payment') }}
                    </template>

                    <FormItem :validate-status="error('payment_method') ? 'error' : undefined" :help="error('payment_method')" class="!mb-0">
                        <RadioGroup v-model:value="form.payment_method" class="store-payment">
                            <Radio v-for="method in paymentMethods" :key="method.value" :value="method.value" class="store-payment-option">
                                {{ method.label }}
                            </Radio>
                        </RadioGroup>
                    </FormItem>
                </Card>
            </div>

            <!-- Review / summary -->
            <aside class="w-full lg:w-96">
                <Card class="sticky top-24">
                    <template #title>
                        <span class="store-step">3</span>
                        {{ t('checkout.review') }}
                    </template>
                    <template #extra>
                        <Link :href="route('cart.show')">
                            <Button size="small">
                                <template #icon><Pencil class="h-3.5 w-3.5" /></template>
                                {{ t('checkout.edit_order') }}
                            </Button>
                        </Link>
                    </template>

                    <ul class="overlay-scroll mb-4 max-h-80 space-y-3 overflow-y-auto text-sm">
                        <li v-for="item in cart.items" :key="item.id" class="flex items-center gap-3">
                            <Link :href="route('products.show', item.slug)" class="store-cart-thumb !h-16 !w-16">
                                <img
                                    v-if="item.image"
                                    :src="item.image"
                                    :alt="item.name"
                                    width="64"
                                    height="64"
                                    loading="lazy"
                                    class="h-full w-full object-contain"
                                />
                                <ImageOff v-else class="h-6 w-6 opacity-30" />
                            </Link>
                            <div class="min-w-0 flex-1">
                                <p class="store-product-name !min-h-0">{{ item.name }}</p>
                                <Text class="!mt-0.5 !block !text-xs opacity-55" dir="auto"
                                    >{{ item.quantity }} × {{ item.unit_price_formatted }}</Text
                                >
                            </div>
                            <span class="store-money shrink-0 font-semibold">{{ item.line_subtotal_formatted }}</span>
                        </li>
                    </ul>

                    <dl class="store-totals border-t border-black/10 pt-3 dark:border-white/10">
                        <div v-if="cart.discount_cents > 0" class="store-totals-row store-totals-discount">
                            <dt>
                                {{ t('cart.discount') }} <template v-if="cart.coupon">({{ cart.coupon.code }})</template>
                            </dt>
                            <dd>
                                <bdi dir="ltr">−{{ cart.discount_formatted }}</bdi>
                            </dd>
                        </div>
                        <div class="store-totals-row store-totals-grand">
                            <dt>{{ t('cart.total') }}</dt>
                            <dd>{{ cart.total_formatted }}</dd>
                        </div>
                    </dl>

                    <Button type="primary" size="large" shape="round" block html-type="submit" :loading="form.processing" class="!mt-5">
                        {{ t('checkout.place_order') }}
                    </Button>
                </Card>
            </aside>
        </Form>
    </div>
</template>
