<script setup lang="ts">
import CatalogFilters from '@/components/store/CatalogFilters.vue';
import EmptyState from '@/components/store/EmptyState.vue';
import Pagination from '@/components/store/Pagination.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import type { CategoryData, PaginationLink, ProductCardData } from '@/components/store/types';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowUpDown, Check, SlidersHorizontal } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface Filters {
    q?: string;
    brands?: (string | number)[];
    categories?: (string | number)[];
    min_price?: string | number;
    max_price?: string | number;
    in_stock?: string | boolean;
    on_sale?: string | boolean;
    sort?: string;
}

const props = defineProps<{
    products: {
        data: ProductCardData[];
        meta: { links: PaginationLink[]; total: number };
    };
    category: { id: number; name: string; name_ar?: string | null; slug: string; description: string | null } | null;
    childCategories: CategoryData[] | null;
    brand: { id: number; name: string; slug: string; description: string | null } | null;
    filters: Filters;
    filterOptions: {
        brands: { id: number; name: string; slug: string }[];
        categories: CategoryData[];
    };
    sortOptions: string[];
}>();

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

const showFilters = ref(false);

const form = reactive<Required<Pick<Filters, 'brands' | 'categories'>> & Filters>({
    q: props.filters.q ?? '',
    brands: (props.filters.brands ?? []).map(Number),
    categories: (props.filters.categories ?? []).map(Number),
    min_price: props.filters.min_price ?? '',
    max_price: props.filters.max_price ?? '',
    in_stock: props.filters.in_stock === '1' || props.filters.in_stock === true,
    on_sale: props.filters.on_sale === '1' || props.filters.on_sale === true,
    sort: props.filters.sort ?? 'newest',
});

const title = computed(() => {
    if (props.category) return locale.value === 'ar' && props.category.name_ar ? props.category.name_ar : props.category.name;
    if (props.brand) return props.brand.name;
    return t('nav.products');
});

const baseUrl = computed(() => {
    if (props.category) return route('catalog.category', props.category.slug);
    if (props.brand) return route('catalog.brand', props.brand.slug);
    return route('catalog.index');
});

function refresh() {
    router.get(
        baseUrl.value,
        {
            q: form.q || undefined,
            brands: form.brands.length > 0 ? form.brands : undefined,
            categories: form.categories.length > 0 ? form.categories : undefined,
            min_price: form.min_price || undefined,
            max_price: form.max_price || undefined,
            in_stock: form.in_stock ? 1 : undefined,
            on_sale: form.on_sale ? 1 : undefined,
            sort: form.sort !== 'newest' ? form.sort : undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

// Filters apply themselves: any change re-fetches after a short pause, so
// typing a price doesn't fire a request per keystroke. The mobile sheet
// stays open — results update live behind it.
let refreshTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    form,
    () => {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refresh, 400);
    },
    { deep: true },
);

onBeforeUnmount(() => clearTimeout(refreshTimer));

function clearFilters() {
    form.q = '';
    form.brands = [];
    form.categories = [];
    form.min_price = '';
    form.max_price = '';
    form.in_stock = false;
    form.on_sale = false;
    form.sort = 'newest';
}

function sortLabel(option: string): string {
    return t(`catalog.sort_${option}`);
}

function setSort(option: string) {
    form.sort = option;
}
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ title }}</h1>
        <p v-if="category?.description || brand?.description" class="mt-1 text-sm text-muted-foreground">
            {{ category?.description ?? brand?.description }}
        </p>

        <!-- Subcategory chips -->
        <div v-if="childCategories && childCategories.length > 0" class="mt-3 flex flex-wrap gap-2">
            <Link
                v-for="child in childCategories"
                :key="child.id"
                :href="route('catalog.category', child.slug)"
                class="rounded-full border border-border bg-card px-4 py-1.5 text-sm transition hover:border-black hover:opacity-70 dark:hover:border-white"
            >
                {{ locale === 'ar' && child.name_ar ? child.name_ar : child.name }}
            </Link>
        </div>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row">
        <!-- Filters: desktop sidebar -->
        <aside class="hidden shrink-0 lg:block lg:w-64">
            <div class="rounded-xl border border-border bg-card p-4">
                <CatalogFilters
                    :form="form"
                    :locale="locale"
                    :brands="brand ? null : filterOptions.brands"
                    :categories="category ? null : filterOptions.categories"
                    @clear="clearFilters"
                />
            </div>
        </aside>

        <!-- Filters: mobile bottom sheet -->
        <Sheet :open="showFilters" @update:open="showFilters = $event">
            <SheetContent side="bottom" class="max-h-[85dvh] overflow-y-auto rounded-t-3xl bg-white p-5 pt-10 dark:bg-neutral-950 lg:hidden">
                <CatalogFilters
                    :form="form"
                    :locale="locale"
                    :brands="brand ? null : filterOptions.brands"
                    :categories="category ? null : filterOptions.categories"
                    :results-count="products.meta.total"
                    @done="showFilters = false"
                    @clear="clearFilters"
                />
            </SheetContent>
        </Sheet>

        <!-- Results -->
        <div class="flex-1">
            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">{{ t('catalog.results', { count: products.meta.total }) }}</p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm lg:hidden"
                        @click="showFilters = true"
                    >
                        <SlidersHorizontal class="h-4 w-4" />
                        {{ t('catalog.filters') }}
                    </button>

                    <DropdownMenu>
                        <DropdownMenuTrigger
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm transition hover:border-black dark:hover:border-white"
                        >
                            <ArrowUpDown class="h-4 w-4 text-muted-foreground" />
                            {{ sortLabel(form.sort ?? 'newest') }}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem
                                v-for="option in sortOptions"
                                :key="option"
                                class="flex items-center justify-between gap-2"
                                @select="setSort(option)"
                            >
                                {{ sortLabel(option) }}
                                <Check v-if="(form.sort ?? 'newest') === option" class="h-4 w-4" />
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <EmptyState v-if="products.data.length === 0" :message="t('catalog.no_products')" />

            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                <ProductCard v-for="product in products.data" :key="product.id" :product="product" />
            </div>

            <Pagination :links="products.meta.links" />
        </div>
    </div>
    </div>
</template>
