<script setup lang="ts">
/**
 * Product search select for the page builder — one product (a hero slide) or
 * an ordered list (a manual carousel). Searches through the Homepage screen's
 * picker endpoint; products already chosen stay labelled through `known`.
 */
import { usePage } from '@inertiajs/vue3';
import { Select, Spin } from 'ant-design-vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ProductSummary } from './types';

const props = defineProps<{
    value: number | number[] | null | undefined;
    multiple?: boolean;
    max?: number;
    known: Record<number, ProductSummary>;
}>();

const emit = defineEmits<{
    (e: 'update:value', value: number | number[] | null): void;
    (e: 'picked', products: ProductSummary[]): void;
}>();

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

const results = ref<ProductSummary[]>([]);
const searching = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let abort: AbortController | null = null;

function search(term: string): void {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => run(term), 250);
}

async function run(term: string): Promise<void> {
    abort?.abort();
    abort = new AbortController();
    searching.value = true;

    try {
        const response = await fetch(`${route('admin.homepage.products')}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: abort.signal,
        });
        results.value = ((await response.json()) as { products: ProductSummary[] }).products;
        emit('picked', results.value);
    } catch {
        // aborted by a newer keystroke, or offline — keep the last list
    } finally {
        searching.value = false;
    }
}

function label(product: ProductSummary): string {
    return `${locale.value === 'ar' && product.name_ar ? product.name_ar : product.name} · ${product.sku}`;
}

const selected = computed<number[]>(() => (Array.isArray(props.value) ? props.value : props.value ? [props.value] : []));

/** Chosen products always stay options, so the select can show their labels. */
const options = computed(() => {
    const pool = new Map<number, ProductSummary>();
    selected.value.forEach((id) => props.known[id] && pool.set(id, props.known[id]));
    results.value.forEach((product) => pool.set(product.id, product));

    return [...pool.values()].map((product) => ({
        value: product.id,
        label: label(product) + (product.visible ? '' : ` — ${t('admin.pages.not_in_store')}`),
    }));
});

function change(value: unknown): void {
    if (props.multiple) {
        const ids = (value as unknown[]).map(Number);
        emit('update:value', props.max ? ids.slice(0, props.max) : ids);
    } else {
        emit('update:value', value === undefined || value === null ? null : Number(value));
    }
}
</script>

<template>
    <Select
        :value="multiple ? selected : (selected[0] ?? undefined)"
        :mode="multiple ? 'multiple' : undefined"
        show-search
        :filter-option="false"
        :options="options"
        :placeholder="t('admin.homepage.search_product')"
        class="w-full"
        @search="search"
        @focus="results.length === 0 && run('')"
        @change="change"
    >
        <template v-if="searching" #notFoundContent><Spin size="small" /></template>
    </Select>
</template>
