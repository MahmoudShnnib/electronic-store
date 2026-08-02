<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2, Search } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Suggestion {
    id: number;
    name: string;
    slug: string;
    brand: string | null;
    price_formatted: string;
    image: string | null;
}

const { t } = useI18n();

const query = ref('');
const suggestions = ref<Suggestion[]>([]);
const open = ref(false);
const loading = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

function onInput() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(fetchSuggestions, 300);
}

async function fetchSuggestions() {
    const term = query.value.trim();

    if (term.length < 2) {
        suggestions.value = [];
        open.value = false;
        return;
    }

    controller?.abort();
    controller = new AbortController();
    loading.value = true;

    try {
        const response = await fetch(`${route('search.suggest')}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        const json = (await response.json()) as { products: Suggestion[] };
        suggestions.value = json.products;
        open.value = true;
    } catch {
        // aborted or network error — keep the previous list
    } finally {
        loading.value = false;
    }
}

function submit() {
    open.value = false;
    if (query.value.trim() !== '') {
        router.get(route('catalog.index'), { q: query.value.trim() });
    }
}

function select(product: Suggestion) {
    open.value = false;
    query.value = '';
    router.get(route('products.show', product.slug));
}

function close() {
    // Delay so click events on options land first.
    setTimeout(() => (open.value = false), 150);
}

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div class="relative w-full">
        <form class="relative" role="search" @submit.prevent="submit">
            <input
                v-model="query"
                type="search"
                :placeholder="t('nav.search_placeholder')"
                class="h-10 w-full rounded-lg border border-input bg-background pe-10 ps-4 text-sm focus:border-black focus:outline-none focus:ring-2 focus:ring-black/15 dark:focus:border-white dark:focus:ring-white/25"
                @input="onInput"
                @focus="suggestions.length > 0 && (open = true)"
                @blur="close"
            />
            <button
                type="submit"
                class="absolute top-0 flex h-10 w-10 items-center justify-center text-muted-foreground hover:text-foreground ltr:right-0 rtl:left-0"
                :aria-label="t('common.search')"
            >
                <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                <Search v-else class="h-4 w-4" />
            </button>
        </form>

        <div
            v-if="open && suggestions.length > 0"
            class="absolute z-50 mt-2 w-full overflow-hidden rounded-lg border border-border bg-popover shadow-xl"
        >
            <button
                v-for="product in suggestions"
                :key="product.id"
                type="button"
                class="flex w-full items-center gap-3 px-3 py-2 text-start transition hover:bg-muted"
                @mousedown.prevent="select(product)"
            >
                <img v-if="product.image" :src="product.image" :alt="product.name" class="h-10 w-10 rounded object-contain" />
                <div v-else class="h-10 w-10 rounded bg-muted" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ product.name }}</p>
                    <p v-if="product.brand" class="text-xs text-muted-foreground">{{ product.brand }}</p>
                </div>
                <span class="text-sm font-semibold text-black dark:text-white">{{ product.price_formatted }}</span>
            </button>
        </div>
    </div>
</template>
