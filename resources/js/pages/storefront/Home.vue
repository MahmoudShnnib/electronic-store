<script setup lang="ts">
import CatchGame from '@/components/store/CatchGame.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import type { ProductCardData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Cpu,
    Gamepad2,
    Headphones,
    Laptop,
    Monitor,
    MousePointer,
    Package,
    Printer,
    Router,
    Smartphone,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

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

const props = defineProps<{
    categories: HomeCategory[];
    brands: HomeBrand[];
    featured: { data: ProductCardData[] };
    newArrivals: { data: ProductCardData[] };
    onSale: { data: ProductCardData[] };
    bestSellers: { data: ProductCardData[] };
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

// Hero + banner tiles come from the featured/new pools.

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

const activeSlide = ref(0);
const heroProduct = computed(() => heroSlides.value[activeSlide.value] ?? null);


function goToSlide(index: number) {
    const count = heroSlides.value.length;
    if (count === 0) return;
    activeSlide.value = (index + count) % count;
}

const nextSlide = () => goToSlide(activeSlide.value + 1);
const previousSlide = () => goToSlide(activeSlide.value - 1);

// Autoplay: paused while the pointer or keyboard focus is inside the hero, and
// disabled outright for anyone who asked for reduced motion.
const heroPaused = ref(false);
let heroTimer: ReturnType<typeof setInterval> | null = null;

function stopHeroAutoplay() {
    if (heroTimer !== null) {
        clearInterval(heroTimer);
        heroTimer = null;
    }
}

function startHeroAutoplay() {
    stopHeroAutoplay();
    if (heroSlides.value.length < 2) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    heroTimer = setInterval(() => {
        if (!heroPaused.value) nextSlide();
    }, HERO_ROTATE_MS);
}

onMounted(startHeroAutoplay);
onBeforeUnmount(stopHeroAutoplay);
watch(() => heroSlides.value.length, startHeroAutoplay);

/** Split a product name so the last word can carry the bold accent. */
function splitName(name: string): { light: string; bold: string } {
    const parts = name.trim().split(' ');
    if (parts.length === 1) return { light: '', bold: name };
    return { light: parts.slice(0, -1).join(' '), bold: parts[parts.length - 1] };
}

/**
 * Promo tiles lead with whatever is actually discounted; categories with a live
 * sale come first so the "up to N% off" badge is truthful rather than decorative.
 */
const promoTiles = computed(() =>
    [...props.categories].sort((a, b) => b.max_discount - a.max_discount).slice(0, 6),
);

/** Stacked homepage rows, each rendered only when it has something to show. */
const productSections = computed(() =>
    [
        { key: 'deals', title: t('home.top_deals'), products: props.onSale.data, href: route('catalog.index') },
        { key: 'newArrivals', title: t('home.new_arrivals'), products: props.newArrivals.data, href: route('catalog.index') },
        { key: 'bestSellers', title: t('home.best_sellers'), products: props.bestSellers.data, href: route('catalog.index') },
        { key: 'featured', title: t('home.featured'), products: props.featured.data, href: route('catalog.index') },
    ]
        .map((section) => ({ ...section, products: section.products.slice(0, 8) }))
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
const marqueeCopies = computed(() => (promoTiles.value.length >= 2 ? 3 : 1));
const brandCopies = computed(() => (props.brands.length >= 2 ? 3 : 1));

// Category rail scrolling.
const rail = ref<HTMLElement | null>(null);

function scrollRail(direction: 1 | -1) {
    rail.value?.scrollBy({ left: direction * 340 * (locale.value === 'ar' ? -1 : 1), behavior: 'smooth' });
}
</script>

<template>
    <Head :title="t('nav.home')" />

    <!-- Hero carousel -->
    <section
        class="relative overflow-hidden bg-[#211C24] text-white"
        role="region"
        aria-roledescription="carousel"
        :aria-label="t('home.featured')"
        tabindex="0"
        @mouseenter="heroPaused = true"
        @mouseleave="heroPaused = false"
        @focusin="heroPaused = true"
        @focusout="heroPaused = false"
        @keydown.left.prevent="locale === 'ar' ? nextSlide() : previousSlide()"
        @keydown.right.prevent="locale === 'ar' ? previousSlide() : nextSlide()"
    >
        <!-- sm:px-20 keeps the slide content clear of the absolutely-positioned arrows. -->
        <div class="mx-auto grid max-w-7xl px-4 py-10 sm:px-20 sm:py-20 lg:py-24">
            <!-- Slides share one grid cell so the section keeps a stable height. -->
            <div
                v-for="(slide, index) in heroSlides"
                :key="slide.id"
                class="col-start-1 row-start-1 grid items-center gap-10 transition-opacity duration-700 lg:grid-cols-2"
                :class="index === activeSlide ? 'opacity-100' : 'pointer-events-none opacity-0'"
                :aria-hidden="index !== activeSlide"
                role="group"
                aria-roledescription="slide"
                :aria-label="`${index + 1} / ${heroSlides.length}`"
            >
                <div class="text-center lg:text-start">
                    <p class="text-base font-semibold text-white/40 sm:text-2xl">{{ t('home.featured') }}</p>
                    <h1 class="mt-2 text-3xl font-light leading-tight tracking-tight sm:mt-3 sm:text-7xl">
                        {{ splitName(slide.name).light }}
                        <span class="font-semibold">{{ splitName(slide.name).bold }}</span>
                    </h1>
                    <p class="mt-4 text-lg text-[#909090]">{{ t('home.hero_subtitle') }}</p>
                    <p class="mt-2 text-2xl font-light">{{ slide.current_price_formatted }}</p>
                    <Link
                        :href="route('products.show', slide.slug)"
                        class="mt-8 inline-block rounded-md border border-white px-14 py-3.5 text-sm font-medium transition hover:bg-white hover:text-black"
                        :tabindex="index === activeSlide ? 0 : -1"
                    >
                        {{ t('home.shop_now') }}
                    </Link>
                </div>
                <div class="flex items-center justify-center">
                    <img
                        v-if="slide.image"
                        :src="slide.image"
                        :alt="slide.name"
                        class="max-h-52 w-auto rounded-2xl object-contain drop-shadow-2xl sm:max-h-[420px]"
                        :loading="index === 0 ? 'eager' : 'lazy'"
                    />
                    <div v-else class="flex h-72 w-72 items-center justify-center rounded-3xl bg-white/5">
                        <Package class="h-24 w-24 text-white/20" />
                    </div>
                </div>
            </div>

            <!-- Empty state: no products at all. -->
            <div v-if="heroSlides.length === 0" class="col-start-1 row-start-1 py-10 text-center">
                <h1 class="text-5xl font-light sm:text-7xl">{{ $page.props.name }}</h1>
                <p class="mt-4 text-lg text-[#909090]">{{ t('home.hero_subtitle') }}</p>
                <Link
                    :href="route('catalog.index')"
                    class="mt-8 inline-block rounded-md border border-white px-14 py-3.5 text-sm font-medium transition hover:bg-white hover:text-black"
                >
                    {{ t('home.view_all') }}
                </Link>
            </div>
        </div>

        <template v-if="heroSlides.length > 1">
            <button
                type="button"
                class="absolute start-2 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/10 p-3 transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:block"
                :aria-label="t('home.previous_slide')"
                @click="previousSlide"
            >
                <component :is="locale === 'ar' ? ChevronRight : ChevronLeft" class="h-5 w-5" />
            </button>
            <button
                type="button"
                class="absolute end-2 top-1/2 hidden -translate-y-1/2 rounded-full bg-white/10 p-3 transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:block"
                :aria-label="t('home.next_slide')"
                @click="nextSlide"
            >
                <component :is="locale === 'ar' ? ChevronLeft : ChevronRight" class="h-5 w-5" />
            </button>

            <div class="absolute inset-x-0 bottom-6 flex justify-center gap-2">
                <button
                    v-for="(slide, index) in heroSlides"
                    :key="slide.id"
                    type="button"
                    class="h-2 rounded-full transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    :class="index === activeSlide ? 'w-8 bg-white' : 'w-2 bg-white/30 hover:bg-white/60'"
                    :aria-label="t('home.go_to_slide', { number: index + 1 })"
                    :aria-current="index === activeSlide"
                    @click="goToSlide(index)"
                />
            </div>
        </template>
    </section>

    <!-- Promo tiles: endless drifting marquee, alternating light/dark.
         The row is repeated three times and each copy slides its own full
         width, so when one copy exits the next is already in its place. -->
    <section v-if="promoTiles.length > 0" class="mx-auto max-w-7xl overflow-hidden px-4 pt-8">
        <div class="promo-marquee-wrap flex pb-2">
            <div
                v-for="copy in marqueeCopies"
                :key="copy"
                class="promo-marquee flex shrink-0 gap-4 pe-4"
                :class="{ 'promo-marquee-static': marqueeCopies === 1 }"
                :style="{ animationDuration: `${promoTiles.length * 8}s` }"
                :aria-hidden="copy > 1 ? 'true' : undefined"
            >
                <Link
                    v-for="(tile, index) in promoTiles"
                    :key="tile.id"
                    :href="route('catalog.category', tile.slug)"
                    :tabindex="copy > 1 ? -1 : undefined"
                    class="relative flex h-40 w-[300px] shrink-0 items-center gap-4 overflow-hidden rounded-2xl px-5 transition hover:opacity-95 sm:w-[380px]"
                    :class="index % 2 === 0 ? 'bg-[#E9E7E4] text-black' : 'bg-[#1C1C1C] text-white'"
                >
                    <div class="relative z-10 flex-1">
                        <h3 class="text-lg font-semibold leading-tight">{{ categoryName(tile) }}</h3>
                        <span
                            v-if="tile.max_discount > 0"
                            class="mt-3 inline-block rounded-md px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide"
                            :class="index % 2 === 0 ? 'bg-black text-white' : 'bg-white text-black'"
                        >
                            {{ t('home.up_to_off', { percent: tile.max_discount }) }}
                        </span>
                        <span v-else class="mt-3 block text-xs opacity-70">
                            {{ t('home.product_count', tile.products_count) }}
                        </span>
                    </div>
                    <img v-if="tile.image" :src="tile.image" :alt="categoryName(tile)" loading="lazy" class="h-28 w-28 rounded-xl object-cover" />
                </Link>
            </div>
        </div>
    </section>

    <!-- Categories rail -->
    <section v-if="categories.length > 0" class="mx-auto max-w-7xl px-4 pt-10">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold">{{ t('home.shop_by_category') }}</h2>
            <Link :href="route('catalog.index')" class="flex items-center gap-1 text-sm text-[#8B8B8B] transition hover:text-black dark:hover:text-white">
                {{ t('home.more') }}
                <ChevronRight class="h-4 w-4 rtl:rotate-180" />
            </Link>
        </div>
        <div ref="rail" class="flex snap-x gap-3 overflow-x-auto pb-2 lg:gap-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <Link
                v-for="category in categories"
                :key="category.id"
                :href="route('catalog.category', category.slug)"
                class="group w-28 shrink-0 snap-start text-center sm:w-36 lg:w-44"
            >
                <div
                    class="relative h-28 w-28 overflow-hidden rounded-2xl bg-neutral-900 ring-1 ring-black/5 transition group-hover:-translate-y-1 group-hover:shadow-lg dark:ring-white/10 sm:h-36 sm:w-36 lg:h-44 lg:w-44"
                >
                    <img
                        v-if="category.image"
                        :src="category.image"
                        :alt="categoryName(category)"
                        loading="lazy"
                        class="absolute inset-0 h-full w-full object-cover opacity-90 transition duration-500 group-hover:scale-105"
                    />
                    <div class="absolute inset-0 bg-black/25"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="rounded-full bg-white/15 p-2.5 backdrop-blur-sm lg:p-3.5">
                            <component :is="categoryIcon(category)" class="h-5 w-5 text-white lg:h-7 lg:w-7" />
                        </span>
                    </div>
                </div>
                <span class="mt-2 line-clamp-2 block text-xs font-medium leading-snug sm:text-sm">{{ categoryName(category) }}</span>
            </Link>
        </div>
    </section>

    <!-- Stacked product sections, each with its own "More" link -->
    <section v-for="section in productSections" :key="section.key" class="mx-auto max-w-7xl px-4 pt-10">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold">{{ section.title }}</h2>
            <Link :href="section.href" class="flex items-center gap-1 text-sm text-[#8B8B8B] transition hover:text-black dark:hover:text-white">
                {{ t('home.more') }}
                <ChevronRight class="h-4 w-4 rtl:rotate-180" />
            </Link>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-4">
            <ProductCard v-for="product in section.products" :key="product.id" :product="product" />
        </div>
    </section>

    <!-- Big sale banner -->
    <section class="mt-14 bg-gradient-to-br from-[#211C24] via-[#211C24] to-[#3a2d4a] py-24 text-center text-white">
        <div class="mx-auto max-w-3xl px-4">
            <h2 class="text-5xl font-light tracking-tight sm:text-6xl">Big Summer <span class="font-semibold">Sale</span></h2>
            <p class="mt-4 text-[#787878]">{{ t('home.on_sale') }} — {{ t('home.view_all') }}</p>
            <Link
                :href="route('catalog.index', { on_sale: 1 })"
                class="mt-10 inline-block rounded-md border border-white px-14 py-3.5 text-sm font-medium transition hover:bg-white hover:text-black"
            >
                {{ t('home.view_all') }}
            </Link>
        </div>
    </section>

    <!-- Brands -->
    <section v-if="brands.length > 0" class="mx-auto max-w-7xl overflow-hidden px-4 py-14">
        <h2 class="mb-8 text-2xl font-medium">{{ t('home.shop_by_brand') }}</h2>
        <div class="promo-marquee-wrap flex">
            <div
                v-for="copy in brandCopies"
                :key="copy"
                class="promo-marquee flex shrink-0 gap-3 pe-3"
                :class="{ 'promo-marquee-static': brandCopies === 1 }"
                :style="{ animationDuration: `${brands.length * 2.5}s` }"
                :aria-hidden="copy > 1 ? 'true' : undefined"
            >
                <Link
                    v-for="brand in brands"
                    :key="brand.id"
                    :href="route('catalog.brand', brand.slug)"
                    :tabindex="copy > 1 ? -1 : undefined"
                    class="shrink-0 whitespace-nowrap rounded-md border border-[#DBDBDB] px-6 py-2.5 text-sm font-medium transition hover:border-black hover:bg-black hover:text-white dark:border-neutral-700 dark:hover:border-white dark:hover:bg-white dark:hover:text-black"
                >
                    {{ brand.name }}
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
