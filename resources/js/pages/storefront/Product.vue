<script setup lang="ts">
import Pagination from '@/components/store/Pagination.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import ProductDescription from '@/components/store/ProductDescription.vue';
import QuantityInput from '@/components/store/QuantityInput.vue';
import RatingStars from '@/components/store/RatingStars.vue';
import type { PaginationLink, ProductCardData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Alert,
    Breadcrumb,
    BreadcrumbItem,
    Button,
    Card,
    Descriptions,
    DescriptionsItem,
    Form,
    FormItem,
    Image,
    Input,
    Radio,
    RadioGroup,
    Rate,
    TabPane,
    Tabs,
    Tag,
    Textarea,
    Tooltip,
    Typography,
} from 'ant-design-vue';
import { Heart, ImageOff } from 'lucide-vue-next';
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
    status: string;
    purchasable: boolean;
    wishlisted: boolean;
    has_variants: boolean;
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

const { Title, Text, Paragraph } = Typography;

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
    <Head :title="product.name"> </Head>

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <Breadcrumb class="!mb-5">
            <BreadcrumbItem>
                <Link :href="route('home')">{{ t('nav.home') }}</Link>
            </BreadcrumbItem>
            <BreadcrumbItem v-if="product.parent_category">
                <Link :href="route('catalog.category', product.parent_category.slug)">{{ product.parent_category.name }}</Link>
            </BreadcrumbItem>
            <BreadcrumbItem v-if="product.category">
                <Link :href="route('catalog.category', product.category.slug)">{{ product.category.name }}</Link>
            </BreadcrumbItem>
            <BreadcrumbItem>{{ product.name }}</BreadcrumbItem>
        </Breadcrumb>

        <div class="grid gap-8 lg:grid-cols-2">
            <!-- Gallery. AntD's `Image` brings its own lightbox, which is what a
                 shopper reaches for on a product photo. -->
            <div>
                <Card :body-style="{ padding: '0' }" class="overflow-hidden">
                    <div class="store-gallery">
                        <Image
                            v-if="currentImage"
                            :src="currentImage"
                            :alt="product.name"
                            class="store-gallery-img"
                            :preview="{ maskClassName: 'store-gallery-mask' }"
                        />
                        <ImageOff v-else class="h-16 w-16 opacity-25" />
                    </div>
                </Card>

                <div v-if="product.images.length > 1" class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="(image, index) in product.images"
                        :key="image.id"
                        type="button"
                        class="store-thumb"
                        :class="{ 'store-thumb-active': index === selectedImageIndex }"
                        :aria-label="image.alt ?? product.name"
                        @click="selectedImageIndex = index"
                    >
                        <img :src="image.url" :alt="image.alt ?? product.name" width="800" height="800" class="h-full w-full object-contain" />
                    </button>
                </div>
            </div>

            <!-- Info -->
            <div class="flex flex-col gap-4">
                <div>
                    <Link v-if="product.brand" :href="route('catalog.brand', product.brand.slug)" class="store-product-brand hover:underline">
                        {{ product.brand.name }}
                    </Link>
                    <Title :level="1" class="!mb-0 !mt-1 !text-2xl !font-bold sm:!text-3xl">{{ product.name }}</Title>

                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <RatingStars :rating="ratingSummary.avg" :count="ratingSummary.count" size="md" />
                        <Text class="!text-sm opacity-55">{{ t('product.sku') }}: {{ selectedVariant?.sku ?? product.sku }}</Text>
                    </div>
                </div>

                <Paragraph v-if="product.short_description" class="!mb-0 opacity-70">{{ product.short_description }}</Paragraph>

                <!-- Price -->
                <div class="flex items-end gap-3">
                    <span class="store-detail-price" :class="{ 'store-detail-price-sale': product.on_sale }">{{ displayPrice }}</span>
                    <span v-if="product.on_sale && !selectedVariant" class="store-product-was !text-lg">{{ product.price_formatted }}</span>
                </div>

                <!-- Stock -->
                <div>
                    <Tag v-if="!canBuy" color="error">{{ t('product.out_of_stock') }}</Tag>
                    <Tag v-else-if="available <= 5" color="warning">{{ t('product.only_left', { count: available }) }}</Tag>
                    <Tag v-else color="success">{{ t('product.in_stock') }}</Tag>
                </div>

                <!-- Variants -->
                <div v-if="product.variants.length > 0">
                    <h3 class="store-filters-heading">{{ t('product.choose_variant') }}</h3>
                    <RadioGroup v-model:value="selectedVariantId" button-style="solid" class="store-variants">
                        <Radio.Button v-for="variant in product.variants" :key="variant.id" :value="variant.id" :disabled="variant.available <= 0">
                            {{ variant.name }}
                            <span class="ms-1 text-xs opacity-65">{{ variant.current_price_formatted }}</span>
                        </Radio.Button>
                    </RadioGroup>
                </div>

                <!-- Actions -->
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <QuantityInput v-model="quantity" :max="Math.max(1, Math.min(10, available))" />

                    <Button size="large" shape="round" :disabled="!canBuy" :loading="adding" @click="addToCart">
                        {{ t('product.add_to_cart') }}
                    </Button>

                    <Button type="primary" size="large" shape="round" :disabled="!canBuy" :loading="buying" @click="buyNow">
                        {{ t('product.buy_now') }}
                    </Button>

                    <Tooltip :title="product.wishlisted ? t('product.remove_from_wishlist') : t('product.save_to_wishlist')">
                        <Button
                            size="large"
                            shape="circle"
                            :class="{ 'store-wish-on': product.wishlisted }"
                            :aria-pressed="product.wishlisted"
                            :aria-label="product.wishlisted ? t('product.remove_from_wishlist') : t('product.save_to_wishlist')"
                            @click="toggleWishlist"
                        >
                            <template #icon>
                                <Heart
                                    :key="String(product.wishlisted)"
                                    class="store-heart h-4 w-4"
                                    :class="{ 'fill-current': product.wishlisted }"
                                />
                            </template>
                        </Button>
                    </Tooltip>
                </div>
            </div>
        </div>

        <!-- The long tail of the page is three separate reads, so it is three
             tabs rather than three stacked walls the shopper has to scroll past. -->
        <Tabs class="!mt-12" size="large">
            <TabPane v-if="product.description" key="description" :tab="t('product.description')">
                <ProductDescription :text="product.description" />
            </TabPane>

            <TabPane v-if="Object.keys(specifications).length > 0" key="specs" :tab="t('product.specifications')">
                <div class="max-w-3xl space-y-6">
                    <Descriptions v-for="(items, group) in specifications" :key="group" :title="group" bordered :column="1" size="small">
                        <DescriptionsItem v-for="spec in items" :key="spec.name" :label="spec.name">
                            {{ spec.value }}<template v-if="spec.unit"> {{ spec.unit }}</template>
                        </DescriptionsItem>
                    </Descriptions>
                </div>
            </TabPane>

            <TabPane key="reviews" :tab="`${t('product.reviews')} (${ratingSummary.count})`">
                <Card v-if="canReview" class="mb-6 max-w-xl" :title="t('product.write_review')">
                    <Form layout="vertical" @submit.prevent="submitReview">
                        <FormItem
                            :label="t('product.rating')"
                            :validate-status="reviewForm.errors.rating ? 'error' : undefined"
                            :help="reviewForm.errors.rating"
                        >
                            <Rate v-model:value="reviewForm.rating" class="store-rate store-rate-md" />
                        </FormItem>

                        <FormItem :label="t('product.review_title')">
                            <Input v-model:value="reviewForm.title" />
                        </FormItem>

                        <FormItem
                            :label="t('product.review_body')"
                            :validate-status="reviewForm.errors.body ? 'error' : undefined"
                            :help="reviewForm.errors.body"
                        >
                            <Textarea v-model:value="reviewForm.body" :rows="3" />
                        </FormItem>

                        <Button type="primary" html-type="submit" :loading="reviewForm.processing">{{ t('product.submit_review') }}</Button>
                    </Form>
                </Card>

                <Alert v-if="reviews.data.length === 0" type="info" show-icon :message="t('product.no_reviews')" class="max-w-xl" />

                <div v-else class="max-w-3xl space-y-4">
                    <Card v-for="review in reviews.data" :key="review.id" size="small">
                        <div class="flex flex-wrap items-center gap-2">
                            <RatingStars :rating="review.rating" />
                            <span class="text-sm font-semibold">{{ review.author }}</span>
                            <Tag v-if="review.verified" color="success" class="!m-0">{{ t('product.verified_purchase') }}</Tag>
                            <Text class="!ms-auto !text-xs opacity-55">{{ review.created_at }}</Text>
                        </div>
                        <h4 v-if="review.title" class="mt-2 text-sm font-semibold">{{ review.title }}</h4>
                        <Paragraph v-if="review.body" class="!mb-0 !mt-1 !text-sm opacity-75">{{ review.body }}</Paragraph>
                    </Card>

                    <Pagination :links="reviews.links" />
                </div>
            </TabPane>
        </Tabs>

        <!-- Related -->
        <section v-if="related.data.length > 0" class="mt-12">
            <Title :level="2" class="!mb-4 !text-lg !font-bold">{{ t('product.related') }}</Title>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="item in related.data" :key="item.id" :product="item" />
            </div>
        </section>
    </div>
</template>
