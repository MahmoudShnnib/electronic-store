<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import QuantityInput from '@/components/store/QuantityInput.vue';
import type { CartData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Alert, Button, Card, Input, Modal, Space, Tag, Tooltip, Typography } from 'ant-design-vue';
import { ImageOff, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

defineProps<{ cart: CartData }>();

const { Title, Text } = Typography;

const { t } = useI18n();

function updateQuantity(itemId: number, quantity: number) {
    router.patch(route('cart.update', itemId), { quantity }, { preserveScroll: true });
}

/**
 * Removing a line and dropping a coupon are both destructive and both used to
 * happen on a single click. They ask first now.
 *
 * A declarative `<Modal>` rather than `Popconfirm` or `Modal.confirm()`. Both
 * were tried here first: Popconfirm's popup never mounted — its trigger fires
 * `open-change` and no popover appears, controlled or not — and the imperative
 * `Modal.confirm()` renders in its own app root, outside the shop's
 * `ConfigProvider`, so it came out unthemed and at the wrong scale. The
 * dashboard's `ConfirmDialog` is this same declarative shape.
 */
const confirming = ref<'item' | 'coupon' | null>(null);
const pendingItem = ref<number | null>(null);

function askRemoveItem(itemId: number) {
    pendingItem.value = itemId;
    confirming.value = 'item';
}

function confirmRemoval() {
    if (confirming.value === 'coupon') {
        removeCoupon();
    } else if (pendingItem.value !== null) {
        removeItem(pendingItem.value);
    }

    confirming.value = null;
    pendingItem.value = null;
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
        <Title :level="1" class="!mb-6 !text-2xl !font-bold">{{ t('cart.title') }}</Title>

        <EmptyState
            v-if="cart.items.length === 0"
            :message="t('cart.empty')"
            :action-label="t('cart.continue_shopping')"
            :action-href="route('catalog.index')"
        />

        <div v-else class="flex flex-col gap-8 lg:flex-row">
            <!-- Items -->
            <div class="flex-1 space-y-3">
                <Card v-for="item in cart.items" :key="item.id" size="small" :body-style="{ padding: '0.875rem' }">
                    <div class="flex gap-4">
                        <Link :href="route('products.show', item.slug)" class="store-cart-thumb">
                            <img
                                v-if="item.image"
                                :src="item.image"
                                :alt="item.name"
                                width="96"
                                height="96"
                                loading="lazy"
                                class="h-full w-full object-contain"
                            />
                            <ImageOff v-else class="h-8 w-8 opacity-30" />
                        </Link>

                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <Link :href="route('products.show', item.slug)" class="store-product-name !min-h-0">{{ item.name }}</Link>
                                    <Text class="!mt-0.5 !block !text-xs opacity-55">{{ item.sku }}</Text>
                                    <Tag v-if="!item.purchasable" color="error" class="!mt-1.5">{{ t('cart.unavailable') }}</Tag>
                                </div>

                                <Tooltip :title="t('cart.remove')">
                                    <Button
                                        type="text"
                                        danger
                                        shape="circle"
                                        :aria-label="t('cart.remove')"
                                        @click.stop="askRemoveItem(item.id)"
                                    >
                                        <template #icon><Trash2 class="h-4 w-4" /></template>
                                    </Button>
                                </Tooltip>
                            </div>

                            <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-3">
                                <QuantityInput
                                    :model-value="item.quantity"
                                    :min="1"
                                    :max="10"
                                    @update:model-value="(quantity) => updateQuantity(item.id, quantity)"
                                />
                                <div class="text-end">
                                    <Text class="!block !text-xs opacity-55">{{ item.unit_price_formatted }} × {{ item.quantity }}</Text>
                                    <p class="store-money font-semibold">{{ item.line_subtotal_formatted }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Summary -->
            <aside class="w-full lg:w-80">
                <Card class="sticky top-24" :body-style="{ padding: '1.25rem' }">
                    <!-- Coupon -->
                    <div class="mb-4">
                        <div v-if="cart.coupon" class="flex items-center justify-between gap-2">
                            <Tag color="success" class="!m-0 !px-3 !py-1 !text-sm !font-semibold">{{ cart.coupon.code }}</Tag>
                            <Button type="link" danger size="small" @click.stop="confirming = 'coupon'">{{ t('cart.remove_coupon') }}</Button>
                        </div>

                        <form v-else @submit.prevent="applyCoupon">
                            <Space.Compact class="w-full">
                                <Input
                                    v-model:value="couponForm.code"
                                    :placeholder="t('cart.coupon_placeholder')"
                                    :aria-label="t('cart.coupon_placeholder')"
                                    name="coupon"
                                    autocomplete="off"
                                    spellcheck="false"
                                    class="store-coupon-field"
                                />
                                <Button html-type="submit" :loading="couponForm.processing" :disabled="couponForm.code.trim() === ''">
                                    {{ t('cart.apply_coupon') }}
                                </Button>
                            </Space.Compact>
                        </form>

                        <Alert v-if="couponForm.errors.coupon" type="error" show-icon class="!mt-2" :message="couponForm.errors.coupon" />
                        <Alert v-else-if="cart.coupon_error" type="warning" show-icon class="!mt-2" :message="cart.coupon_error" />
                    </div>

                    <dl class="store-totals">
                        <div v-if="cart.discount_cents > 0" class="store-totals-row store-totals-discount">
                            <dt>{{ t('cart.discount') }}</dt>
                            <dd>
                                <bdi dir="ltr">−{{ cart.discount_formatted }}</bdi>
                            </dd>
                        </div>
                        <div v-if="cart.tax_cents > 0" class="store-totals-row">
                            <dt class="opacity-60">{{ t('cart.tax') }}</dt>
                            <dd class="font-medium">{{ cart.tax_formatted }}</dd>
                        </div>
                        <div class="store-totals-row store-totals-grand">
                            <dt>{{ t('cart.total') }}</dt>
                            <dd>{{ cart.total_formatted }}</dd>
                        </div>
                    </dl>

                    <Link :href="route('checkout.show')" class="mt-5 block">
                        <Button type="primary" size="large" shape="round" block>{{ t('cart.checkout') }}</Button>
                    </Link>
                    <Link :href="route('catalog.index')" class="mt-2 block">
                        <Button type="link" block>{{ t('cart.continue_shopping') }}</Button>
                    </Link>
                </Card>
            </aside>
        </div>


        <Modal
            :open="confirming !== null"
            :title="confirming === 'coupon' ? t('cart.remove_coupon_confirm') : t('cart.remove_confirm')"
            :ok-text="t('cart.remove')"
            :cancel-text="t('common.cancel')"
            :ok-button-props="{ danger: true }"
            centered
            @ok="confirmRemoval"
            @cancel="confirming = null"
        />
    </div>
</template>
