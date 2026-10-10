<script setup lang="ts">
import RatingStars from '@/components/store/RatingStars.vue';
import type { HeroSlide } from '@/components/store/types';
import { Link } from '@inertiajs/vue3';
import { Button } from 'ant-design-vue';
import { ArrowLeft, ArrowRight, ChevronLeft, ChevronRight, Package, Pause, Play, Sparkles } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The homepage hero: one featured product at a time, sold properly — photo,
 * brand, price and saving, rating and stock, one clear call to action.
 *
 * Replaces the AntD carousel, which hid the product photo below `lg` (so on a
 * phone the hero sold a product you couldn't see), repeated one tagline on
 * every slide, and had dots too faint to find. This one is a small custom
 * slider because it needs things AntD's doesn't do well: RTL-aware swipe and
 * arrows, a progress indicator per slide, and a real pause control.
 *
 * Slides share one grid cell, so the frame is as tall as the tallest slide
 * and nothing jumps when they change.
 */

const props = defineProps<{ slides: HeroSlide[] }>();

const { t, locale } = useI18n();
const rtl = computed(() => locale.value === 'ar');

const ROTATE_MS = 6000;
const prefersReducedMotion = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const current = ref(0);
/** The user's own choice — pausing sticks until they press play. */
const userPaused = ref(prefersReducedMotion);
/** Hover or keyboard focus inside the hero holds the slide while it is being read. */
const holding = ref(false);
const playing = computed(() => props.slides.length > 1 && !userPaused.value && !holding.value);

let timer: ReturnType<typeof setTimeout> | null = null;

function schedule() {
    if (timer) clearTimeout(timer);
    timer = null;
    if (playing.value) timer = setTimeout(() => go(current.value + 1), ROTATE_MS);
}

function go(index: number) {
    const count = props.slides.length;
    if (count === 0) return;
    current.value = (index + count) % count;
    schedule();
}

const next = () => go(current.value + 1);
const previous = () => go(current.value - 1);

watch(playing, schedule);
watch(
    () => props.slides.length,
    () => {
        if (current.value >= props.slides.length) current.value = 0;
        schedule();
    },
);

function onVisibility() {
    holding.value = document.hidden;
}

onMounted(() => {
    schedule();
    document.addEventListener('visibilitychange', onVisibility);
});

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
    document.removeEventListener('visibilitychange', onVisibility);
});

/** Arrow keys follow reading direction: in Arabic, "next" is to the left. */
function onKeydown(event: KeyboardEvent) {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;

    event.preventDefault();
    const forward = (event.key === 'ArrowRight') !== rtl.value;

    if (forward) next();
    else previous();
}

/** Horizontal swipe on touch screens, mirrored for RTL like the arrows. */
let swipeStartX: number | null = null;

function onPointerDown(event: PointerEvent) {
    if (event.pointerType !== 'mouse') swipeStartX = event.clientX;
}

function onPointerUp(event: PointerEvent) {
    if (swipeStartX === null) return;
    const dx = event.clientX - swipeStartX;
    swipeStartX = null;
    if (Math.abs(dx) < 40) return;
    // Swiping the slide towards the start edge brings in the next one.
    const towardsStart = rtl.value ? dx > 0 : dx < 0;

    if (towardsStart) next();
    else previous();
}

/** The dashboard's headline in the reader's language, falling back to the product name. */
function headline(slide: HeroSlide): string {
    const custom = rtl.value ? slide.title_ar || slide.title : slide.title;

    return custom || slide.product.name;
}

const counter = computed(() => `${String(current.value + 1).padStart(2, '0')} / ${String(props.slides.length).padStart(2, '0')}`);
</script>

<template>
    <div
        class="store-hero"
        role="region"
        aria-roledescription="carousel"
        :aria-label="t('home.featured')"
        @mouseenter="holding = true"
        @mouseleave="holding = false"
        @focusin="holding = true"
        @focusout="holding = false"
        @keydown="onKeydown"
        @pointerdown="onPointerDown"
        @pointerup="onPointerUp"
    >
        <div class="store-hero-stack" aria-live="off">
            <article
                v-for="(slide, index) in slides"
                :key="slide.product.id"
                :class="['store-hero-slide', { 'store-hero-slide-on': index === current }]"
                role="group"
                aria-roledescription="slide"
                :aria-label="`${index + 1} / ${slides.length}`"
                :aria-hidden="index !== current"
                :inert="index !== current || undefined"
            >
                <!-- Photo: first on a phone, trailing on desktop -->
                <Link :href="route('products.show', slide.product.slug)" class="store-hero-media" tabindex="-1" aria-hidden="true">
                    <span class="store-hero-spot" />
                    <!-- The first slide is the page's LCP element; the rest wait. -->
                    <img
                        v-if="slide.image || slide.product.image"
                        :src="(slide.image ?? slide.product.image)!"
                        alt=""
                        width="512"
                        height="512"
                        class="store-hero-img"
                        :loading="index === 0 ? 'eager' : 'lazy'"
                        :fetchpriority="index === 0 ? 'high' : undefined"
                        decoding="async"
                    />
                    <Package v-else class="relative h-20 w-20 opacity-25" />
                </Link>

                <div class="store-hero-copy">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="store-hero-chip">
                            <Sparkles class="h-3.5 w-3.5" />
                            {{ t('home.featured_badge') }}
                        </span>
                        <span v-if="slide.product.brand" class="store-hero-brand">{{ slide.product.brand.name }}</span>
                        <span v-if="slide.product.on_sale && slide.product.discount_percent" class="store-hero-off">
                            {{ t('product.percent_off', { percent: slide.product.discount_percent }) }}
                        </span>
                    </div>

                    <h2 class="store-hero-title">{{ headline(slide) }}</h2>

                    <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span class="store-hero-price">{{ slide.product.current_price_formatted }}</span>
                        <span v-if="slide.product.on_sale" class="store-hero-was">{{ slide.product.price_formatted }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                        <RatingStars v-if="slide.product.rating_count > 0" :rating="slide.product.rating_avg" :count="slide.product.rating_count" />
                        <span v-if="slide.product.in_stock" class="store-hero-stock">{{ t('product.in_stock') }}</span>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <Link :href="route('products.show', slide.product.slug)">
                            <Button type="primary" size="large" class="store-hero-cta">
                                <span class="flex items-center gap-2">
                                    {{ t('home.shop_now') }}
                                    <component :is="rtl ? ArrowLeft : ArrowRight" class="h-4 w-4" />
                                </span>
                            </Button>
                        </Link>
                        <Link :href="`${route('catalog.index')}?on_sale=1`" class="store-hero-secondary">{{ t('home.browse_deals') }}</Link>
                    </div>
                </div>
            </article>
        </div>

        <!-- Controls: progress per slide, a counter, pause, and small arrows — kept
             in one bar along the bottom instead of floating over the product. -->
        <div v-if="slides.length > 1" class="store-hero-controls">
            <div class="flex flex-1 items-center gap-1.5">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.product.id"
                    type="button"
                    :class="['store-hero-bar', { 'store-hero-bar-on': index === current, 'store-hero-bar-done': index < current }]"
                    :aria-label="t('home.go_to_slide', { n: index + 1 })"
                    :aria-current="index === current ? 'true' : undefined"
                    @click="go(index)"
                >
                    <span
                        v-if="index === current"
                        :key="`${current}-${playing}`"
                        class="store-hero-bar-fill"
                        :class="{ 'store-hero-bar-fill-run': playing }"
                        :style="{ animationDuration: `${ROTATE_MS}ms` }"
                    />
                </button>
            </div>

            <span class="store-hero-counter" aria-hidden="true">{{ counter }}</span>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="store-hero-btn"
                    :aria-label="userPaused ? t('home.play_slides') : t('home.pause_slides')"
                    @click="userPaused = !userPaused"
                >
                    <Play v-if="userPaused" class="h-3.5 w-3.5" />
                    <Pause v-else class="h-3.5 w-3.5" />
                </button>
                <button type="button" class="store-hero-btn" :aria-label="t('home.previous_slide')" @click="previous">
                    <component :is="rtl ? ChevronRight : ChevronLeft" class="h-4 w-4" />
                </button>
                <button type="button" class="store-hero-btn" :aria-label="t('home.next_slide')" @click="next">
                    <component :is="rtl ? ChevronLeft : ChevronRight" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
