<script setup lang="ts">
import type { CategoryData } from '@/components/store/types';
import { BadgePercent } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

export interface CatalogFilterForm {
    q?: string;
    brands: (string | number)[];
    categories: (string | number)[];
    min_price?: string | number;
    max_price?: string | number;
    in_stock?: string | boolean;
    on_sale?: string | boolean;
    sort?: string;
}

const props = defineProps<{
    form: CatalogFilterForm;
    locale: string;
    brands: { id: number; name: string; slug: string }[] | null;
    categories: CategoryData[] | null;
    /** When set (mobile sheet), shows a live "show N results" close button —
     *  filters apply automatically, so there is no apply action anymore. */
    resultsCount?: number;
}>();

const emit = defineEmits<{ done: []; clear: [] }>();

const { t } = useI18n();

function toggleId(list: (string | number)[], id: number) {
    const index = list.map(Number).indexOf(id);
    if (index >= 0) list.splice(index, 1);
    else list.push(id);
}

function isSelected(list: (string | number)[], id: number): boolean {
    return list.map(Number).includes(id);
}

const numberInput =
    'w-full rounded-lg border border-input bg-background px-3 py-2 text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';

const chip = 'rounded-full border px-3.5 py-1.5 text-sm transition';
const chipOff = 'border-[#DBDBDB] text-foreground hover:border-black dark:border-neutral-700 dark:hover:border-white';
const chipOn = 'border-black bg-black text-white dark:border-white dark:bg-white dark:text-black';

function chipClass(selected: boolean): string {
    return `${chip} ${selected ? chipOn : chipOff}`;
}

function categoryName(option: CategoryData): string {
    return props.locale === 'ar' && option.name_ar ? option.name_ar : option.name;
}
</script>

<template>
    <div>
        <h2 class="mb-4 font-semibold">{{ t('catalog.filters') }}</h2>

        <!-- Price -->
        <div class="border-t border-[#EDEDED] py-4 dark:border-neutral-800">
            <h3 class="mb-3 text-sm font-medium">{{ t('catalog.price_range') }}</h3>
            <div class="flex items-center gap-2">
                <input v-model="form.min_price" type="number" inputmode="numeric" min="0" :placeholder="t('catalog.min')" :class="numberInput" />
                <span class="shrink-0 text-muted-foreground">–</span>
                <input v-model="form.max_price" type="number" inputmode="numeric" min="0" :placeholder="t('catalog.max')" :class="numberInput" />
            </div>

            <button
                type="button"
                class="mt-3 inline-flex items-center gap-1.5"
                :class="chipClass(form.on_sale === true)"
                @click="form.on_sale = form.on_sale !== true"
            >
                <BadgePercent class="h-4 w-4" />
                {{ t('catalog.on_sale_only') }}
            </button>
        </div>

        <!-- Brands -->
        <div v-if="brands && brands.length > 0" class="border-t border-[#EDEDED] py-4 dark:border-neutral-800">
            <h3 class="mb-3 text-sm font-medium">{{ t('nav.brands') }}</h3>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in brands"
                    :key="option.id"
                    type="button"
                    :class="chipClass(isSelected(form.brands, option.id))"
                    @click="toggleId(form.brands, option.id)"
                >
                    {{ option.name }}
                </button>
            </div>
        </div>

        <!-- Categories -->
        <div v-if="categories && categories.length > 0" class="border-t border-[#EDEDED] py-4 dark:border-neutral-800">
            <h3 class="mb-3 text-sm font-medium">{{ t('nav.categories') }}</h3>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in categories"
                    :key="option.id"
                    type="button"
                    :class="chipClass(isSelected(form.categories, option.id))"
                    @click="toggleId(form.categories, option.id)"
                >
                    {{ categoryName(option) }}
                </button>
            </div>
        </div>

        <div class="mt-2 flex gap-2">
            <button
                type="button"
                class="rounded-lg border border-[#DBDBDB] py-2.5 text-sm font-semibold transition hover:border-black dark:border-neutral-700 dark:hover:border-white"
                :class="resultsCount !== undefined ? 'w-1/3' : 'w-full'"
                @click="emit('clear')"
            >
                {{ t('catalog.clear') }}
            </button>
            <button
                v-if="resultsCount !== undefined"
                type="button"
                class="flex-1 rounded-lg bg-black py-2.5 text-sm font-semibold text-white transition hover:opacity-85 dark:bg-white dark:text-black"
                @click="emit('done')"
            >
                {{ t('catalog.show_results', { count: resultsCount }) }}
            </button>
        </div>
    </div>
</template>
