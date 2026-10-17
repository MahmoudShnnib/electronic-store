<script setup lang="ts">
/**
 * Edits one page section's `data`, with a form per section type. Opened
 * either on an existing section (PUT) or on a type picked from the add menu
 * (POST — nothing is created until the admin saves, so a half-filled block
 * never reaches the storefront).
 */
import Field from '@/components/admin/Field.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Alert, Button, Drawer, Empty, Input, InputNumber, Select, Switch, Textarea } from 'ant-design-vue';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ImageField from './ImageField.vue';
import ProductPicker from './ProductPicker.vue';
import {
    defaultData,
    SECTION_ICONS,
    type BuilderLimits,
    type CategoryOption,
    type ProductSummary,
    type Section,
    type SectionData,
    type SectionType,
} from './types';

const props = defineProps<{
    open: boolean;
    pageId: number;
    /** The section being edited, or null when adding one of `type`. */
    section: Section | null;
    type: SectionType | null;
    products: Record<number, ProductSummary>;
    categories: CategoryOption[];
    sources: string[];
    limits: BuilderLimits;
    storageUrl: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'products', products: ProductSummary[]): void;
}>();

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

const currentType = computed<SectionType | null>(() => props.section?.type ?? props.type);

const draft = reactive<{ data: SectionData; is_active: boolean }>({ data: {}, is_active: true });

const form = useForm<{ type?: SectionType; is_active: boolean; data: SectionData }>({ is_active: true, data: {} });

watch(
    () => [props.open, props.section, props.type] as const,
    ([open]) => {
        if (!open || currentType.value === null) return;

        // Missing keys come from the type's defaults so every field has a model.
        draft.data = { ...defaultData(currentType.value), ...JSON.parse(JSON.stringify(props.section?.data ?? {})) };
        draft.is_active = props.section?.is_active ?? true;
        form.clearErrors();
    },
    { immediate: true },
);

const errors = computed(() => form.errors as Record<string, string>);
const err = (field: string): string | undefined => errors.value[`data.${field}`];

function move(list: unknown[], index: number, delta: number): void {
    const target = index + delta;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
}

const name = (item: { name: string; name_ar: string | null }) => (locale.value === 'ar' && item.name_ar ? item.name_ar : item.name);

const categoryOptions = computed(() =>
    props.categories.map((category) => ({
        value: category.id,
        label: name(category) + (category.is_active ? '' : ` (${t('admin.homepage.inactive_category')})`),
    })),
);

const rootCategoryOptions = computed(() => categoryOptions.value.filter((_, i) => props.categories[i].parent_id === null));

const sourceOptions = computed(() => props.sources.map((source) => ({ value: source, label: t(`admin.pages.sources.${source}`) })));

const iconOptions = SECTION_ICONS.map((icon) => ({ value: icon, label: icon }));

function save(): void {
    const options = { preserveScroll: true, preserveState: true, onSuccess: () => emit('close') };

    form.transform(() => ({
        ...(props.section ? {} : { type: currentType.value! }),
        is_active: draft.is_active,
        data: draft.data,
    }));

    if (props.section) {
        form.put(route('admin.pages.sections.update', [props.pageId, props.section.id]), options);
    } else {
        form.post(route('admin.pages.sections.store', props.pageId), options);
    }
}

const title = computed(() =>
    currentType.value
        ? `${props.section ? t('admin.pages.edit_section') : t('admin.pages.add_section')}: ${t(`admin.pages.types.${currentType.value}`)}`
        : '',
);
</script>

<template>
    <Drawer :open="open" :title="title" :width="560" destroy-on-close @close="emit('close')">
        <form v-if="currentType" class="grid" @submit.prevent="save">
            <p class="admin-muted mb-4 text-sm">{{ t(`admin.pages.type_hints.${currentType}`) }}</p>

            <!-- Hero slides ------------------------------------------------ -->
            <template v-if="currentType === 'hero'">
                <Alert v-if="err('slides')" type="error" :message="err('slides')" class="!mb-4" />
                <Empty v-if="draft.data.slides.length === 0" :description="t('admin.homepage.empty_hero')" class="!mb-4" />
                <div v-for="(slide, index) in draft.data.slides" :key="index" class="builder-row">
                    <div class="builder-row-head">
                        <strong>{{ t('admin.homepage.slide_n', { n: index + 1 }) }}</strong>
                        <div class="flex gap-1">
                            <Button
                                size="small"
                                type="text"
                                :disabled="index === 0"
                                :aria-label="t('admin.homepage.move_up')"
                                @click="move(draft.data.slides, index, -1)"
                            >
                                <template #icon><ArrowUp class="h-4 w-4" /></template>
                            </Button>
                            <Button
                                size="small"
                                type="text"
                                :disabled="index === draft.data.slides.length - 1"
                                :aria-label="t('admin.homepage.move_down')"
                                @click="move(draft.data.slides, index, 1)"
                            >
                                <template #icon><ArrowDown class="h-4 w-4" /></template>
                            </Button>
                            <Button
                                size="small"
                                type="text"
                                danger
                                :aria-label="t('admin.common.remove')"
                                @click="draft.data.slides.splice(index, 1)"
                            >
                                <template #icon><Trash2 class="h-4 w-4" /></template>
                            </Button>
                        </div>
                    </div>
                    <Field :label="t('admin.homepage.product')" required :error="err(`slides.${index}.product_id`)">
                        <ProductPicker v-model:value="slide.product_id" :known="products" @picked="emit('products', $event)" />
                    </Field>
                    <Alert
                        v-if="slide.product_id && products[slide.product_id] && !products[slide.product_id].visible"
                        type="warning"
                        show-icon
                        :message="t('admin.homepage.hidden_warning')"
                        class="!mb-4"
                    />
                    <div class="grid gap-x-3 sm:grid-cols-2">
                        <Field :label="t('admin.pages.title_en')" :hint="t('admin.homepage.headline_hint')" :error="err(`slides.${index}.title`)">
                            <Input v-model:value="slide.title" :maxlength="120" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.pages.title_ar')" :error="err(`slides.${index}.title_ar`)">
                            <Input v-model:value="slide.title_ar" :maxlength="120" dir="rtl" />
                        </Field>
                    </div>
                    <Field :label="t('admin.homepage.artwork')" :hint="t('admin.homepage.artwork_hint')" :error="err(`slides.${index}.image_path`)">
                        <ImageField
                            v-model="slide.image_path"
                            :storage-url="storageUrl"
                            :fallback="slide.product_id ? products[slide.product_id]?.image : null"
                        />
                    </Field>
                </div>
                <Button
                    :disabled="draft.data.slides.length >= limits.slides"
                    @click="draft.data.slides.push({ product_id: null, title: '', title_ar: '', image_path: null })"
                >
                    <span class="flex items-center gap-1.5"><Plus class="h-4 w-4" />{{ t('admin.homepage.add_slide') }}</span>
                </Button>
            </template>

            <!-- Promo tiles ------------------------------------------------ -->
            <template v-else-if="currentType === 'promo_tiles'">
                <Alert v-if="err('tiles')" type="error" :message="err('tiles')" class="!mb-4" />
                <Empty v-if="draft.data.tiles.length === 0" :description="t('admin.homepage.empty_tiles')" class="!mb-4" />
                <div v-for="(tile, index) in draft.data.tiles" :key="index" class="builder-row">
                    <div class="builder-row-head">
                        <strong>{{ t('admin.homepage.tile_n', { n: index + 1 }) }}</strong>
                        <Button size="small" type="text" danger :aria-label="t('admin.common.remove')" @click="draft.data.tiles.splice(index, 1)">
                            <template #icon><Trash2 class="h-4 w-4" /></template>
                        </Button>
                    </div>
                    <Field :label="t('admin.homepage.category')" required :error="err(`tiles.${index}.category_id`)">
                        <Select
                            v-model:value="tile.category_id"
                            :options="rootCategoryOptions"
                            :placeholder="t('admin.homepage.choose_category')"
                            show-search
                            option-filter-prop="label"
                            class="w-full"
                        />
                    </Field>
                    <div class="grid gap-x-3 sm:grid-cols-2">
                        <Field :label="t('admin.pages.title_en')" :hint="t('admin.homepage.tile_title_hint')">
                            <Input v-model:value="tile.title" :maxlength="120" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.pages.title_ar')">
                            <Input v-model:value="tile.title_ar" :maxlength="120" dir="rtl" />
                        </Field>
                    </div>
                    <Field :label="t('admin.homepage.artwork')" :error="err(`tiles.${index}.image_path`)">
                        <ImageField v-model="tile.image_path" :storage-url="storageUrl" />
                    </Field>
                </div>
                <Button
                    :disabled="draft.data.tiles.length >= limits.tiles"
                    @click="draft.data.tiles.push({ category_id: null, title: '', title_ar: '', image_path: null })"
                >
                    <span class="flex items-center gap-1.5"><Plus class="h-4 w-4" />{{ t('admin.homepage.add_tile') }}</span>
                </Button>
            </template>

            <!-- Trust strip ------------------------------------------------ -->
            <template v-else-if="currentType === 'trust_strip'">
                <Alert v-if="err('items')" type="error" :message="err('items')" class="!mb-4" />
                <div v-for="(item, index) in draft.data.items" :key="index" class="builder-row">
                    <div class="builder-row-head">
                        <strong>{{ t('admin.pages.item_n', { n: index + 1 }) }}</strong>
                        <Button
                            size="small"
                            type="text"
                            danger
                            :disabled="draft.data.items.length <= 1"
                            :aria-label="t('admin.common.remove')"
                            @click="draft.data.items.splice(index, 1)"
                        >
                            <template #icon><Trash2 class="h-4 w-4" /></template>
                        </Button>
                    </div>
                    <Field :label="t('admin.pages.icon')" required :error="err(`items.${index}.icon`)">
                        <Select v-model:value="item.icon" :options="iconOptions" class="w-full" />
                    </Field>
                    <div class="grid gap-x-3 sm:grid-cols-2">
                        <Field :label="t('admin.pages.title_en')" required :error="err(`items.${index}.title`)">
                            <Input v-model:value="item.title" :maxlength="120" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.pages.title_ar')" :error="err(`items.${index}.title_ar`)">
                            <Input v-model:value="item.title_ar" :maxlength="120" dir="rtl" />
                        </Field>
                        <Field :label="t('admin.pages.text_en')" :error="err(`items.${index}.text`)">
                            <Input v-model:value="item.text" :maxlength="255" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.pages.text_ar')" :error="err(`items.${index}.text_ar`)">
                            <Input v-model:value="item.text_ar" :maxlength="255" dir="rtl" />
                        </Field>
                    </div>
                </div>
                <Button
                    :disabled="draft.data.items.length >= limits.trust_items"
                    @click="draft.data.items.push({ icon: 'star', title: '', title_ar: '', text: '', text_ar: '' })"
                >
                    <span class="flex items-center gap-1.5"><Plus class="h-4 w-4" />{{ t('admin.pages.add_item') }}</span>
                </Button>
            </template>

            <!-- Everything else: a heading pair plus the type's own fields -- -->
            <template v-else>
                <div v-if="currentType !== 'category_spotlight' && currentType !== 'rich_text'" class="grid gap-x-3 sm:grid-cols-2">
                    <Field :label="t('admin.pages.title_en')" :error="err('title')">
                        <Input v-model:value="draft.data.title" :maxlength="120" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.pages.title_ar')" :error="err('title_ar')">
                        <Input v-model:value="draft.data.title_ar" :maxlength="120" dir="rtl" />
                    </Field>
                </div>

                <template v-if="currentType === 'product_carousel'">
                    <div class="grid gap-x-3 sm:grid-cols-2">
                        <Field :label="t('admin.pages.source')" required :error="err('source')">
                            <Select v-model:value="draft.data.source" :options="sourceOptions" class="w-full" />
                        </Field>
                        <Field :label="t('admin.pages.limit')" required :error="err('limit')">
                            <InputNumber v-model:value="draft.data.limit" :min="1" :max="limits.carousel" class="w-full" />
                        </Field>
                    </div>
                    <Field v-if="draft.data.source === 'category'" :label="t('admin.homepage.category')" required :error="err('category_id')">
                        <Select
                            v-model:value="draft.data.category_id"
                            :options="categoryOptions"
                            :placeholder="t('admin.homepage.choose_category')"
                            show-search
                            option-filter-prop="label"
                            class="w-full"
                        />
                    </Field>
                    <Field
                        v-if="draft.data.source === 'manual'"
                        :label="t('admin.common.products')"
                        required
                        :hint="t('admin.pages.manual_hint')"
                        :error="err('product_ids')"
                    >
                        <ProductPicker
                            v-model:value="draft.data.product_ids"
                            multiple
                            :max="limits.carousel"
                            :known="products"
                            @picked="emit('products', $event)"
                        />
                    </Field>
                    <Field :label="t('admin.pages.icon')" :error="err('icon')">
                        <Select v-model:value="draft.data.icon" :options="iconOptions" allow-clear class="w-full" />
                    </Field>
                </template>

                <template v-else-if="currentType === 'image_banner'">
                    <Field :label="t('admin.pages.image')" required :error="err('image_path')">
                        <ImageField v-model="draft.data.image_path" :storage-url="storageUrl" />
                    </Field>
                    <Field :label="t('admin.pages.link')" :hint="t('admin.pages.link_hint')" :error="err('link')">
                        <Input v-model:value="draft.data.link" :maxlength="255" dir="ltr" />
                    </Field>
                </template>

                <template v-else-if="currentType === 'rich_text'">
                    <Field :label="t('admin.pages.body_en')" :hint="t('admin.pages.body_hint')" :error="err('body')">
                        <Textarea v-model:value="draft.data.body" :rows="8" dir="ltr" class="font-mono" />
                    </Field>
                    <Field :label="t('admin.pages.body_ar')" :error="err('body_ar')">
                        <Textarea v-model:value="draft.data.body_ar" :rows="8" dir="rtl" class="font-mono" />
                    </Field>
                </template>

                <template v-else-if="currentType === 'newsletter'">
                    <div class="grid gap-x-3 sm:grid-cols-2">
                        <Field :label="t('admin.pages.text_en')" :error="err('text')">
                            <Input v-model:value="draft.data.text" :maxlength="255" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.pages.text_ar')" :error="err('text_ar')">
                            <Input v-model:value="draft.data.text_ar" :maxlength="255" dir="rtl" />
                        </Field>
                    </div>
                </template>
            </template>

            <div class="mt-6 flex items-center justify-between gap-3 border-t border-ad-line pt-4">
                <label class="flex items-center gap-2 text-sm">
                    <Switch v-model:checked="draft.is_active" size="small" />
                    {{ t('admin.pages.shown') }}
                </label>
                <div class="flex gap-2">
                    <Button @click="emit('close')">{{ t('admin.common.cancel') }}</Button>
                    <Button type="primary" html-type="submit" :loading="form.processing">
                        {{ form.processing ? t('admin.common.saving') : t('admin.common.save') }}
                    </Button>
                </div>
            </div>
        </form>
    </Drawer>
</template>

<style scoped>
.builder-row {
    margin-bottom: 1rem;
    padding: 0.875rem 1rem 0.25rem;
    border: 1px solid var(--ad-line);
    border-radius: 8px;
    background: var(--ad-bg);
}

.builder-row-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}
</style>
