<script setup lang="ts">
import { Gamepad2, Heart, RotateCcw, ShoppingCart, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
}

const items = ref<Falling[]>([]);
const basketX = ref(50); // % of arena width
const arena = ref<HTMLElement | null>(null);

let raf = 0;
let lastTime = 0;
let sinceSpawn = 0;
let nextId = 1;

const BASKET_HALF = 9; // catch tolerance, % of arena width

const spawnEvery = computed(() => Math.max(0.45, 1.3 - score.value * 0.02));
const fallSpeed = computed(() => 130 + score.value * 6);

function start() {
    score.value = 0;
    lives.value = 3;
    items.value = [];
    gameOver.value = false;
    running.value = true;
    lastTime = 0;
    sinceSpawn = 0;
    raf = requestAnimationFrame(tick);
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

    if (sinceSpawn >= spawnEvery.value && props.images.length > 0) {
        sinceSpawn = 0;
        items.value.push({
            id: nextId++,
            x: 8 + Math.random() * 84,
            y: -48,
            speed: fallSpeed.value * (0.85 + Math.random() * 0.3),
            img: props.images[Math.floor(Math.random() * props.images.length)],
            caught: false,
        });
    }

    const height = arena.value?.clientHeight ?? 400;
    const catchLine = height - 74;

    for (const item of items.value) {
        item.y += item.speed * dt;

        if (!item.caught && item.y >= catchLine && item.y <= catchLine + 40 && Math.abs(item.x - basketX.value) <= BASKET_HALF) {
            item.caught = true;
            score.value++;
        }
    }

    const missed = items.value.filter((i) => !i.caught && i.y > height).length;

    if (missed > 0) {
        lives.value -= missed;

        if (lives.value <= 0) {
            lives.value = 0;
            endGame();
        }
    }

    items.value = items.value.filter((i) => !i.caught && i.y <= height);

    if (running.value) {
        raf = requestAnimationFrame(tick);
    }
}

function moveTo(clientX: number) {
    const rect = arena.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    basketX.value = Math.min(96, Math.max(4, ((clientX - rect.left) / rect.width) * 100));
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

    // Screen-relative so it feels right in RTL too.
    if (event.key === 'ArrowLeft') {
        basketX.value = Math.max(4, basketX.value - 5);
    } else if (event.key === 'ArrowRight') {
        basketX.value = Math.min(96, basketX.value + 5);
    }
}

function toggleOpen() {
    open.value = !open.value;

    if (!open.value) {
        stopLoop();
        gameOver.value = false;
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
    stopLoop();
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <section class="mx-auto max-w-7xl px-4 pt-14">
        <!-- Teaser -->
        <button
            v-if="!open"
            type="button"
            class="group flex w-full items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5 text-start transition hover:shadow-lg"
            @click="toggleOpen"
        >
            <span class="flex items-center gap-4">
                <span class="rounded-xl bg-[#0F9D58]/10 p-3 text-[#0F9D58]">
                    <Gamepad2 class="h-7 w-7" />
                </span>
                <span>
                    <span class="block text-lg font-semibold">{{ t('home.game_title') }}</span>
                    <span class="mt-0.5 block text-sm text-muted-foreground">{{ t('home.game_subtitle') }}</span>
                </span>
            </span>
            <span
                class="shrink-0 rounded-xl bg-black px-5 py-2.5 text-sm font-semibold text-white transition group-hover:opacity-85 dark:bg-white dark:text-black"
            >
                {{ t('home.game_play') }}
            </span>
        </button>

        <!-- Game -->
        <div v-else class="overflow-hidden rounded-2xl border border-border bg-card">
            <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div class="flex items-center gap-2 font-semibold">
                    <Gamepad2 class="h-5 w-5 text-[#0F9D58]" />
                    {{ t('home.game_title') }}
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="font-semibold text-[#0F9D58]">{{ t('home.game_score', { score }) }}</span>
                    <span class="text-muted-foreground">{{ t('home.game_best', { best }) }}</span>
                    <span class="flex items-center gap-0.5" aria-hidden="true">
                        <Heart v-for="n in 3" :key="n" class="h-4 w-4" :class="n <= lives ? 'fill-red-500 text-red-500' : 'text-border'" />
                    </span>
                    <button type="button" class="rounded-md p-1.5 text-muted-foreground hover:bg-muted" :aria-label="t('common.close')" @click="toggleOpen">
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div
                ref="arena"
                class="relative h-96 touch-none select-none overflow-hidden bg-gradient-to-b from-transparent to-[#0F9D58]/5"
                @pointermove="onPointerMove"
                @touchmove="onTouchMove"
            >
                <!-- Falling products -->
                <img
                    v-for="item in items"
                    :key="item.id"
                    :src="item.img"
                    alt=""
                    class="pointer-events-none absolute h-12 w-12 -translate-x-1/2 object-contain drop-shadow"
                    :style="{ left: item.x + '%', top: item.y + 'px' }"
                />

                <!-- Basket -->
                <div
                    class="absolute bottom-4 flex h-11 w-[18%] min-w-20 -translate-x-1/2 items-center justify-center rounded-xl bg-black text-white shadow-lg transition-none dark:bg-white dark:text-black"
                    :style="{ left: basketX + '%' }"
                >
                    <ShoppingCart class="h-5 w-5" />
                </div>

                <!-- Start / game over overlay -->
                <div v-if="!running" class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-card/85 backdrop-blur-sm">
                    <template v-if="gameOver">
                        <p class="text-2xl font-bold">{{ t('home.game_over') }}</p>
                        <p class="text-lg">
                            {{ t('home.game_score', { score }) }}
                            <span v-if="score >= best && score > 0" class="ms-1 font-semibold text-[#0F9D58]">🏆</span>
                        </p>
                    </template>
                    <p v-else class="max-w-xs text-center text-sm text-muted-foreground">{{ t('home.game_help') }}</p>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#0F9D58] px-6 py-3 font-semibold text-white transition hover:opacity-85"
                        @click="start"
                    >
                        <RotateCcw v-if="gameOver" class="h-4 w-4" />
                        {{ gameOver ? t('home.game_play_again') : t('home.game_play') }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
