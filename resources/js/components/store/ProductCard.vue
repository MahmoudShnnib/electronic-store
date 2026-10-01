<script setup lang="ts">
import RatingStars from '@/components/store/RatingStars.vue';
import type { ProductCardData } from '@/components/store/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Button, Card, Tag, Tooltip } from 'ant-design-vue';
import { Heart, ImageOff, ShoppingCart } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ product: ProductCardData }>();

const { t } = useI18n();
const page = usePage();
const authenticated = computed(() => (page.props.auth as { user: unknown | null }).user !== null);

const adding = ref(false);
const buying = ref(false);

function addToCart() {
    // Variant products need a colour/option picked first.
    if (props.product.has_variants) {
        router.visit(route('products.show', props.product.slug));

        return;
    }

    adding.value = true;
    router.post(route('cart.add'), { product_id: props.product.id, quantity: 1 }, { preserveScroll: true, onFinish: () => (adding.value = false) });
}

/** Straight to checkout with one unit (or to the page when a variant must be chosen). */
function buyNow() {
    if (props.product.has_variants) {
        router.visit(route('products.show', props.product.slug));

        return;
    }

    buying.value = true;
    router.post(
        route('cart.add'),
        { product_id: props.product.id, quantity: 1 },
        {
            preserveScroll: true,
            onSuccess: () => router.visit(route('checkout.show')),
            onError: () => (buying.value = false),
        },
    );
}

function toggleWishlist() {
    if (!authenticated.value) {
        router.visit(route('login'));

        return;
    }

    router.post(route('wishlist.toggle', props.product.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Card class="store-product-card h-full" :bordered="true" hoverable :body-style="{ padding: '0.75rem', display: 'flex', flex: '1 1 auto' }">
        <template #cover>
            <div class="store-product-media">
                <Tag v-if="product.on_sale && product.discount_percent" color="success" class="store-product-flag">
                    {{ t('product.percent_off', { percent: product.discount_percent }) }}
                </Tag>

                <Tooltip :title="product.wishlisted ? t('product.remove_from_wishlist') : t('product.save_to_wishlist')">
                    <Button
                        type="text"
                        shape="circle"
                        :class="['store-product-wish', { 'store-product-wish-on': product.wishlisted }]"
                        :aria-pressed="product.wishlisted"
                        :aria-label="product.wishlisted ? t('product.remove_from_wishlist') : t('product.save_to_wishlist')"
                        @click.prevent.stop="toggleWishlist"
                    >
                        <template #icon>
                            <Heart :key="String(product.wishlisted)" class="store-heart h-4 w-4" :class="{ 'fill-current': product.wishlisted }" />
                        </template>
                    </Button>
                </Tooltip>

                <Link :href="route('products.show', product.slug)" class="store-product-frame" :aria-label="product.name">
                    <img
                        v-if="product.image"
                        :src="product.image"
                        :alt="product.name"
                        loading="lazy"
                        decoding="async"
                        width="400"
                        height="400"
                        class="store-product-img"
                    />
                    <ImageOff v-else class="h-10 w-10 opacity-30" />
                </Link>
            </div>
        </template>

        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <p v-if="product.brand" class="store-product-brand">{{ product.brand.name }}</p>

            <Link :href="route('products.show', product.slug)" class="store-product-name">{{ product.name }}</Link>

            <RatingStars v-if="product.rating_count > 0" :rating="product.rating_avg" :count="product.rating_count" />

            <!-- Price line: what you pay, what it was, what you save. -->
            <div class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <span class="store-product-price">
                    <template v-if="product.price_from">{{ t('product.from_price') }} </template>{{ product.current_price_formatted }}
                </span>
                <template v-if="product.on_sale">
                    <span class="store-product-was">{{ product.price_formatted }}</span>
                    <span class="store-product-save">{{ t('product.percent_off', { percent: product.discount_percent }) }}</span>
                </template>
            </div>

            <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                <Tag v-if="!product.in_stock" class="!m-0">{{ t('product.out_of_stock') }}</Tag>

                <template v-else>
                    <Button type="primary" shape="round" :loading="buying" @click.prevent="buyNow">
                        {{ t('product.buy_now') }}
                    </Button>

                    <Tooltip :title="t('product.add_to_cart')">
                        <Button shape="circle" size="large" :loading="adding" :aria-label="t('product.add_to_cart')" @click="addToCart">
                            <template #icon><ShoppingCart class="h-4 w-4" /></template>
                        </Button>
                    </Tooltip>
                </template>
            </div>
        </div>
    </Card>
</template>
