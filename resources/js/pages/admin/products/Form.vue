<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import AdminSelect from '@/components/admin/AdminSelect.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import type { Option } from '@/components/admin/types';
import { btnPrimary, btnSecondary, checkboxClass, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface VariantForm {
    id: number | null;
    name: string;
    sku: string;
    barcode: string | null;
    attributes: Record<string, string> | null;
    cost_price: number | null;
    price: number | null;
    sale_price: number | null;
    low_stock_threshold: number;
    is_active: boolean;
}

interface SpecValue {
    value: string;
    value_ar: string | null;
}

interface ProductImage {
    id: number;
    url: string;
    is_primary: boolean;
}

interface ProductData {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    sku: string;
    barcode: string | null;
    category_id: number;
    brand_id: number | null;
    supplier_id: number | null;
    short_description: string | null;
    short_description_ar: string | null;
    description: string | null;
    description_ar: string | null;
    cost_price: number;
    price: number;
    sale_price: number | null;
    sale_starts_at: string | null;
    sale_ends_at: string | null;
    tax_rate: number;
    low_stock_threshold: number;
    warranty_months: number | null;
    weight_grams: number | null;
    length_mm: number | null;
    width_mm: number | null;
    height_mm: number | null;
    status: string;
    is_featured: boolean;
    meta_title: string | null;
    meta_description: string | null;
    images: ProductImage[];
    variants: VariantForm[];
    specifications: Record<number, SpecValue>;
}

const props = defineProps<{
    product: ProductData | null;
    categories: { id: number; name: string; parent_id: number | null }[];
    brands: { id: number; name: string }[];
    suppliers: { id: number; company_name: string }[];
    specificationGroups: {
        id: number;
        name: string;
        attributes: { id: number; name: string; unit: string | null; type: string; category_ids: number[] }[];
    }[];
    statuses: Option[];
}>();

const isEdit = props.product !== null;

const form = useForm({
    name: props.product?.name ?? '',
    name_ar: props.product?.name_ar ?? '',
    slug: props.product?.slug ?? '',
    sku: props.product?.sku ?? '',
    barcode: props.product?.barcode ?? '',
    category_id: props.product?.category_id ?? ('' as number | ''),
    brand_id: props.product?.brand_id ?? ('' as number | ''),
    supplier_id: props.product?.supplier_id ?? ('' as number | ''),
    short_description: props.product?.short_description ?? '',
    short_description_ar: props.product?.short_description_ar ?? '',
    description: props.product?.description ?? '',
    description_ar: props.product?.description_ar ?? '',
    cost_price: props.product?.cost_price ?? 0,
    price: props.product?.price ?? 0,
    sale_price: props.product?.sale_price ?? (null as number | null),
    sale_starts_at: props.product?.sale_starts_at ?? '',
    sale_ends_at: props.product?.sale_ends_at ?? '',
    tax_rate: props.product?.tax_rate ?? 0,
    low_stock_threshold: props.product?.low_stock_threshold ?? 5,
    warranty_months: props.product?.warranty_months ?? (null as number | null),
    weight_grams: props.product?.weight_grams ?? (null as number | null),
    length_mm: props.product?.length_mm ?? (null as number | null),
    width_mm: props.product?.width_mm ?? (null as number | null),
    height_mm: props.product?.height_mm ?? (null as number | null),
    status: props.product?.status ?? 'draft',
    is_featured: props.product?.is_featured ?? false,
    meta_title: props.product?.meta_title ?? '',
    meta_description: props.product?.meta_description ?? '',
    variants: (props.product?.variants ?? []) as VariantForm[],
    specifications: (props.product?.specifications ?? {}) as Record<number, SpecValue>,
});

/**
 * Only the attributes that belong to the selected category (or its parent
 * category), plus universal attributes that have no categories at all.
 * Groups left with nothing to show disappear entirely.
 */
const visibleGroups = computed(() => {
    const categoryId = form.category_id === '' ? null : Number(form.category_id);
    const parentId = categoryId !== null ? (props.categories.find((c) => c.id === categoryId)?.parent_id ?? null) : null;

    return props.specificationGroups
        .map((group) => ({
            ...group,
            attributes: group.attributes.filter(
                (attribute) =>
                    attribute.category_ids.length === 0 ||
                    (categoryId !== null &&
                        (attribute.category_ids.includes(categoryId) || (parentId !== null && attribute.category_ids.includes(parentId)))),
            ),
        }))
        .filter((group) => group.attributes.length > 0);
});

const visibleAttributeIds = computed(() => new Set(visibleGroups.value.flatMap((group) => group.attributes.map((a) => a.id))));

/** Nested keys like `variants.0.sku` — Inertia flattens them, so read them flat. */
function err(key: string): string | undefined {
    return (form.errors as Record<string, string>)[key];
}

/**
 * A 422 on a nested field used to render nowhere, and preserveScroll kept the
 * user pinned to the button — so a rejected save looked like a dead button.
 */
const errorSummary = computed(() => Object.values(form.errors as Record<string, string>).filter(Boolean));

function submit(): void {
    // Normalize empty selects to null.
    form.transform((data) => ({
        ...data,
        brand_id: data.brand_id === '' ? null : data.brand_id,
        supplier_id: data.supplier_id === '' ? null : data.supplier_id,
        slug: data.slug === '' ? null : data.slug,
        sale_price: data.sale_price === ('' as unknown) ? null : data.sale_price,
        // Always send the array — an empty list means "delete every variant";
        // null would mean "leave variants untouched" and removals never save.
        variants: data.variants,
        // Drop values for attributes the selected category doesn't have, so
        // switching category cleans up specs that no longer belong.
        // Vue's v-model on a `type="number"` input casts to a JS number, but
        // the backend validates `value` as a string — cast back before sending.
        specifications: Object.fromEntries(
            Object.entries(data.specifications)
                .filter(([attributeId]) => visibleAttributeIds.value.has(Number(attributeId)))
                .map(([attributeId, spec]) => [
                    attributeId,
                    { ...spec, value: spec.value === '' || spec.value === null ? spec.value : String(spec.value) },
                ]),
        ),
    }));

    if (isEdit) {
        form.patch(route('admin.products.update', props.product!.id), { preserveScroll: true });
    } else {
        form.post(route('admin.products.store'));
    }
}

// -- Variants -----------------------------------------------------------------

function addVariant(): void {
    form.variants.push({
        id: null,
        name: '',
        sku: '',
        barcode: null,
        attributes: {},
        cost_price: form.cost_price,
        price: form.price,
        sale_price: null,
        low_stock_threshold: 5,
        is_active: true,
    });
}

function removeVariant(index: number): void {
    form.variants.splice(index, 1);
}

// Attribute key/value editing helpers for variants
const attributeDrafts = ref<Record<number, { key: string; value: string }>>({});

function addAttribute(index: number): void {
    const draft = attributeDrafts.value[index];

    if (!draft || draft.key.trim() === '') return;

    const variant = form.variants[index];
    variant.attributes = { ...(variant.attributes ?? {}), [draft.key.trim()]: draft.value };
    attributeDrafts.value[index] = { key: '', value: '' };
}

function removeAttribute(index: number, key: string): void {
    const variant = form.variants[index];

    if (variant.attributes) {
        const { [key]: _removed, ...rest } = variant.attributes;
        variant.attributes = rest;
    }
}

// -- Specifications -----------------------------------------------------------

function specFor(attributeId: number): SpecValue {
    if (!form.specifications[attributeId]) {
        form.specifications[attributeId] = { value: '', value_ar: null };
    }

    return form.specifications[attributeId];
}

// -- Images -------------------------------------------------------------------

const imageInput = ref<HTMLInputElement | null>(null);
const uploadingImages = ref(false);

function uploadImages(event: Event): void {
    const files = (event.target as HTMLInputElement).files;

    if (!files || files.length === 0 || !props.product) return;

    uploadingImages.value = true;

    router.post(
        route('admin.products.images.store', props.product.id),
        { images: Array.from(files) },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploadingImages.value = false;
                if (imageInput.value) imageInput.value.value = '';
            },
        },
    );
}

function deleteImage(image: ProductImage): void {
    if (!props.product) return;

    router.delete(route('admin.products.images.destroy', [props.product.id, image.id]), { preserveScroll: true });
}
</script>

<template>
    <Head :title="isEdit ? t('admin.products.edit_named', { name: product?.name }) : 'New product'" />

    <PageHeader :title="isEdit ? t('admin.products.edit') : t('admin.products.new')" :subtitle="product?.name">
        <template #actions>
            <Link :href="route('admin.products.index')" :class="btnSecondary">{{ t('admin.common.back_to_list') }}</Link>
        </template>
    </PageHeader>

    <form class="grid gap-6 xl:grid-cols-3" @submit.prevent="submit">
        <div class="space-y-6 xl:col-span-2">
            <!-- Basics -->
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.products.basics') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field :label="t('admin.common.name')" required :error="form.errors.name">
                        <input v-model="form.name" type="text" :class="inputClass" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.common.name_ar')" :error="form.errors.name_ar">
                        <input v-model="form.name_ar" type="text" dir="rtl" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.common.sku')" required :error="form.errors.sku">
                        <input v-model="form.sku" type="text" :class="inputClass" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.products.barcode')" :error="form.errors.barcode">
                        <input v-model="form.barcode" type="text" :class="inputClass" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.common.slug')" :hint="t('admin.products.slug_hint')" :error="form.errors.slug">
                        <input v-model="form.slug" type="text" :class="inputClass" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.common.category')" required :error="form.errors.category_id">
                        <AdminSelect
                            v-model="form.category_id"
                            :placeholder="t('admin.common.choose')"
                            :options="categories.map((c) => ({ value: c.id, label: (c.parent_id ? '— ' : '') + c.name }))"
                        />
                    </Field>
                    <Field :label="t('admin.common.brand')" :error="form.errors.brand_id">
                        <AdminSelect
                            v-model="form.brand_id"
                            :empty-label="t('admin.common.none')"
                            :options="brands.map((b) => ({ value: b.id, label: b.name }))"
                        />
                    </Field>
                    <Field :label="t('admin.common.supplier')" :error="form.errors.supplier_id">
                        <AdminSelect
                            v-model="form.supplier_id"
                            :empty-label="t('admin.common.none')"
                            :options="suppliers.map((s) => ({ value: s.id, label: s.company_name }))"
                        />
                    </Field>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <Field :label="t('admin.products.short_description')" :error="form.errors.short_description">
                        <textarea v-model="form.short_description" rows="2" :class="inputClass" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.products.short_description_ar')" :error="form.errors.short_description_ar">
                        <textarea v-model="form.short_description_ar" rows="2" dir="rtl" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.common.description')" :error="form.errors.description">
                        <textarea v-model="form.description" rows="6" :class="inputClass" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.common.description_ar')" :error="form.errors.description_ar">
                        <textarea v-model="form.description_ar" rows="6" dir="rtl" :class="inputClass" />
                    </Field>
                </div>
            </section>

            <!-- Pricing -->
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.products.pricing') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Field :label="t('admin.products.cost_price')" required :error="form.errors.cost_price">
                        <input v-model.number="form.cost_price" type="number" step="0.01" min="0" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.products.regular_price')" required :error="form.errors.price">
                        <input v-model.number="form.price" type="number" step="0.01" min="0" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.products.sale_price')" :error="form.errors.sale_price">
                        <input v-model.number="form.sale_price" type="number" step="0.01" min="0" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.products.sale_starts')" :error="form.errors.sale_starts_at">
                        <AdminDateInput v-model="form.sale_starts_at" with-time />
                    </Field>
                    <Field :label="t('admin.products.sale_ends')" :error="form.errors.sale_ends_at">
                        <AdminDateInput v-model="form.sale_ends_at" with-time />
                    </Field>
                    <Field :label="t('admin.common.tax_rate')" :error="form.errors.tax_rate">
                        <input v-model.number="form.tax_rate" type="number" step="0.01" min="0" max="100" :class="inputClass" />
                    </Field>
                </div>
            </section>

            <!-- Variants -->
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.products.variants') }}</h2>
                    <button type="button" :class="btnSecondary" @click="addVariant">
                        <Plus class="h-4 w-4" />{{ t('admin.products.add_variant') }}
                    </button>
                </div>
                <p v-if="form.variants.length === 0" class="text-sm text-gray-400">{{ t('admin.products.no_variants') }}</p>
                <p v-if="form.errors.variants" class="mb-2 text-xs text-red-600">{{ form.errors.variants }}</p>

                <div
                    v-for="(variant, index) in form.variants"
                    :key="variant.id ?? `new-${index}`"
                    class="mb-4 rounded-md border border-gray-200 p-4 dark:border-gray-700"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{
                            t('admin.products.variant_n', { n: index + 1 })
                        }}</span>
                        <button type="button" class="text-red-500 hover:text-red-700" :title="t('admin.common.remove')" @click="removeVariant(index)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <Field :label="t('admin.common.name')" required :error="err(`variants.${index}.name`)">
                            <input v-model="variant.name" type="text" :placeholder="t('admin.products.variant_hint')" :class="inputClass" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.common.sku')" required :error="err(`variants.${index}.sku`)">
                            <input v-model="variant.sku" type="text" :class="inputClass" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.products.barcode')" :error="err(`variants.${index}.barcode`)">
                            <input v-model="variant.barcode" type="text" :class="inputClass" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.products.cost_price')" :error="err(`variants.${index}.cost_price`)">
                            <input v-model.number="variant.cost_price" type="number" step="0.01" min="0" :class="inputClass" />
                        </Field>
                        <Field :label="t('admin.common.price')" required :error="err(`variants.${index}.price`)">
                            <input v-model.number="variant.price" type="number" step="0.01" min="0" :class="inputClass" />
                        </Field>
                        <Field :label="t('admin.products.sale_price')" :error="err(`variants.${index}.sale_price`)">
                            <input v-model.number="variant.sale_price" type="number" step="0.01" min="0" :class="inputClass" />
                        </Field>
                        <Field :label="t('admin.common.low_stock_threshold')" :error="err(`variants.${index}.low_stock_threshold`)">
                            <input v-model.number="variant.low_stock_threshold" type="number" min="0" :class="inputClass" />
                        </Field>
                        <label class="mt-6 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="variant.is_active" type="checkbox" :class="checkboxClass" />{{ t('admin.common.active') }}</label
                        >
                    </div>

                    <div class="mt-3">
                        <p class="mb-1 text-xs font-medium uppercase text-gray-400">{{ t('admin.products.attributes') }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-for="(value, key) in variant.attributes ?? {}"
                                :key="key"
                                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            >
                                {{ key }}: {{ value }}
                                <button type="button" class="text-gray-400 hover:text-red-500" @click="removeAttribute(index, String(key))">×</button>
                            </span>
                            <input
                                :value="attributeDrafts[index]?.key ?? ''"
                                type="text"
                                :placeholder="t('admin.products.attr_key_hint')"
                                :class="inputClass"
                                class="!w-24 !px-2 !py-1 text-xs"
                                @input="
                                    attributeDrafts[index] = {
                                        key: ($event.target as HTMLInputElement).value,
                                        value: attributeDrafts[index]?.value ?? '',
                                    }
                                "
                            />
                            <input
                                :value="attributeDrafts[index]?.value ?? ''"
                                type="text"
                                :placeholder="t('admin.products.attr_value_hint')"
                                :class="inputClass"
                                class="!w-28 !px-2 !py-1 text-xs"
                                @input="
                                    attributeDrafts[index] = {
                                        key: attributeDrafts[index]?.key ?? '',
                                        value: ($event.target as HTMLInputElement).value,
                                    }
                                "
                            />
                            <button type="button" class="text-xs text-blue-600 hover:underline" @click="addAttribute(index)">
                                {{ t('admin.common.add') }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Specifications -->
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.nav.specifications') }}</h2>
                <p v-if="form.category_id === ''" class="mb-3 text-sm text-gray-400">{{ t('admin.products.pick_category_for_specs') }}</p>
                <div v-for="group in visibleGroups" :key="group.id" class="mb-5">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ group.name }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="attribute in group.attributes" :key="attribute.id" class="grid grid-cols-2 gap-2">
                            <Field
                                :label="attribute.unit ? `${attribute.name} (${attribute.unit})` : attribute.name"
                                :error="err(`specifications.${attribute.id}.value`)"
                            >
                                <!--
                                    step="any": a bare number input defaults to step=1, so a
                                    screen size of 6.8 failed native constraint validation and
                                    silently blocked the whole form from submitting — which is
                                    what "Save does nothing on some products" actually was.
                                -->
                                <input
                                    v-model="specFor(attribute.id).value"
                                    :type="attribute.type === 'number' ? 'number' : 'text'"
                                    :step="attribute.type === 'number' ? 'any' : undefined"
                                    :class="inputClass"
                                />
                            </Field>
                            <Field :label="`${attribute.name} (AR)`" :error="err(`specifications.${attribute.id}.value_ar`)">
                                <input v-model="specFor(attribute.id).value_ar" type="text" dir="rtl" :class="inputClass" />
                            </Field>
                        </div>
                    </div>
                </div>
                <p v-if="form.category_id !== '' && visibleGroups.length === 0" class="text-sm text-gray-400">
                    {{ t('admin.products.no_specs_hint') }}
                </p>
            </section>

            <!-- Images (edit only) -->
            <section v-if="isEdit" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.products.images') }}</h2>
                    <label :class="btnSecondary" class="cursor-pointer">
                        <Upload class="h-4 w-4" />
                        {{ uploadingImages ? 'Uploading…' : 'Upload images' }}
                        <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="uploadImages" />
                    </label>
                </div>
                <div v-if="(product?.images.length ?? 0) > 0" class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                    <div v-for="image in product?.images" :key="image.id" class="group relative">
                        <img
                            :src="image.url"
                            alt=""
                            class="aspect-square w-full rounded-md border border-gray-200 object-cover dark:border-gray-700"
                        />
                        <span
                            v-if="image.is_primary"
                            class="absolute start-1 top-1 rounded bg-blue-600 px-1.5 py-0.5 text-[10px] font-medium text-white"
                            >{{ t('admin.common.primary') }}</span
                        >
                        <button
                            type="button"
                            class="absolute end-1 top-1 hidden rounded bg-red-600 p-1 text-white group-hover:block"
                            :title="t('admin.products.delete_image')"
                            @click="deleteImage(image)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400">{{ t('admin.products.no_images') }}</p>
            </section>
            <p v-else class="text-sm text-gray-400">{{ t('admin.products.save_first') }}</p>
        </div>

        <!-- Side column -->
        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.status') }}</h2>
                <Field :label="t('admin.common.status')" required :error="form.errors.status">
                    <AdminSelect v-model="form.status" :options="statuses" />
                </Field>
                <label class="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="form.is_featured" type="checkbox" :class="checkboxClass" />{{ t('admin.products.featured_product') }}</label
                >
                <Field class="mt-4" :label="t('admin.common.low_stock_threshold')" :error="form.errors.low_stock_threshold">
                    <input v-model.number="form.low_stock_threshold" type="number" min="0" :class="inputClass" />
                </Field>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.products.logistics') }}</h2>
                <div class="grid gap-3">
                    <Field :label="t('admin.products.warranty')" :error="form.errors.warranty_months">
                        <input v-model.number="form.warranty_months" type="number" min="0" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.products.weight')" :error="form.errors.weight_grams">
                        <input v-model.number="form.weight_grams" type="number" min="0" :class="inputClass" />
                    </Field>
                    <div class="grid grid-cols-3 gap-2">
                        <Field :label="t('admin.products.length')"
                            ><input v-model.number="form.length_mm" type="number" min="0" :class="inputClass"
                        /></Field>
                        <Field :label="t('admin.products.width')"
                            ><input v-model.number="form.width_mm" type="number" min="0" :class="inputClass"
                        /></Field>
                        <Field :label="t('admin.products.height')"
                            ><input v-model.number="form.height_mm" type="number" min="0" :class="inputClass"
                        /></Field>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.products.seo') }}</h2>
                <div class="grid gap-3">
                    <Field :label="t('admin.products.meta_title')" :error="form.errors.meta_title">
                        <input v-model="form.meta_title" type="text" :class="inputClass" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.products.meta_description')" :error="form.errors.meta_description">
                        <textarea v-model="form.meta_description" rows="3" :class="inputClass" dir="ltr" />
                    </Field>
                </div>
            </section>

            <div
                v-if="errorSummary.length > 0"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
            >
                <p class="mb-1 font-semibold">{{ t('admin.common.fix_errors') }}</p>
                <ul class="list-inside list-disc space-y-0.5">
                    <li v-for="(message, index) in errorSummary" :key="index">{{ message }}</li>
                </ul>
            </div>

            <div class="flex gap-3">
                <button type="submit" :disabled="form.processing" :class="btnPrimary" class="flex-1 justify-center">
                    {{ form.processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create product' }}
                </button>
            </div>
        </div>
    </form>
</template>
