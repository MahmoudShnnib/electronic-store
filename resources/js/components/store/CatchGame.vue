<script setup lang="ts">
import { Gamepad2, Heart, RotateCcw, ShoppingCart, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/** Product photos used as the falling items. */
const props = defineProps<{ images: string[] }>();

const { t } = useI18n();

const open = ref(false);
const running = ref(false);
const gameOver = ref(false);
const score = ref(0);
const lives = ref(3);
const best = ref(Number(localStorage.getItem('catch_game_best') ?? 0));

interface Falling {
    id: number;
    x: number; // % of arena width
    y: number; // px from top
    speed: number; // px per second
    img: string;
    caught: boolean;
    rot: number; // current rotation, deg
    spin: number; // deg per second
    caughtAt: number; // seconds since catch, drives the pop animation
}

/** Floating "+1" markers left behind at each catch point. */
interface Popper {
    id: number;
    x: number;
    y: number;
}

const items = ref<Falling[]>([]);
const poppers = ref<Popper[]>([]);
const basketX = ref(50); // rendered position, % of arena width
const basketTarget = ref(50); // where the pointer wants it — the spring chases this
const basketTilt = ref(0); // deg, leans into the direction of travel
const basketPop = ref(false); // squash-and-stretch pulse on catch
const arenaShake = ref(false); // jolt when a life is lost
const arena = ref<HTMLElement | null>(null);

let raf = 0;
let lastTime = 0;
let sinceSpawn = 0;
let nextId = 1;
let popTimer = 0;
let shakeTimer = 0;

function pulseBasket() {
    basketPop.value = false;
    clearTimeout(popTimer);
    // Next frame, so the CSS animation retriggers on rapid catches.
    requestAnimationFrame(() => (basketPop.value = true));
    popTimer = window.setTimeout(() => (basketPop.value = false), 300);
}

function shakeArena() {
    arenaShake.value = false;
    clearTimeout(shakeTimer);
    requestAnimationFrame(() => (arenaShake.value = true));
    shakeTimer = window.setTimeout(() => (arenaShake.value = false), 450);
}

const BASKET_HALF = 9; // catch tolerance, % of arena width

/** Fixed drift slots so the teaser looks composed, not random. */
const TEASER_SLOTS = [
    { x: '6%', y: '8%' },
    { x: '30%', y: '55%' },
    { x: '55%', y: '10%' },
    { x: '72%', y: '48%' },
    { x: '88%', y: '18%' },
];

/** Cutout PNGs float like real objects; photos with baked-in backgrounds
 *  read as rectangles, so only use them when nothing better exists. */
const floatables = computed(() => {
    const cutouts = props.images.filter((src) => src.endsWith('.png'));

    return cutouts.length >= 3 ? cutouts : props.images;
});

const teaserItems = computed(() =>
    TEASER_SLOTS.filter((_, i) => i < floatables.value.length).map((slot, i) => ({
        src: floatables.value[i % floatables.value.length],
        x: slot.x,
        y: slot.y,
        delay: `${i * 0.9}s`,
        duration: `${5 + (i % 3)}s`,
    })),
);

const spawnEvery = computed(() => Math.max(0.45, 1.3 - score.value * 0.02));
const fallSpeed = computed(() => 130 + score.value * 6);

function start() {
    score.value = 0;
    lives.value = 3;
    items.value = [];
    poppers.value = [];
    gameOver.value = false;
    running.value = true;
    lastTime = 0;
    sinceSpawn = 0;
    basketX.value = 50;
    basketTarget.value = 50;
    basketTilt.value = 0;
    raf = requestAnimationFrame(tick);

    // The arena owns the arrow keys, so it has to hold focus for them to arrive.
    arena.value?.focus();
}

function stopLoop() {
    cancelAnimationFrame(raf);
    running.value = false;
}

function endGame() {
    stopLoop();
    gameOver.value = true;

    if (score.value > best.value) {
        best.value = score.value;
        localStorage.setItem('catch_game_best', String(best.value));
    }
}

function tick(now: number) {
    if (!running.value) {
        return;
    }

    const dt = lastTime === 0 ? 0 : Math.min(0.05, (now - lastTime) / 1000);
    lastTime = now;

    sinceSpawn += dt;

    // The basket is a critically-damped spring chasing the pointer, and it
    // leans into its own velocity — that lag + tilt is most of the "alive".
    const delta = basketTarget.value - basketX.value;
    basketX.value += delta * Math.min(1, dt * 16);
    basketTilt.value = Math.max(-16, Math.min(16, delta * 1.4));

    if (sinceSpawn >= spawnEvery.value && props.images.length > 0) {
        sinceSpawn = 0;
        items.value.push({
            id: nextId++,
            x: 8 + Math.random() * 84,
            y: -48,
            speed: fallSpeed.value * (0.85 + Math.random() * 0.3),
            img: props.images[Math.floor(Math.random() * props.images.length)],
            caught: false,
            rot: -20 + Math.random() * 40,
            spin: (Math.random() < 0.5 ? -1 : 1) * (40 + Math.random() * 120),
            caughtAt: 0,
        });
    }

    const height = arena.value?.clientHeight ?? 400;
    const catchLine = height - 74;

    for (const item of items.value) {
        if (item.caught) {
            item.caughtAt += dt;
            continue;
        }

        item.y += item.speed * dt;
        item.rot += item.spin * dt;

        if (item.y >= catchLine && item.y <= catchLine + 40 && Math.abs(item.x - basketX.value) <= BASKET_HALF) {
            item.caught = true;
            score.value++;
            pulseBasket();

            const popId = nextId++;
            poppers.value.push({ id: popId, x: item.x, y: item.y });
            setTimeout(() => (poppers.value = poppers.value.filter((p) => p.id !== popId)), 700);
        }
    }

    const missed = items.value.filter((i) => !i.caught && i.y > height).length;

    if (missed > 0) {
        lives.value -= missed;
        shakeArena();

        if (lives.value <= 0) {
            lives.value = 0;
            endGame();
        }
    }

    // Caught items linger ~0.3s so their pop animation can play out.
    items.value = items.value.filter((i) => (i.caught ? i.caughtAt < 0.3 : i.y <= height));

    if (running.value) {
        raf = requestAnimationFrame(tick);
    }
}

function moveTo(clientX: number) {
    const rect = arena.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    basketTarget.value = Math.min(96, Math.max(4, ((clientX - rect.left) / rect.width) * 100));
}

function onPointerMove(event: PointerEvent) {
    moveTo(event.clientX);
}

function onTouchMove(event: TouchEvent) {
    event.preventDefault();
    moveTo(event.touches[0].clientX);
}

function onKeydown(event: KeyboardEvent) {
    if (!running.value) {
        return;
    }

    // Screen-relative so it feels right in RTL too. `preventDefault` keeps the
    // page from scrolling sideways underneath the game while it has the keys.
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        basketTarget.value = Math.max(4, basketTarget.value - 7);
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        basketTarget.value = Math.min(96, basketTarget.value + 7);
    }
}

function toggleOpen() {
    open.value = !open.value;

    if (!open.value) {
        stopLoop();
        gameOver.value = false;
    }
}

onBeforeUnmount(() => stopLoop());
</script>

<template>
    <section class="mx-auto max-w-7xl px-4 pt-14">
        <!-- Teaser: a slice of the game itself, products drifting behind the pitch -->
        <button
            v-if="!open"
            type="button"
            class="group relative block w-full overflow-hidden rounded-3xl border border-st-accent/25 bg-gradient-to-l from-st-accent/15 via-card to-card p-6 text-start transition hover:border-st-accent/60 hover:shadow-xl hover:shadow-st-accent/10 sm:p-9"
            @click="toggleOpen"
        >
            <!-- Drifting catalogue items -->
            <img
                v-for="(img, i) in teaserItems"
                :key="i"
                :src="img.src"
                alt=""
                aria-hidden="true"
                width="64"
                height="64"
                class="game-float pointer-events-none absolute h-12 w-12 object-contain opacity-40 transition group-hover:opacity-70 sm:h-16 sm:w-16"
                :class="{ 'hidden sm:block': i > 2 }"
                :style="{ insetInlineStart: img.x, top: img.y, animationDelay: img.delay, animationDuration: img.duration }"
            />

            <!-- Dotted catch line -->
            <span
                class="pointer-events-none absolute inset-x-8 bottom-6 hidden border-t-2 border-dashed border-st-accent/30 sm:block"
                aria-hidden="true"
            />

            <div class="relative z-10 flex flex-wrap items-center justify-between gap-6">
                <span class="flex items-center gap-5">
                    <span class="game-wiggle rounded-2xl bg-st-accent p-3.5 text-st-on-accent shadow-lg shadow-st-accent/40">
                        <Gamepad2 class="h-8 w-8" />
                    </span>
                    <span>
                        <span
                            class="block bg-gradient-to-l from-st-accent to-foreground bg-clip-text text-2xl font-extrabold text-transparent sm:text-3xl"
                        >
                            {{ t('home.game_title') }}
                        </span>
                        <span class="mt-1.5 block max-w-md text-sm text-muted-foreground">{{ t('home.game_subtitle') }}</span>
                        <span
                            v-if="best > 0"
                            class="mt-2.5 inline-flex items-center gap-1.5 rounded-full bg-st-accent/10 px-3 py-1 text-xs font-bold text-st-accent-text"
                        >
                            🏆 {{ t('home.game_best', { best }) }}
                        </span>
                    </span>
                </span>

                <span class="relative shrink-0">
                    <span class="game-glow absolute inset-0 rounded-2xl bg-st-accent" aria-hidden="true" />
                    <span
                        class="relative flex items-center gap-2 rounded-2xl bg-st-accent px-8 py-3.5 text-base font-bold text-st-on-accent transition group-hover:scale-105"
                    >
                        ▶ {{ t('home.game_play') }}
                    </span>
                </span>
            </div>
        </button>

        <!-- Game -->
        <div v-else class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div class="flex items-center gap-2 font-semibold">
                    <Gamepad2 class="h-5 w-5 text-st-accent-text" />
                    {{ t('home.game_title') }}
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span :key="score" class="game-score-pop inline-block font-semibold text-st-accent-text">{{
                        t('home.game_score', { score })
                    }}</span>
                    <span class="text-muted-foreground">{{ t('home.game_best', { best }) }}</span>
                    <span class="flex items-center gap-0.5" aria-hidden="true">
                        <Heart v-for="n in 3" :key="n" class="h-4 w-4" :class="n <= lives ? 'fill-red-500 text-red-500' : 'text-border'" />
                    </span>
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-muted-foreground hover:bg-muted"
                        :aria-label="t('common.close')"
                        @click="toggleOpen"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- The arena itself is focusable and owns the arrow keys: the basket is a
                 pointer gesture, so the keyboard alternative has to live on a control a
                 keyboard user can actually reach, not only on a window listener. -->
            <div
                ref="arena"
                class="relative h-96 touch-none select-none overflow-hidden bg-gradient-to-b from-transparent to-st-accent/5 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-st-accent"
                :class="{ 'game-shake': arenaShake }"
                tabindex="0"
                role="application"
                :aria-label="t('home.game_help')"
                @pointermove="onPointerMove"
                @touchmove="onTouchMove"
                @keydown="onKeydown"
            >
                <!-- Falling products: they tumble on the way down and pop when caught -->
                <img
                    v-for="item in items"
                    :key="item.id"
                    :src="item.img"
                    alt=""
                    width="48"
                    height="48"
                    class="pointer-events-none absolute h-12 w-12 object-contain drop-shadow"
                    :class="{ 'game-item-pop': item.caught }"
                    :style="{ left: item.x + '%', top: item.y + 'px', transform: `translateX(-50%) rotate(${item.rot}deg)` }"
                />

                <!-- "+1" left behind at each catch -->
                <span
                    v-for="pop in poppers"
                    :key="pop.id"
                    class="game-plus-pop pointer-events-none absolute text-lg font-extrabold text-st-accent-text"
                    :style="{ left: pop.x + '%', top: pop.y + 'px' }"
                    aria-hidden="true"
                >
                    +1
                </span>

                <!-- Basket: springs after the pointer, leans into the turn, squashes on catch -->
                <div
                    class="absolute bottom-4 flex h-11 w-[18%] min-w-20 items-center justify-center rounded-xl bg-black text-white shadow-lg dark:bg-white dark:text-black"
                    :class="{ 'game-basket-pop': basketPop }"
                    :style="{ left: basketX + '%', transform: `translateX(-50%) rotate(${basketTilt}deg)` }"
                >
                    <ShoppingCart class="h-5 w-5" />
                </div>

                <!-- Start / game over overlay -->
                <div v-if="!running" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-card/85 backdrop-blur-sm">
                    <div class="game-overlay-in flex flex-col items-center gap-4">
                        <template v-if="gameOver">
                            <p class="text-2xl font-bold">{{ t('home.game_over') }}</p>
                            <p class="text-lg">
                                {{ t('home.game_score', { score }) }}
                                <span v-if="score >= best && score > 0" class="ms-1 font-semibold text-st-accent-text">🏆</span>
                            </p>
                        </template>
                        <p v-else class="max-w-xs text-center text-sm text-muted-foreground">{{ t('home.game_help') }}</p>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-st-accent px-6 py-3 font-semibold text-st-on-accent transition hover:scale-105 hover:opacity-90"
                            @click="start"
                        >
                            <RotateCcw v-if="gameOver" class="h-4 w-4" />
                            {{ gameOver ? t('home.game_play_again') : t('home.game_play') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style>
@keyframes game-float {
    0%,
    100% {
        transform: translateY(0) rotate(-4deg);
    }
    50% {
        transform: translateY(-14px) rotate(5deg);
    }
}

@keyframes game-wiggle {
    0%,
    100% {
        transform: rotate(-6deg);
    }
    50% {
        transform: rotate(8deg);
    }
}

@keyframes game-glow {
    0%,
    100% {
        opacity: 0.55;
        transform: scale(1);
    }
    50% {
        opacity: 0;
        transform: scale(1.18);
    }
}

.game-float {
    animation: game-float 6s ease-in-out infinite;
}

.game-wiggle {
    animation: game-wiggle 2.6s ease-in-out infinite;
}

.game-glow {
    animation: game-glow 2s ease-out infinite;
}

/* Catch: the item bursts, the basket squashes, a +1 floats away. */
@keyframes game-item-pop {
    0% {
        opacity: 1;
        scale: 1;
    }
    100% {
        opacity: 0;
        scale: 1.7;
    }
}

.game-item-pop {
    animation: game-item-pop 0.3s ease-out forwards;
}

@keyframes game-plus-pop {
    0% {
        opacity: 0;
        translate: -50% 0;
        scale: 0.6;
    }
    25% {
        opacity: 1;
        scale: 1.25;
    }
    100% {
        opacity: 0;
        translate: -50% -46px;
        scale: 1;
    }
}

.game-plus-pop {
    animation: game-plus-pop 0.7s cubic-bezier(0.22, 1.4, 0.36, 1) forwards;
}

/* Squash-and-stretch runs on `scale` so it composes with the inline
   translate/rotate transform instead of overwriting it. */
@keyframes game-basket-pop {
    0% {
        scale: 1 1;
    }
    35% {
        scale: 1.28 0.7;
    }
    70% {
        scale: 0.92 1.12;
    }
    100% {
        scale: 1 1;
    }
}

.game-basket-pop {
    animation: game-basket-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes game-shake {
    0%,
    100% {
        translate: 0 0;
    }
    20% {
        translate: -7px 0;
    }
    40% {
        translate: 6px 0;
    }
    60% {
        translate: -4px 0;
    }
    80% {
        translate: 3px 0;
    }
}

.game-shake {
    animation: game-shake 0.45s ease-out;
}

@keyframes game-score-pop {
    0% {
        scale: 1.5;
    }
    100% {
        scale: 1;
    }
}

.game-score-pop {
    animation: game-score-pop 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes game-overlay-in {
    0% {
        opacity: 0;
        scale: 0.8;
        translate: 0 12px;
    }
    100% {
        opacity: 1;
        scale: 1;
        translate: 0 0;
    }
}

.game-overlay-in {
    animation: game-overlay-in 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@media (prefers-reduced-motion: reduce) {
    .game-float,
    .game-wiggle,
    .game-glow,
    .game-item-pop,
    .game-plus-pop,
    .game-basket-pop,
    .game-shake,
    .game-score-pop,
    .game-overlay-in {
        animation: none;
    }
}
</style>
