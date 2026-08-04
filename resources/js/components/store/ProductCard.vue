<script setup lang="ts">
import RatingStars from '@/components/store/RatingStars.vue';
import type { ProductCardData } from '@/components/store/types';
import { Link, router, usePage } from '@inertiajs/vue3';
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
    <article
        class="group relative flex flex-col overflow-hidden rounded-2xl border border-black/5 bg-white text-card-foreground transition hover:shadow-lg dark:border-white/10 dark:bg-neutral-900"
    >
        <span
            v-if="product.on_sale && product.discount_percent"
            class="absolute top-3 z-10 rounded-md bg-[#0F9D58] px-2 py-1 text-[11px] font-bold uppercase leading-none tracking-wide text-white ltr:left-3 rtl:right-3"
        >
            {{ t('product.percent_off', { percent: product.discount_percent }) }}
        </span>

        <button
            type="button"
            class="absolute top-2 z-10 rounded-full bg-white/80 p-1.5 text-gray-600 shadow transition hover:text-red-500 dark:bg-gray-900/80 dark:text-gray-300 lg:opacity-0 lg:group-hover:opacity-100 ltr:right-2 rtl:left-2"
            :aria-label="t('nav.wishlist')"
            @click.prevent="toggleWishlist"
        >
            <Heart class="h-4 w-4" />
        </button>

        <Link :href="route('products.show', product.slug)" class="flex aspect-square items-center justify-center overflow-hidden p-4">
            <img
                v-if="product.image"
                :src="product.image"
                :alt="product.name"
                loading="lazy"
                class="h-full w-full object-contain transition duration-300 group-hover:scale-105"
            />
            <ImageOff v-else class="h-10 w-10 text-muted-foreground" />
        </Link>

        <div class="flex flex-1 flex-col gap-1 px-3 pb-3">
            <p v-if="product.brand" class="text-[11px] uppercase tracking-wide text-muted-foreground">{{ product.brand.name }}</p>

            <Link :href="route('products.show', product.slug)" class="line-clamp-2 min-h-10 text-sm font-medium leading-snug hover:opacity-70">
                {{ product.name }}
            </Link>

            <RatingStars v-if="product.rating_count > 0" :rating="product.rating_avg" :count="product.rating_count" />

            <!-- Price line follows the reference: price, was-price, saving. -->
            <div class="mt-1 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <span class="text-base font-bold">{{ product.current_price_formatted }}</span>
                <template v-if="product.on_sale">
                    <span class="text-xs text-muted-foreground line-through">{{ product.price_formatted }}</span>
                    <span class="text-xs font-semibold text-[#0F9D58]">
                        {{ t('product.percent_off', { percent: product.discount_percent }) }}
                    </span>
                </template>
            </div>

            <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                <span v-if="!product.in_stock" class="rounded-lg bg-muted px-2 py-1 text-[11px] font-medium text-muted-foreground">
                    {{ t('product.out_of_stock') }}
                </span>
                <button
                    v-else
                    type="button"
                    :disabled="buying"
                    class="rounded-full bg-[#0F9D58] px-3.5 py-2 text-xs font-semibold text-white transition hover:opacity-85 disabled:opacity-50"
                    @click.prevent="buyNow"
                >
                    {{ t('product.buy_now') }}
                </button>

                <button
                    v-if="product.in_stock"
                    type="button"
                    :disabled="adding"
                    class="inline-flex shrink-0 items-center justify-center rounded-full bg-black p-2.5 text-white transition hover:opacity-85 disabled:opacity-50 dark:bg-white dark:text-black"
                    :aria-label="t('product.add_to_cart')"
                    @click="addToCart"
                >
                    <ShoppingCart class="h-4 w-4" />
                </button>
            </div>
        </div>
    </article>
</template>
