<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AutoComplete, Input, Spin } from 'ant-design-vue';
import { Search } from 'lucide-vue-next';
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
const loading = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

function onSearch() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(fetchSuggestions, 300);
}

async function fetchSuggestions() {
    const term = query.value.trim();

    if (term.length < 2) {
        suggestions.value = [];

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
    } catch {
        // aborted or network error — keep the previous list
    } finally {
        loading.value = false;
    }
}

/** Enter with nothing picked runs a full catalogue search. */
function submit() {
    if (query.value.trim() !== '') {
        router.get(route('catalog.index'), { q: query.value.trim() });
    }
}

/** AntD hands back the option's `value`, which is the product slug. */
function select(slug: unknown) {
    query.value = '';
    suggestions.value = [];
    router.get(route('products.show', String(slug)));
}

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <AutoComplete
        v-model:value="query"
        role="search"
        :options="suggestions.map((product) => ({ value: product.slug, product }))"
        :default-active-first-option="false"
        class="store-search w-full"
        popup-class-name="store-search-panel"
        @search="onSearch"
        @select="select"
    >
        <!-- A plain `Input`, not `Input.Search`: its attached button would be a
             second, dead search control beside the debounced suggestions —
             the same call the dashboard's FilterBar makes. -->
        <Input
            :placeholder="t('nav.search_placeholder')"
            size="large"
            type="search"
            name="q"
            autocomplete="off"
            spellcheck="false"
            :aria-label="t('common.search')"
            @press-enter="submit"
        >
            <template #prefix>
                <Spin v-if="loading" size="small" />
                <Search v-else class="h-4 w-4 opacity-45" />
            </template>
        </Input>

        <template #option="{ product }">
            <div class="store-search-option">
                <img v-if="product.image" :src="product.image" :alt="product.name" width="40" height="40" loading="lazy" class="store-search-thumb" />
                <div v-else class="store-search-thumb store-search-thumb-empty" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ product.name }}</p>
                    <p v-if="product.brand" class="truncate text-xs opacity-60">{{ product.brand }}</p>
                </div>
                <span class="shrink-0 text-sm font-semibold">{{ product.price_formatted }}</span>
            </div>
        </template>
    </AutoComplete>
</template>
