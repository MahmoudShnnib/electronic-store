<script setup lang="ts">
import CatchGame from '@/components/store/CatchGame.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import SnakeButton from '@/components/store/SnakeButton.vue';
import type { ProductCardData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { LeftOutlined, RightOutlined } from '@ant-design/icons-vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Carousel as ACarousel, Button, Col, Row, Typography } from 'ant-design-vue';
import {
    Banknote,
    Cpu,
    Flame,
    Gamepad2,
    Headphones,
    Laptop,
    Monitor,
    MousePointer,
    Package,
    Printer,
    RotateCcw,
    Router,
    ShieldCheck,
    Smartphone,
    Sparkles,
    TrendingUp,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

const { Title, Text, Paragraph } = Typography;

interface HomeCategory {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    /** Borrowed from a product in this category's subtree; null if none have photos. */
    image: string | null;
    /** Products anywhere beneath this category, not just directly in it. */
    products_count: number;
    /** Best live discount in the subtree, as a whole percentage. 0 when nothing is on sale. */
    max_discount: number;
}

interface HomeBrand {
    id: number;
    name: string;
    slug: string;
    logo_path: string | null;
}

interface CategorySection {
    category: { name: string; name_ar: string | null; slug: string };
    products: { data: ProductCardData[] };
}

const props = defineProps<{
    categories: HomeCategory[];
    brands: HomeBrand[];
    featured: { data: ProductCardData[] };
    newArrivals: { data: ProductCardData[] };
    onSale: { data: ProductCardData[] };
    bestSellers: { data: ProductCardData[] };
    categorySections: CategorySection[];
}>();

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

function categoryName(category: HomeCategory): string {
    return locale.value === 'ar' && category.name_ar ? category.name_ar : category.name;
}

/** Map category names to Cyber-style icons; Package is the fallback. */
function categoryIcon(category: HomeCategory) {
    const name = category.name.toLowerCase();
    if (name.includes('phone')) return Smartphone;
    if (name.includes('laptop') || name.includes('ultrabook')) return Laptop;
    if (name.includes('monitor') || name.includes('computer') || name.includes('desktop')) return Monitor;
    if (name.includes('gaming') || name.includes('console') || name.includes('controller')) return Gamepad2;
    if (name.includes('network') || name.includes('router') || name.includes('switch')) return Router;
    if (name.includes('component') || name.includes('processor') || name.includes('graphic') || name.includes('memory')) return Cpu;
    if (name.includes('printer')) return Printer;
    if (name.includes('headset') || name.includes('audio')) return Headphones;
    if (name.includes('accessor') || name.includes('mice') || name.includes('mouse') || name.includes('keyboard')) return MousePointer;
    return Package;
}

/** Distinct products, in order, capped at `limit`. */
function takeUnique(pool: ProductCardData[], limit: number, exclude: number[] = []): ProductCardData[] {
    const unique: ProductCardData[] = [];
    for (const product of pool) {
        if (exclude.includes(product.id)) continue;
        if (unique.some((candidate) => candidate.id === product.id)) continue;
        unique.push(product);
        if (unique.length === limit) break;
    }
    return unique;
}

const HERO_SLIDE_COUNT = 5;
const HERO_ROTATE_MS = 6000;

const heroSlides = computed(() => takeUnique([...props.featured.data, ...props.newArrivals.data], HERO_SLIDE_COUNT));

// a-carousel owns slide position/animation/dots; we just drive it via ref for
// keyboard + the custom arrow buttons (its built-in nav isn't RTL-aware).
const carouselRef = ref<InstanceType<typeof ACarousel>>();
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const heroAutoplay = computed(() => !prefersReducedMotion && heroSlides.value.length > 1);

function nextSlide() {
    carouselRef.value?.next();
}
function previousSlide() {
    carouselRef.value?.prev();
}

/** Split a product name so the last word can carry the bold accent. */
function splitName(name: string): { light: string; bold: string } {
    const parts = name.trim().split(' ');
    if (parts.length === 1) return { light: '', bold: name };
    return { light: parts.slice(0, -1).join(' '), bold: parts[parts.length - 1] };
}

/** The service promises the shop actually keeps — mirrored at checkout. */
const trustItems = [
    { key: 'trust_cod', icon: Banknote },
    { key: 'trust_delivery', icon: Truck },
    { key: 'trust_genuine', icon: ShieldCheck },
    { key: 'trust_returns', icon: RotateCcw },
];

/** Stacked homepage rows, each rendered only when it has something to show.
 *  Curated, not exhaustive: the hero already carries the featured products, so
 *  there is no featured grid, and each row is capped at one full 6-up line —
 *  the "More" link is the way to the rest, not a longer wall of cards. */
const SECTION_CAP = 6;
const productSections = computed(() =>
    [
        { key: 'deals', title: t('home.top_deals'), icon: Flame, products: props.onSale.data, href: route('catalog.index', { on_sale: 1 }) },
        { key: 'newArrivals', title: t('home.new_arrivals'), icon: Sparkles, products: props.newArrivals.data, href: route('catalog.index') },
        { key: 'bestSellers', title: t('home.best_sellers'), icon: TrendingUp, products: props.bestSellers.data, href: route('catalog.index') },
    ]
        .map((section) => ({ ...section, products: section.products.slice(0, SECTION_CAP) }))
        .filter((section) => section.products.length > 0),
);

/** Distinct product photos that fall in the mini-game. */
const gameImages = computed(() => [
    ...new Set(
        productSections.value
            .flatMap((section) => section.products)
            .map((product) => product.image)
            .filter((image): image is string => image !== null),
    ),
]);

/** A single tile can't loop seamlessly — render it once, unanimated. */
const brandCopies = computed(() => (props.brands.length >= 2 ? 3 : 1));

/** Two promo tiles beside the hero: best live discount first, then the biggest
 *  category — the truthful stand-ins for the reference site's ad banners. */
const sideTiles = computed(() => {
    const ranked = [...props.categories].sort((a, b) => b.max_discount - a.max_discount || b.products_count - a.products_count);
    return ranked.slice(0, 2);
});

function sectionTitle(section: CategorySection): string {
    return locale.value === 'ar' && section.category.name_ar ? section.category.name_ar : section.category.name;
}
</script>

<template>
    <Head :title="t('nav.home')" />

    <!-- One <h1> for the page. The hero rotates, so its slide titles are <h2>s —
         a carousel that changes the page's only top-level heading every six
         seconds gives a screen reader a document outline that will not sit still. -->
    <h1 class="sr-only">{{ $page.props.name }} — {{ t('home.hero_subtitle') }}</h1>

    <!-- Hero: a framed carousel with two promo tiles beside it, the reference
         site's opening move. The tiles are the two categories with the best
         live story to tell (biggest discount, then sheer size). -->
    <section class="mx-auto max-w-7xl px-4 pt-6">
        <div class="grid gap-4 lg:grid-cols-3">
            <div
                class="store-slab store-slab-hero store-slab-deco relative overflow-hidden rounded-2xl lg:col-span-2"
                role="region"
                aria-roledescription="carousel"
                :aria-label="t('home.featured')"
                tabindex="0"
                @keydown.left.prevent="locale === 'ar' ? nextSlide() : previousSlide()"
                @keydown.right.prevent="locale === 'ar' ? previousSlide() : nextSlide()"
            >
                <ACarousel
                    v-if="heroSlides.length > 0"
                    ref="carouselRef"
                    :autoplay="heroAutoplay"
                    :autoplay-speed="HERO_ROTATE_MS"
                    dots
                    effect="fade"
                >
                    <div v-for="(slide, index) in heroSlides" :key="slide.id">
                        <div class="grid items-center gap-6 px-6 py-10 sm:px-10 lg:grid-cols-2 lg:py-14">
                            <div class="text-center lg:text-start">
                                <Text class="store-slab-ink-soft !text-sm !font-semibold sm:!text-lg">{{ t('home.featured') }}</Text>
                                <Title :level="2" class="store-slab-title !mt-2 !text-3xl !font-light !leading-tight !tracking-tight sm:!text-5xl">
                                    {{ splitName(slide.name).light }}
                                    <span class="font-semibold">{{ splitName(slide.name).bold }}</span>
                                </Title>
                                <Paragraph class="store-slab-ink-soft !mt-3 !text-base">{{ t('home.hero_subtitle') }}</Paragraph>
                                <p class="mt-1 text-2xl font-light">{{ slide.current_price_formatted }}</p>
                                <div class="mt-6">
                                    <SnakeButton :href="route('products.show', slide.slug)">{{ t('home.shop_now') }}</SnakeButton>
                                </div>
                            </div>
                            <div class="hidden items-center justify-center lg:flex">
                                <!-- The first slide is the page's LCP element; the rest are
                                     off-screen until the carousel advances. -->
                                <img
                                    v-if="slide.image"
                                    :src="slide.image"
                                    :alt="slide.name"
                                    width="512"
                                    height="512"
                                    class="max-h-64 w-auto rounded-2xl object-contain drop-shadow-2xl"
                                    :loading="index === 0 ? 'eager' : 'lazy'"
                                    :fetchpriority="index === 0 ? 'high' : undefined"
                                    decoding="async"
                                />
                                <div v-else class="store-slab-well flex h-56 w-56 items-center justify-center rounded-3xl">
                                    <Package class="h-20 w-20 opacity-25" />
                                </div>
                            </div>
                        </div>
                    </div>
                </ACarousel>

                <!-- Empty state: no products at all. -->
                <div v-else class="px-6 py-16 text-center">
                    <Title :level="1" class="store-slab-title !text-5xl !font-light sm:!text-6xl">{{ $page.props.name }}</Title>
                    <Paragraph class="store-slab-ink-soft !mt-4 !text-lg">{{ t('home.hero_subtitle') }}</Paragraph>
                    <div class="mt-8">
                        <SnakeButton :href="route('catalog.index')">{{ t('home.view_all') }}</SnakeButton>
                    </div>
                </div>

                <template v-if="heroSlides.length > 1">
                    <Button
                        shape="circle"
                        class="store-slab-arrow !absolute start-2 top-1/2 hidden -translate-y-1/2 sm:!flex"
                        :aria-label="t('home.previous_slide')"
                        @click="previousSlide"
                    >
                        <template #icon><component :is="locale === 'ar' ? RightOutlined : LeftOutlined" /></template>
                    </Button>
                    <Button
                        shape="circle"
                        class="store-slab-arrow !absolute end-2 top-1/2 hidden -translate-y-1/2 sm:!flex"
                        :aria-label="t('home.next_slide')"
                        @click="nextSlide"
                    >
                        <template #icon><component :is="locale === 'ar' ? LeftOutlined : RightOutlined" /></template>
                    </Button>
                </template>
            </div>

            <!-- Side promo tiles -->
            <div v-if="sideTiles.length > 0" class="grid grid-cols-2 gap-4 lg:grid-cols-1 lg:grid-rows-2">
                <Link
                    v-for="tile in sideTiles"
                    :key="tile.id"
                    :href="route('catalog.category', tile.slug)"
                    class="group relative block h-36 overflow-hidden rounded-2xl sm:h-44 lg:h-full lg:min-h-[10rem]"
                >
                    <img
                        v-if="tile.image"
                        :src="tile.image"
                        :alt="categoryName(tile)"
                        width="640"
                        height="360"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                    <div v-else class="store-slab absolute inset-0 flex items-center justify-center">
                        <component :is="categoryIcon(tile)" class="h-12 w-12 opacity-30" />
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-4 text-start text-white">
                        <p class="text-base font-semibold leading-tight">{{ categoryName(tile) }}</p>
                        <p v-if="tile.max_discount > 0" class="mt-1 text-sm font-bold text-[#4ade9b]">
                            {{ t('home.up_to_off', { percent: tile.max_discount }) }}
                        </p>
                        <p v-else class="mt-1 text-xs opacity-80">{{ t('home.product_count', tile.products_count) }}</p>
                    </div>
                </Link>
            </div>
        </div>
    </section>

    <!-- Categories: bold accent tile cards straight under the hero — the page's
         map. Icon in a chip, name and size inside the card; the discount pill
         only appears when a category actually has a live sale. -->
    <section v-if="categories.length > 0" class="mx-auto max-w-7xl px-4 pt-10">
        <div class="mb-6 flex items-center justify-between">
            <Title :level="3" class="store-section-title !m-0 !text-2xl !font-medium">{{ t('home.shop_by_category') }}</Title>
            <Link :href="route('catalog.index')">
                <Button type="link" class="!px-0">
                    {{ t('home.more') }}
                    <template #icon><RightOutlined class="rtl:rotate-180" /></template>
                </Button>
            </Link>
        </div>
        <div class="grid grid-cols-3 gap-x-3 gap-y-5 sm:grid-cols-4 lg:grid-cols-6 lg:gap-x-4">
            <Link v-for="category in categories" :key="category.id" :href="route('catalog.category', category.slug)" class="group block text-center">
                <div class="store-category-card">
                    <img
                        v-if="category.image"
                        :src="category.image"
                        :alt="categoryName(category)"
                        width="160"
                        height="160"
                        loading="lazy"
                        decoding="async"
                    />
                    <component :is="categoryIcon(category)" v-else class="h-10 w-10 text-black/35" />
                </div>
                <span class="mt-2 line-clamp-2 block text-xs font-semibold leading-snug sm:text-sm">{{ categoryName(category) }}</span>
                <span v-if="category.max_discount > 0" class="mt-0.5 block text-xs font-bold text-[#0f9d58]">
                    {{ t('home.up_to_off', { percent: category.max_discount }) }}
                </span>
                <span v-else class="mt-0.5 block text-xs opacity-55">{{ t('home.product_count', category.products_count) }}</span>
            </Link>
        </div>
    </section>

    <!-- Trust strip: the promises the shop actually keeps. -->
    <section class="mx-auto max-w-7xl px-4 pt-8">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="item in trustItems" :key="item.key" class="store-trust-item">
                <component :is="item.icon" class="h-5 w-5" />
                <span>{{ t(`home.${item.key}`) }}</span>
            </div>
        </div>
    </section>

    <!-- Stacked product sections, each with its own "More" link -->
    <section v-for="section in productSections" :key="section.key" class="mx-auto max-w-7xl px-4 pt-10">
        <div class="mb-4 flex items-center justify-between">
            <Title :level="3" class="store-section-title !m-0 !flex !items-center !gap-2 !text-lg !font-semibold">
                <component :is="section.icon" class="h-5 w-5 text-[#0f9d58]" />
                {{ section.title }}
            </Title>
            <Link :href="section.href">
                <Button type="link" class="!px-0">
                    {{ t('home.more') }}
                    <template #icon><RightOutlined class="rtl:rotate-180" /></template>
                </Button>
            </Link>
        </div>
        <Row :gutter="[12, 12]">
            <Col v-for="product in section.products" :key="product.id" :xs="12" :sm="8" :lg="6" :xl="4">
                <ProductCard :product="product" />
            </Col>
        </Row>
    </section>

    <!-- Category spotlights: the two biggest departments get their own row,
         the reference site's "gaming chairs / headsets" sections driven by
         whatever this catalogue actually stocks. -->
    <section v-for="section in categorySections" :key="section.category.slug" class="mx-auto max-w-7xl px-4 pt-10">
        <div class="mb-4 flex items-center justify-between">
            <Title :level="3" class="store-section-title !m-0 !text-lg !font-semibold">{{ sectionTitle(section) }}</Title>
            <Link :href="route('catalog.category', section.category.slug)">
                <Button type="link" class="!px-0">
                    {{ t('home.more') }}
                    <template #icon><RightOutlined class="rtl:rotate-180" /></template>
                </Button>
            </Link>
        </div>
        <Row :gutter="[12, 12]">
            <Col v-for="product in section.products.data" :key="product.id" :xs="12" :sm="8" :lg="6" :xl="4">
                <ProductCard :product="product" />
            </Col>
        </Row>
    </section>

    <!-- Big sale banner -->
    <section class="store-slab store-slab-deco mt-14 py-24 text-center">
        <div class="mx-auto max-w-3xl px-4">
            <Title :level="2" class="store-slab-title !text-5xl !font-light !tracking-tight sm:!text-6xl">
                {{ t('home.sale_banner_title') }}
            </Title>
            <Paragraph class="store-slab-ink-soft !mt-4">{{ t('home.sale_banner_subtitle') }}</Paragraph>
            <div class="mt-10">
                <SnakeButton :href="route('catalog.index', { on_sale: 1 })">{{ t('home.view_all') }}</SnakeButton>
            </div>
        </div>
    </section>

    <!-- Brands -->
    <section v-if="brands.length > 0" class="store-band overflow-hidden py-14">
        <div class="mx-auto max-w-7xl px-4">
            <Title :level="3" class="store-section-title !mb-8 !text-2xl !font-medium">{{ t('home.shop_by_brand') }}</Title>
        </div>
        <div class="promo-marquee-wrap store-marquee-mask flex">
            <div
                v-for="copy in brandCopies"
                :key="copy"
                class="promo-marquee flex shrink-0 gap-3 pe-3"
                :class="{ 'promo-marquee-static': brandCopies === 1 }"
                :style="{ animationDuration: `${brands.length * 2.5}s` }"
                :aria-hidden="copy > 1 ? 'true' : undefined"
            >
                <Link v-for="brand in brands" :key="brand.id" :href="route('catalog.brand', brand.slug)" :tabindex="copy > 1 ? -1 : undefined">
                    <Button shape="round" class="!whitespace-nowrap">{{ brand.name }}</Button>
                </Link>
            </div>
        </div>
    </section>

    <!-- Mini-game: catch the falling products -->
    <CatchGame v-if="gameImages.length > 0" :images="gameImages" />
</template>

<style>
@keyframes promo-marquee-ltr {
    to {
        transform: translateX(-100%);
    }
}

@keyframes promo-marquee-rtl {
    to {
        transform: translateX(100%);
    }
}

.promo-marquee {
    animation: promo-marquee-ltr 48s linear infinite;
}

[dir='rtl'] .promo-marquee {
    animation-name: promo-marquee-rtl;
}

.promo-marquee-wrap:hover .promo-marquee,
.promo-marquee-wrap:focus-within .promo-marquee {
    animation-play-state: paused;
}

.promo-marquee-static,
[dir='rtl'] .promo-marquee-static {
    animation: none;
}

@media (prefers-reduced-motion: reduce) {
    .promo-marquee,
    [dir='rtl'] .promo-marquee {
        animation: none;
    }
}
</style>
