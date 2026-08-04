<script setup lang="ts">
import Pagination from '@/components/store/Pagination.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import QuantityInput from '@/components/store/QuantityInput.vue';
import RatingStars from '@/components/store/RatingStars.vue';
import type { PaginationLink, ProductCardData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Heart, ImageOff, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface VariantData {
    id: number;
    name: string;
    sku: string;
    attributes: Record<string, string> | null;
    price_cents: number;
    current_price_cents: number;
    current_price_formatted: string;
    on_sale: boolean;
    image: string | null;
    available: number;
}

interface ProductDetail {
    id: number;
    name: string;
    slug: string;
    sku: string;
    brand: { id: number; name: string; slug: string } | null;
    category: { id: number; name: string; name_ar?: string | null; slug: string } | null;
    parent_category: { id: number; name: string; name_ar?: string | null; slug: string } | null;
    short_description: string | null;
    description: string | null;
    price_formatted: string;
    on_sale: boolean;
    sale_price_formatted: string | null;
    current_price_formatted: string;
    warranty_months: number | null;
    status: string;
    purchasable: boolean;
    has_variants: boolean;
    meta_title: string | null;
    meta_description: string | null;
    images: { id: number; url: string; alt: string | null; is_primary: boolean }[];
    variants: VariantData[];
    available: number;
}

interface ReviewData {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    author: string;
    verified: boolean;
    created_at: string;
}

const props = defineProps<{
    product: ProductDetail;
    specifications: Record<string, { name: string; value: string; unit: string | null }[]>;
    related: { data: ProductCardData[] };
    reviews: { data: ReviewData[]; links: PaginationLink[] };
    ratingSummary: { avg: number; count: number };
    canReview: boolean;
}>();

const { t } = useI18n();
const page = usePage();
const authenticated = computed(() => (page.props.auth as { user: unknown | null }).user !== null);

const selectedImageIndex = ref(
    Math.max(
        0,
        props.product.images.findIndex((img) => img.is_primary),
    ),
);
const selectedVariantId = ref<number | null>(props.product.variants.length > 0 ? props.product.variants[0].id : null);
const quantity = ref(1);
const adding = ref(false);
const buying = ref(false);

const selectedVariant = computed(() => props.product.variants.find((v) => v.id === selectedVariantId.value) ?? null);

const currentImage = computed(() => selectedVariant.value?.image ?? props.product.images[selectedImageIndex.value]?.url ?? null);

const displayPrice = computed(() => selectedVariant.value?.current_price_formatted ?? props.product.current_price_formatted);

const available = computed(() => (props.product.has_variants ? (selectedVariant.value?.available ?? 0) : props.product.available));

const canBuy = computed(() => props.product.purchasable && available.value > 0);

function addToCart() {
    adding.value = true;
    router.post(
        route('cart.add'),
        {
            product_id: props.product.id,
            variant_id: selectedVariantId.value,
            quantity: quantity.value,
        },
        { preserveScroll: true, onFinish: () => (adding.value = false) },
    );
}

/** Add to cart, then jump straight to checkout. */
function buyNow() {
    buying.value = true;
    router.post(
        route('cart.add'),
        {
            product_id: props.product.id,
            variant_id: selectedVariantId.value,
            quantity: quantity.value,
        },
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

const reviewForm = useForm({ rating: 5, title: '', body: '' });

function submitReview() {
    reviewForm.post(route('reviews.store', props.product.slug), {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset(),
    });
}
</script>

<template>
    <Head :title="product.meta_title ?? product.name">
        <meta v-if="product.meta_description" name="description" :content="product.meta_description" />
    </Head>

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <!-- Breadcrumbs -->
        <nav class="mb-4 flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
            <Link :href="route('home')" class="hover:text-foreground">{{ t('nav.home') }}</Link>
            <span>/</span>
            <template v-if="product.parent_category">
                <Link :href="route('catalog.category', product.parent_category.slug)" class="hover:text-foreground">
                    {{ product.parent_category.name }}
                </Link>
                <span>/</span>
            </template>
            <template v-if="product.category">
                <Link :href="route('catalog.category', product.category.slug)" class="hover:text-foreground">
                    {{ product.category.name }}
                </Link>
                <span>/</span>
            </template>
            <span class="text-foreground">{{ product.name }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-2">
            <!-- Gallery -->
            <div>
                <div class="flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-border bg-card">
                    <img v-if="currentImage" :src="currentImage" :alt="product.name" class="h-full w-full object-contain" />
                    <ImageOff v-else class="h-16 w-16 text-muted-foreground" />
                </div>
                <div v-if="product.images.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="(image, index) in product.images"
                        :key="image.id"
                        type="button"
                        class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition"
                        :class="index === selectedImageIndex ? 'border-black dark:border-white' : 'border-border hover:border-neutral-400'"
                        @click="selectedImageIndex = index"
                    >
                        <img :src="image.url" :alt="image.alt ?? product.name" class="h-full w-full object-contain" />
                    </button>
                </div>
            </div>

            <!-- Info -->
            <div class="flex flex-col gap-4">
                <div>
                    <Link
                        v-if="product.brand"
                        :href="route('catalog.brand', product.brand.slug)"
                        class="text-sm font-medium uppercase tracking-wide text-black hover:underline dark:text-white"
                    >
                        {{ product.brand.name }}
                    </Link>
                    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ product.name }}</h1>

                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <RatingStars :rating="ratingSummary.avg" :count="ratingSummary.count" size="md" />
                        <span>{{ t('product.sku') }}: {{ selectedVariant?.sku ?? product.sku }}</span>
                    </div>
                </div>

                <p v-if="product.short_description" class="text-muted-foreground">{{ product.short_description }}</p>

                <!-- Price -->
                <div class="flex items-end gap-3">
                    <span class="text-3xl font-extrabold" :class="product.on_sale ? 'text-red-600' : ''">{{ displayPrice }}</span>
                    <span v-if="product.on_sale && !selectedVariant" class="pb-1 text-lg text-muted-foreground line-through">
                        {{ product.price_formatted }}
                    </span>
                </div>

                <!-- Stock -->
                <p class="text-sm font-medium">
                    <span v-if="!canBuy" class="text-red-600">{{ t('product.out_of_stock') }}</span>
                    <span v-else-if="available <= 5" class="text-amber-600">{{ t('product.only_left', { count: available }) }}</span>
                    <span v-else class="text-green-600">{{ t('product.in_stock') }}</span>
                </p>

                <!-- Variants -->
                <div v-if="product.variants.length > 0">
                    <h3 class="mb-2 text-sm font-medium">{{ t('product.choose_variant') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="variant in product.variants"
                            :key="variant.id"
                            type="button"
                            class="rounded-lg border px-4 py-2 text-sm transition"
                            :class="[
                                variant.id === selectedVariantId
                                    ? 'border-black bg-neutral-100 font-semibold text-black dark:border-white dark:bg-neutral-800 dark:text-white'
                                    : 'border-border hover:border-neutral-400',
                                variant.available <= 0 ? 'opacity-50' : '',
                            ]"
                            @click="selectedVariantId = variant.id"
                        >
                            {{ variant.name }}
                            <span class="ms-1 text-xs text-muted-foreground">{{ variant.current_price_formatted }}</span>
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <QuantityInput v-model="quantity" :max="Math.max(1, Math.min(10, available))" />
                    <button
                        type="button"
                        :disabled="!canBuy || adding"
                        class="flex-1 rounded-xl bg-black px-6 py-3 font-semibold text-white transition hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-black sm:flex-none"
                        @click="addToCart"
                    >
                        {{ t('product.add_to_cart') }}
                    </button>
                    <button
                        type="button"
                        :disabled="!canBuy || buying"
                        class="flex-1 rounded-xl bg-[#0F9D58] px-6 py-3 font-semibold text-white transition hover:opacity-85 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none"
                        @click="buyNow"
                    >
                        {{ buying ? t('common.loading') : t('product.buy_now') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-border p-3 text-muted-foreground transition hover:border-red-300 hover:text-red-500"
                        :aria-label="t('nav.wishlist')"
                        @click="toggleWishlist"
                    >
                        <Heart class="h-5 w-5" />
                    </button>
                </div>

                <p v-if="product.warranty_months" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <ShieldCheck class="h-4 w-4 text-green-600" />
                    {{ t('product.warranty', { months: product.warranty_months }) }}
                </p>
            </div>
        </div>

        <!-- Description & specifications -->
        <div class="mt-12 grid gap-8 lg:grid-cols-2">
            <section v-if="product.description">
                <h2 class="mb-3 text-lg font-bold">{{ t('product.description') }}</h2>
                <p class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{{ product.description }}</p>
            </section>

            <section v-if="Object.keys(specifications).length > 0">
                <h2 class="mb-3 text-lg font-bold">{{ t('product.specifications') }}</h2>
                <div v-for="(items, group) in specifications" :key="group" class="mb-4">
                    <h3 class="mb-1 text-sm font-semibold text-black dark:text-white">{{ group }}</h3>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="spec in items" :key="spec.name" class="border-b border-border last:border-0">
                                <td class="w-1/3 py-2 text-muted-foreground">{{ spec.name }}</td>
                                <td class="py-2 font-medium">
                                    {{ spec.value }}<template v-if="spec.unit"> {{ spec.unit }}</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Reviews -->
        <section class="mt-12">
            <h2 class="mb-4 text-lg font-bold">{{ t('product.reviews') }} ({{ ratingSummary.count }})</h2>

            <form v-if="canReview" class="mb-6 max-w-xl space-y-3 rounded-xl border border-border bg-card p-4" @submit.prevent="submitReview">
                <h3 class="font-semibold">{{ t('product.write_review') }}</h3>
                <div>
                    <label class="mb-1 block text-sm">{{ t('product.rating') }}</label>
                    <select v-model.number="reviewForm.rating" class="rounded-lg border border-input bg-background px-3 py-2 text-sm">
                        <option v-for="i in 5" :key="i" :value="6 - i">{{ 6 - i }} ★</option>
                    </select>
                    <p v-if="reviewForm.errors.rating" class="mt-1 text-xs text-red-500">{{ reviewForm.errors.rating }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm">{{ t('product.review_title') }}</label>
                    <input v-model="reviewForm.title" type="text" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm">{{ t('product.review_body') }}</label>
                    <textarea v-model="reviewForm.body" rows="3" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                    <p v-if="reviewForm.errors.body" class="mt-1 text-xs text-red-500">{{ reviewForm.errors.body }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="reviewForm.processing"
                    class="rounded-lg bg-black px-4 py-2 text-sm font-semibold text-white transition hover:opacity-85 disabled:opacity-50 dark:bg-white dark:text-black"
                >
                    {{ t('product.submit_review') }}
                </button>
            </form>

            <p v-if="reviews.data.length === 0" class="text-sm text-muted-foreground">{{ t('product.no_reviews') }}</p>

            <div v-else class="space-y-4">
                <article v-for="review in reviews.data" :key="review.id" class="rounded-xl border border-border bg-card p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <RatingStars :rating="review.rating" />
                        <span class="text-sm font-semibold">{{ review.author }}</span>
                        <span
                            v-if="review.verified"
                            class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700 dark:bg-green-950 dark:text-green-300"
                        >
                            {{ t('product.verified_purchase') }}
                        </span>
                        <span class="ms-auto text-xs text-muted-foreground">{{ review.created_at }}</span>
                    </div>
                    <h4 v-if="review.title" class="mt-2 text-sm font-semibold">{{ review.title }}</h4>
                    <p v-if="review.body" class="mt-1 text-sm text-muted-foreground">{{ review.body }}</p>
                </article>

                <Pagination :links="reviews.links" />
            </div>
        </section>

        <!-- Related -->
        <section v-if="related.data.length > 0" class="mt-12">
            <h2 class="mb-4 text-lg font-bold">{{ t('product.related') }}</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="item in related.data" :key="item.id" :product="item" />
            </div>
        </section>
    </div>
</template>
