<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import QuantityInput from '@/components/store/QuantityInput.vue';
import type { CartData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ImageOff, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

defineProps<{ cart: CartData }>();

const { t } = useI18n();

function updateQuantity(itemId: number, quantity: number) {
    router.patch(route('cart.update', itemId), { quantity }, { preserveScroll: true });
}

function removeItem(itemId: number) {
    router.delete(route('cart.remove', itemId), { preserveScroll: true });
}

const couponForm = useForm({ code: '' });

function applyCoupon() {
    couponForm.post(route('cart.coupon.apply'), {
        preserveScroll: true,
        onSuccess: () => couponForm.reset(),
    });
}

function removeCoupon() {
    router.delete(route('cart.coupon.remove'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('cart.title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <h1 class="mb-6 text-2xl font-bold">{{ t('cart.title') }}</h1>

        <EmptyState
            v-if="cart.items.length === 0"
            :message="t('cart.empty')"
            :action-label="t('cart.continue_shopping')"
            :action-href="route('catalog.index')"
        />

        <div v-else class="flex flex-col gap-8 lg:flex-row">
            <!-- Items -->
            <div class="flex-1 space-y-3">
                <div v-for="item in cart.items" :key="item.id" class="flex gap-4 rounded-xl border border-border bg-card p-3 sm:p-4">
                    <Link
                        :href="route('products.show', item.slug)"
                        class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted sm:h-24 sm:w-24"
                    >
                        <img v-if="item.image" :src="item.image" :alt="item.name" class="h-full w-full object-contain" />
                        <ImageOff v-else class="h-8 w-8 text-muted-foreground" />
                    </Link>

                    <div class="flex min-w-0 flex-1 flex-col">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <Link :href="route('products.show', item.slug)" class="line-clamp-2 text-sm font-medium hover:opacity-70">
                                    {{ item.name }}
                                </Link>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ item.sku }}</p>
                                <p v-if="!item.purchasable" class="mt-1 text-xs font-medium text-red-600">{{ t('cart.unavailable') }}</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg p-1.5 text-muted-foreground transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                                :aria-label="t('cart.remove')"
                                @click="removeItem(item.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
                            <QuantityInput
                                :model-value="item.quantity"
                                :min="0"
                                :max="10"
                                @update:model-value="(quantity) => updateQuantity(item.id, quantity)"
                            />
                            <div class="text-end">
                                <p class="text-xs text-muted-foreground">{{ item.unit_price_formatted }} × {{ item.quantity }}</p>
                                <p class="font-semibold">{{ item.line_subtotal_formatted }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <aside class="h-fit w-full rounded-xl border border-border bg-card p-5 lg:w-80">
                <!-- Coupon -->
                <div class="mb-4">
                    <div v-if="cart.coupon" class="flex items-center justify-between rounded-lg bg-green-50 px-3 py-2 text-sm dark:bg-green-950">
                        <span class="font-medium text-green-700 dark:text-green-300">{{ cart.coupon.code }}</span>
                        <button type="button" class="text-xs text-red-500 hover:underline" @click="removeCoupon">
                            {{ t('cart.remove_coupon') }}
                        </button>
                    </div>
                    <form v-else class="flex gap-2" @submit.prevent="applyCoupon">
                        <input
                            v-model="couponForm.code"
                            type="text"
                            :placeholder="t('cart.coupon_placeholder')"
                            class="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-2 text-sm uppercase"
                        />
                        <button
                            type="submit"
                            :disabled="couponForm.processing || couponForm.code.trim() === ''"
                            class="rounded-lg border border-black px-3 py-2 text-sm font-semibold text-black transition hover:bg-neutral-100 disabled:opacity-50 dark:border-white dark:text-white dark:hover:bg-neutral-800"
                        >
                            {{ t('cart.apply_coupon') }}
                        </button>
                    </form>
                    <p v-if="couponForm.errors.coupon" class="mt-1 text-xs text-red-500">{{ couponForm.errors.coupon }}</p>
                    <p v-if="cart.coupon_error" class="mt-1 text-xs text-amber-600">{{ cart.coupon_error }}</p>
                </div>

                <dl class="space-y-2 text-sm">
                    <div v-if="cart.discount_cents > 0" class="flex justify-between text-green-600">
                        <dt>{{ t('cart.discount') }}</dt>
                        <dd>
                            <bdi dir="ltr">−{{ cart.discount_formatted }}</bdi>
                        </dd>
                    </div>
                    <div v-if="cart.tax_cents > 0" class="flex justify-between">
                        <dt class="text-muted-foreground">{{ t('cart.tax') }}</dt>
                        <dd class="font-medium">{{ cart.tax_formatted }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-border pt-3 text-base font-bold">
                        <dt>{{ t('cart.total') }}</dt>
                        <dd>{{ cart.total_formatted }}</dd>
                    </div>
                </dl>

                <Link
                    :href="route('checkout.show')"
                    class="mt-5 block w-full rounded-xl bg-black py-3 text-center font-semibold text-white transition hover:opacity-85 dark:bg-white dark:text-black"
                >
                    {{ t('cart.checkout') }}
                </Link>
                <Link :href="route('catalog.index')" class="mt-2 block text-center text-sm text-black hover:underline dark:text-white">
                    {{ t('cart.continue_shopping') }}
                </Link>
            </aside>
        </div>
    </div>
</template>
