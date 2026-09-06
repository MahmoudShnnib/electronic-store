<script setup lang="ts">
import type { CategoryData } from '@/components/store/types';
import { Button, Divider, InputNumber, Tag } from 'ant-design-vue';
import { BadgePercent } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const CheckableTag = Tag.CheckableTag;

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
    /** When set (mobile drawer), shows a live "show N results" close button —
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

function categoryName(option: CategoryData): string {
    return props.locale === 'ar' && option.name_ar ? option.name_ar : option.name;
}
</script>

<!-- The parent passes its reactive filter form down on purpose: this panel is
     a controlled view over it, and the page's deep watcher auto-applies any
     mutation made here. -->
<!-- eslint-disable vue/no-mutating-props -->
<template>
    <div class="store-filters">
        <h2 class="text-base font-semibold">{{ t('catalog.filters') }}</h2>

        <!-- Price -->
        <Divider class="!mb-4 !mt-3" />
        <h3 class="store-filters-heading">{{ t('catalog.price_range') }}</h3>
        <div class="flex items-center gap-2">
            <InputNumber
                v-model:value="form.min_price"
                :min="0"
                :placeholder="t('catalog.min')"
                :aria-label="t('catalog.min')"
                inputmode="numeric"
                class="w-full"
            />
            <span class="shrink-0 opacity-50" aria-hidden="true">–</span>
            <InputNumber
                v-model:value="form.max_price"
                :min="0"
                :placeholder="t('catalog.max')"
                :aria-label="t('catalog.max')"
                inputmode="numeric"
                class="w-full"
            />
        </div>

        <!-- AntD's CheckableTag is a bare <span>: it has no role, no checked state and
             no keyboard path of its own, so every chip states all three here. -->
        <CheckableTag
            :checked="form.on_sale === true"
            class="store-filter-tag !mt-3"
            role="checkbox"
            :aria-checked="form.on_sale === true"
            tabindex="0"
            @change="form.on_sale = form.on_sale !== true"
            @keydown.enter.prevent="form.on_sale = form.on_sale !== true"
            @keydown.space.prevent="form.on_sale = form.on_sale !== true"
        >
            <span class="flex items-center gap-1.5">
                <BadgePercent class="h-3.5 w-3.5" />
                {{ t('catalog.on_sale_only') }}
            </span>
        </CheckableTag>

        <!-- Brands -->
        <template v-if="brands && brands.length > 0">
            <Divider class="!mb-4 !mt-5" />
            <h3 class="store-filters-heading">{{ t('nav.brands') }}</h3>
            <div class="flex flex-wrap gap-2" role="group" :aria-label="t('nav.brands')">
                <CheckableTag
                    v-for="option in brands"
                    :key="option.id"
                    :checked="isSelected(form.brands, option.id)"
                    class="store-filter-tag"
                    role="checkbox"
                    :aria-checked="isSelected(form.brands, option.id)"
                    tabindex="0"
                    @change="toggleId(form.brands, option.id)"
                    @keydown.enter.prevent="toggleId(form.brands, option.id)"
                    @keydown.space.prevent="toggleId(form.brands, option.id)"
                >
                    {{ option.name }}
                </CheckableTag>
            </div>
        </template>

        <!-- Categories -->
        <template v-if="categories && categories.length > 0">
            <Divider class="!mb-4 !mt-5" />
            <h3 class="store-filters-heading">{{ t('nav.categories') }}</h3>
            <div class="flex flex-wrap gap-2" role="group" :aria-label="t('nav.categories')">
                <CheckableTag
                    v-for="option in categories"
                    :key="option.id"
                    :checked="isSelected(form.categories, option.id)"
                    class="store-filter-tag"
                    role="checkbox"
                    :aria-checked="isSelected(form.categories, option.id)"
                    tabindex="0"
                    @change="toggleId(form.categories, option.id)"
                    @keydown.enter.prevent="toggleId(form.categories, option.id)"
                    @keydown.space.prevent="toggleId(form.categories, option.id)"
                >
                    {{ categoryName(option) }}
                </CheckableTag>
            </div>
        </template>

        <div class="mt-6 flex gap-2">
            <Button :block="resultsCount === undefined" @click="emit('clear')">{{ t('catalog.clear') }}</Button>
            <Button v-if="resultsCount !== undefined" type="primary" class="flex-1" @click="emit('done')">
                {{ t('catalog.show_results', { count: resultsCount }) }}
            </Button>
        </div>
    </div>
</template>
