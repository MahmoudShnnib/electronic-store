<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import AdminSelect from '@/components/admin/AdminSelect.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import type { Option } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Alert, Button, Card, Checkbox, Input, InputNumber, Tag, Textarea, Tooltip, Typography, Upload } from 'ant-design-vue';
import { Plus, Trash2, Upload as UploadIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
    low_stock_threshold: number;
    status: string;
    is_featured: boolean;
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
    /** Shelf stock of a plain product, per warehouse (empty once it has variants). */
    plainStock: { warehouse_id: number; warehouse: string | null; quantity: number }[];
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
    low_stock_threshold: props.product?.low_stock_threshold ?? 5,
    status: props.product?.status ?? 'draft',
    is_featured: props.product?.is_featured ?? false,
    variants: (props.product?.variants ?? []) as VariantForm[],
    specifications: (props.product?.specifications ?? {}) as Record<number, SpecValue>,
});

const knownVariantIds = (props.product?.variants ?? []).flatMap((v) => (v.id ? [v.id] : []));

/**
 * A stocked plain product getting two or more variants in one save: staff say
 * how the shelf units split between them, per warehouse. Each row lines up with
 * form.variants by index and starts with everything on the first variant.
 */
const splitting = computed(() => (props.product?.variants.length ?? 0) === 0 && props.plainStock.length > 0 && form.variants.length > 1);
const stockSplit = ref<Record<number, number[]>>({});

// Keep one cell per variant as variants are added or removed.
watch(
    () => form.variants.length,
    (count) => {
        for (const level of props.plainStock) {
            // Nothing typed yet: everything starts on the first variant.
            const typed = stockSplit.value[level.warehouse_id] ?? [];
            const row = typed.length > 0 ? typed : [level.quantity];
            stockSplit.value[level.warehouse_id] = Array.from({ length: count }, (_, index) => row[index] ?? 0);
        }
    },
    { immediate: true },
);

function splitLeft(warehouseId: number, quantity: number): number {
    return quantity - (stockSplit.value[warehouseId] ?? []).reduce((sum, n) => sum + (Number(n) || 0), 0);
}

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
        // Variants this page loaded with: only those may be removed by leaving
        // them out, so one opened before a colleague added a variant can't delete it.
        known_variant_ids: knownVariantIds,
        stock_split: splitting.value
            ? props.plainStock.flatMap((level) =>
                  (stockSplit.value[level.warehouse_id] ?? []).map((quantity, index) => ({
                      warehouse_id: level.warehouse_id,
                      sku: data.variants[index]?.sku ?? '',
                      quantity: Number(quantity) || 0,
                  })),
              )
            : null,
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

const uploadingImages = ref(false);

/**
 * AntD calls `before-upload` once per file but hands each call the whole
 * selection, so post the batch once — on the last file — to keep the single
 * multi-image request the native `<input multiple>` used to make.
 */
function uploadImages(files: File[]): void {
    if (files.length === 0 || !props.product) return;

    uploadingImages.value = true;

    router.post(
        route('admin.products.images.store', props.product.id),
        { images: files },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => (uploadingImages.value = false),
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
            <Button @click="router.visit(route('admin.products.index'))">{{ t('admin.common.back_to_list') }}</Button>
        </template>
    </PageHeader>

    <form class="grid gap-6 xl:grid-cols-3" @submit.prevent="submit">
        <div class="space-y-6 xl:col-span-2">
            <!-- Basics -->
            <Card :title="t('admin.products.basics')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field :label="t('admin.common.name')" required :error="form.errors.name">
                        <Input v-model:value="form.name" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.common.name_ar')" :error="form.errors.name_ar">
                        <Input v-model:value="form.name_ar" dir="rtl" />
                    </Field>
                    <Field :label="t('admin.common.sku')" required :error="form.errors.sku">
                        <Input v-model:value="form.sku" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.products.barcode')" :error="form.errors.barcode">
                        <Input v-model:value="form.barcode" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.common.slug')" :hint="t('admin.products.slug_hint')" :error="form.errors.slug">
                        <Input v-model:value="form.slug" dir="ltr" />
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
                        <Textarea v-model:value="form.short_description" :rows="2" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.products.short_description_ar')" :error="form.errors.short_description_ar">
                        <Textarea v-model:value="form.short_description_ar" :rows="2" dir="rtl" />
                    </Field>
                    <Field :label="t('admin.common.description')" :error="form.errors.description">
                        <Textarea v-model:value="form.description" :rows="6" dir="ltr" />
                    </Field>
                    <Field :label="t('admin.common.description_ar')" :error="form.errors.description_ar">
                        <Textarea v-model:value="form.description_ar" :rows="6" dir="rtl" />
                    </Field>
                </div>
            </Card>

            <!-- Pricing -->
            <Card :title="t('admin.products.pricing')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <Field :label="t('admin.products.cost_price')" required :error="form.errors.cost_price">
                        <InputNumber v-model:value="form.cost_price" :step="0.01" :min="0" class="w-full" />
                    </Field>
                    <Field :label="t('admin.products.regular_price')" required :error="form.errors.price">
                        <InputNumber v-model:value="form.price" :step="0.01" :min="0" class="w-full" />
                    </Field>
                    <Field :label="t('admin.products.sale_price')" :error="form.errors.sale_price">
                        <InputNumber v-model:value="form.sale_price" :step="0.01" :min="0" class="w-full" />
                    </Field>
                    <Field :label="t('admin.products.sale_starts')" :error="form.errors.sale_starts_at">
                        <AdminDateInput v-model="form.sale_starts_at" with-time />
                    </Field>
                    <Field :label="t('admin.products.sale_ends')" :error="form.errors.sale_ends_at">
                        <AdminDateInput v-model="form.sale_ends_at" with-time />
                    </Field>
                </div>
            </Card>

            <!-- Variants -->
            <Card :title="t('admin.products.variants')">
                <template #extra>
                    <Button @click="addVariant">
                        <template #icon><Plus class="h-4 w-4" /></template>
                        {{ t('admin.products.add_variant') }}
                    </Button>
                </template>
                <Typography.Text v-if="form.variants.length === 0" type="secondary">{{ t('admin.products.no_variants') }}</Typography.Text>
                <Typography.Text v-if="form.errors.variants" type="danger" class="mb-2 block text-xs">{{ form.errors.variants }}</Typography.Text>

                <Card
                    v-for="(variant, index) in form.variants"
                    :key="variant.id ?? `new-${index}`"
                    size="small"
                    class="mb-4"
                    :title="t('admin.products.variant_n', { n: index + 1 })"
                >
                    <template #extra>
                        <Tooltip :title="t('admin.common.remove')">
                            <Button type="text" size="small" danger :aria-label="t('admin.common.remove')" @click="removeVariant(index)">
                                <template #icon><Trash2 class="h-4 w-4" /></template>
                            </Button>
                        </Tooltip>
                    </template>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <Field :label="t('admin.common.name')" required :error="err(`variants.${index}.name`)">
                            <Input v-model:value="variant.name" :placeholder="t('admin.products.variant_hint')" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.common.sku')" required :error="err(`variants.${index}.sku`)">
                            <Input v-model:value="variant.sku" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.products.barcode')" :error="err(`variants.${index}.barcode`)">
                            <Input v-model:value="variant.barcode" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.products.cost_price')" :error="err(`variants.${index}.cost_price`)">
                            <InputNumber v-model:value="variant.cost_price" :step="0.01" :min="0" class="w-full" />
                        </Field>
                        <Field :label="t('admin.common.price')" required :error="err(`variants.${index}.price`)">
                            <InputNumber v-model:value="variant.price" :step="0.01" :min="0" class="w-full" />
                        </Field>
                        <Field :label="t('admin.products.sale_price')" :error="err(`variants.${index}.sale_price`)">
                            <InputNumber v-model:value="variant.sale_price" :step="0.01" :min="0" class="w-full" />
                        </Field>
                        <Field
                            :label="t('admin.common.low_stock_threshold')"
                            :help="t('admin.help.low_stock_threshold')"
                            :error="err(`variants.${index}.low_stock_threshold`)"
                        >
                            <InputNumber v-model:value="variant.low_stock_threshold" :min="0" class="w-full" />
                        </Field>
                        <Checkbox v-model:checked="variant.is_active" class="mt-6 self-start">{{ t('admin.common.active') }}</Checkbox>
                    </div>

                    <div class="mt-3">
                        <Typography.Text type="secondary" class="mb-1 block text-xs font-medium uppercase">{{
                            t('admin.products.attributes')
                        }}</Typography.Text>
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag v-for="(value, key) in variant.attributes ?? {}" :key="key" closable @close="removeAttribute(index, String(key))">
                                {{ key }}: {{ value }}
                            </Tag>
                            <Input
                                :value="attributeDrafts[index]?.key ?? ''"
                                :placeholder="t('admin.products.attr_key_hint')"
                                size="small"
                                class="!w-24"
                                @update:value="(v: string) => (attributeDrafts[index] = { key: v, value: attributeDrafts[index]?.value ?? '' })"
                            />
                            <Input
                                :value="attributeDrafts[index]?.value ?? ''"
                                :placeholder="t('admin.products.attr_value_hint')"
                                size="small"
                                class="!w-28"
                                @update:value="(v: string) => (attributeDrafts[index] = { key: attributeDrafts[index]?.key ?? '', value: v })"
                            />
                            <Button type="link" size="small" @click="addAttribute(index)">{{ t('admin.common.add') }}</Button>
                        </div>
                    </div>
                </Card>
            </Card>

            <!-- Split a plain product's stock between its new variants -->
            <Card v-if="splitting" :title="t('admin.products.split_stock')">
                <Alert type="info" show-icon :message="t('admin.products.split_stock_hint')" class="mb-3" />
                <Typography.Text v-if="err('stock_split')" type="danger" class="mb-2 block text-xs">{{ err('stock_split') }}</Typography.Text>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-start">
                                <th class="py-1 pe-3 text-start font-medium">{{ t('admin.common.warehouse') }}</th>
                                <th v-for="(variant, index) in form.variants" :key="index" class="px-2 py-1 text-start font-medium">
                                    {{ variant.name || variant.sku || `#${index + 1}` }}
                                </th>
                                <th class="py-1 ps-3 text-start font-medium">{{ t('admin.products.split_left') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="level in plainStock" :key="level.warehouse_id">
                                <td class="py-1 pe-3">{{ level.warehouse }} ({{ level.quantity }})</td>
                                <td v-for="(_, index) in form.variants" :key="index" class="px-2 py-1">
                                    <InputNumber v-model:value="stockSplit[level.warehouse_id][index]" :min="0" :precision="0" class="w-24" />
                                </td>
                                <td class="py-1 ps-3">
                                    <Typography.Text :type="splitLeft(level.warehouse_id, level.quantity) === 0 ? 'success' : 'danger'">
                                        {{ splitLeft(level.warehouse_id, level.quantity) }}
                                    </Typography.Text>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <!-- Specifications -->
            <Card :title="t('admin.nav.specifications')">
                <Typography.Text v-if="form.category_id === ''" type="secondary" class="mb-3 block">{{
                    t('admin.products.pick_category_for_specs')
                }}</Typography.Text>
                <div v-for="group in visibleGroups" :key="group.id" class="mb-5">
                    <Typography.Text type="secondary" class="mb-2 block text-xs font-semibold uppercase tracking-wide">{{
                        group.name
                    }}</Typography.Text>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="attribute in group.attributes" :key="attribute.id" class="grid grid-cols-2 gap-2">
                            <Field
                                :label="attribute.unit ? `${attribute.name} (${attribute.unit})` : attribute.name"
                                :error="err(`specifications.${attribute.id}.value`)"
                            >
                                <!--
                                    `string-mode` keeps decimals intact and hands back a string,
                                    which is what the backend validates `value` as. A plain number
                                    input used to default to step=1, so a screen size of 6.8 failed
                                    native constraint validation and silently blocked the whole form
                                    from submitting — which is what "Save does nothing on some
                                    products" actually was. InputNumber runs no native constraint
                                    validation, so decimals can't wedge the submit any more.
                                -->
                                <InputNumber
                                    v-if="attribute.type === 'number'"
                                    v-model:value="specFor(attribute.id).value"
                                    string-mode
                                    :step="0.01"
                                    class="w-full"
                                />
                                <Input v-else v-model:value="specFor(attribute.id).value" />
                            </Field>
                            <Field :label="`${attribute.name} (AR)`" :error="err(`specifications.${attribute.id}.value_ar`)">
                                <Input v-model:value="specFor(attribute.id).value_ar" dir="rtl" />
                            </Field>
                        </div>
                    </div>
                </div>
                <Typography.Text v-if="form.category_id !== '' && visibleGroups.length === 0" type="secondary">
                    {{ t('admin.products.no_specs_hint') }}
                </Typography.Text>
            </Card>

            <!-- Images (edit only) -->
            <Card v-if="isEdit" :title="t('admin.products.images')">
                <template #extra>
                    <!-- `:before-upload` returning false keeps AntD from POSTing the files
                         itself; `uploadImages` sends the whole selection through Inertia. -->
                    <Upload
                        :file-list="[]"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        :before-upload="
                            (file: File, fileList: File[]) => {
                                if (file === fileList[fileList.length - 1]) uploadImages(fileList);
                                return false;
                            }
                        "
                    >
                        <Button :loading="uploadingImages">
                            <template #icon><UploadIcon class="h-4 w-4" /></template>
                            {{ uploadingImages ? 'Uploading…' : 'Upload images' }}
                        </Button>
                    </Upload>
                </template>
                <div v-if="(product?.images.length ?? 0) > 0" class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                    <div v-for="image in product?.images" :key="image.id" class="group relative">
                        <img
                            :src="image.url"
                            alt=""
                            width="200"
                            height="200"
                            loading="lazy"
                            class="aspect-square w-full rounded-md border border-ad-line object-cover"
                        />
                        <Tag v-if="image.is_primary" color="blue" class="absolute start-1 top-1 !me-0">{{ t('admin.common.primary') }}</Tag>
                        <Tooltip :title="t('admin.products.delete_image')">
                            <Button
                                type="primary"
                                danger
                                size="small"
                                class="absolute end-1 top-1 hidden group-hover:inline-flex"
                                :aria-label="t('admin.products.delete_image')"
                                @click="deleteImage(image)"
                            >
                                <template #icon><Trash2 class="h-3.5 w-3.5" /></template>
                            </Button>
                        </Tooltip>
                    </div>
                </div>
                <Typography.Text v-else type="secondary">{{ t('admin.products.no_images') }}</Typography.Text>
            </Card>
            <Typography.Text v-else type="secondary">{{ t('admin.products.save_first') }}</Typography.Text>
        </div>

        <!-- Side column -->
        <div class="space-y-6">
            <Card :title="t('admin.common.status')">
                <Field :label="t('admin.common.status')" required :error="form.errors.status">
                    <AdminSelect v-model="form.status" :options="statuses" />
                </Field>
                <Checkbox v-model:checked="form.is_featured" class="mt-4">{{ t('admin.products.featured_product') }}</Checkbox>
                <Field
                    class="mt-4"
                    :label="t('admin.common.low_stock_threshold')"
                    :help="t('admin.help.low_stock_threshold')"
                    :error="form.errors.low_stock_threshold"
                >
                    <InputNumber v-model:value="form.low_stock_threshold" :min="0" class="w-full" />
                </Field>
            </Card>

            <Alert v-if="errorSummary.length > 0" type="error" show-icon :message="t('admin.common.fix_errors')">
                <template #description>
                    <ul class="list-inside list-disc space-y-0.5">
                        <li v-for="(message, index) in errorSummary" :key="index">{{ message }}</li>
                    </ul>
                </template>
            </Alert>

            <Button type="primary" html-type="submit" block :loading="form.processing">
                {{ form.processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create product' }}
            </Button>
        </div>
    </form>
</template>
