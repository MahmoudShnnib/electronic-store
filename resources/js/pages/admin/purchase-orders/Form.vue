<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import AdminSelect from '@/components/admin/AdminSelect.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { formatMoney } from '@/lib/money';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Button, Card, InputNumber, Textarea, Tooltip, Typography } from 'ant-design-vue';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { Text } = Typography;

const { t } = useI18n();
const page = usePage();

/** The only figures the browser computes itself, so the only ones it formats. */
function money(amount: number): string {
    return formatMoney(amount, (page.props.locale as string) ?? 'en');
}

interface VariantOption {
    id: number;
    name: string;
    sku: string;
    cost_price: number;
}

interface ProductOption {
    id: number;
    name: string;
    sku: string;
    cost_price: number;
    variants: VariantOption[];
}

interface PurchaseOrderPayload {
    id: number;
    po_number: string;
    supplier_id: number;
    warehouse_id: number;
    discount: number;
    shipping: number;
    expected_at: string | null;
    notes: string | null;
    items: { product_id: number; product_variant_id: number | null; quantity: number; unit_cost: number }[];
}

const props = defineProps<{
    purchaseOrder: PurchaseOrderPayload | null;
    /** A reorder draft from the low-stock list: supplier and lines, all still editable. */
    draft?: {
        supplier_id: number | null;
        warehouse_id: number | null;
        items: { product_id: number; product_variant_id: number | null; quantity: number; unit_cost: number }[];
    } | null;
    suppliers: { id: number; company_name: string }[];
    warehouses: { id: number; name: string }[];
    products: ProductOption[];
}>();

interface ItemRow {
    key: string;
    product_id: number | '';
    product_variant_id: number | null;
    quantity: number;
    unit_cost: number;
}

const isEditing = props.purchaseOrder !== null;

const form = useForm({
    supplier_id: (props.purchaseOrder?.supplier_id ?? props.draft?.supplier_id ?? '') as number | '',
    warehouse_id: (props.purchaseOrder?.warehouse_id ?? props.draft?.warehouse_id ?? '') as number | '',
    discount: props.purchaseOrder?.discount ?? 0,
    shipping: props.purchaseOrder?.shipping ?? 0,
    expected_at: props.purchaseOrder?.expected_at ?? '',
    notes: props.purchaseOrder?.notes ?? '',
    items: (props.purchaseOrder?.items ?? props.draft?.items ?? []).map((item, index) => ({
        key: `existing-${index}`,
        ...item,
    })) as ItemRow[],
});

/**
 * The server reports a line's problem as `items.<index>.<field>`; without this
 * a refused cost or variant failed the save with nothing on screen.
 */
function lineError(index: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`items.${index}.${field}`];
}

/**
 * Every purchasable line as a flat list — "Product (SKU)" for simple products,
 * "Product — Variant (SKU)" for each variant. The old <optgroup> headings only
 * repeated the product name that each option already carries.
 */
const productOptions = computed(() =>
    props.products.flatMap((product) =>
        product.variants.length === 0
            ? [{ value: String(product.id), label: `${product.name} (${product.sku})` }]
            : product.variants.map((variant) => ({
                  value: `${product.id}:${variant.id}`,
                  label: `${product.name} — ${variant.name} (${variant.sku})`,
              })),
    ),
);

function addItem(): void {
    form.items.push({
        key: Math.random().toString(36).slice(2),
        product_id: '',
        product_variant_id: null,
        quantity: 1,
        unit_cost: 0,
    });
}

function removeItem(index: number): void {
    form.items.splice(index, 1);
}

/** Options are encoded "productId" or "productId:variantId". */
function selectionValue(item: ItemRow): string {
    if (item.product_id === '') return '';

    return item.product_variant_id !== null ? `${item.product_id}:${item.product_variant_id}` : String(item.product_id);
}

function applySelection(item: ItemRow, encoded: string): void {
    if (encoded === '') {
        item.product_id = '';
        item.product_variant_id = null;

        return;
    }

    const [productId, variantId] = encoded.split(':').map(Number);

    item.product_id = productId;
    item.product_variant_id = Number.isFinite(variantId) ? variantId : null;

    const product = props.products.find((p) => p.id === productId);

    if (product) {
        const variant = product.variants.find((v) => v.id === item.product_variant_id);
        // A variant saved without its own cost (0) uses the product's, like everywhere else:
        // the PO line price becomes the cost of the received units.
        item.unit_cost = variant?.cost_price || product.cost_price;
    }
}

const subtotal = computed(() => form.items.reduce((sum, item) => sum + item.quantity * (item.unit_cost || 0), 0));

const total = computed(() => Math.max(0, subtotal.value - (form.discount || 0)) + (form.shipping || 0));

function submit(): void {
    const transformed = form.transform((data) => ({
        ...data,
        expected_at: data.expected_at === '' ? null : data.expected_at,
        items: data.items.map(({ key: _key, ...item }) => item),
    }));

    if (isEditing && props.purchaseOrder) {
        transformed.patch(route('admin.purchase-orders.update', props.purchaseOrder.id));
    } else {
        transformed.post(route('admin.purchase-orders.store'));
    }
}
</script>

<template>
    <Head :title="isEditing ? `${t('admin.common.edit')} ${props.purchaseOrder?.po_number}` : t('admin.purchase_orders.new')" />

    <PageHeader :title="isEditing ? `${t('admin.common.edit')} ${props.purchaseOrder?.po_number}` : t('admin.purchase_orders.new')">
        <template #actions>
            <Link :href="route('admin.purchase-orders.index')">
                <Button>{{ t('admin.common.back_to_list') }}</Button>
            </Link>
        </template>
    </PageHeader>

    <form class="grid gap-6 xl:grid-cols-3" @submit.prevent="submit">
        <div class="space-y-6 xl:col-span-2">
            <Card :title="t('admin.purchase_orders.details')" size="small">
                <div class="grid gap-4 sm:grid-cols-3">
                    <Field :label="t('admin.common.supplier')" required :error="form.errors.supplier_id">
                        <AdminSelect
                            v-model="form.supplier_id"
                            :placeholder="t('admin.common.choose')"
                            :options="suppliers.map((s) => ({ value: s.id, label: s.company_name }))"
                        />
                    </Field>
                    <Field :label="t('admin.common.destination_warehouse')" required :error="form.errors.warehouse_id">
                        <AdminSelect
                            v-model="form.warehouse_id"
                            :placeholder="t('admin.common.choose')"
                            :options="warehouses.map((w) => ({ value: w.id, label: w.name }))"
                        />
                    </Field>
                    <Field :label="t('admin.purchase_orders.expected_date')" :error="form.errors.expected_at">
                        <AdminDateInput v-model="form.expected_at" />
                    </Field>
                </div>
                <Field class="mt-4" :label="t('admin.common.notes')" :error="form.errors.notes">
                    <Textarea v-model:value="form.notes" :rows="2" />
                </Field>
            </Card>

            <Card :title="t('admin.common.items')" size="small">
                <template #extra>
                    <Button @click="addItem">
                        <template #icon><Plus class="h-4 w-4" /></template>
                        {{ t('admin.purchase_orders.add_item') }}
                    </Button>
                </template>
                <Text v-if="form.errors.items" type="danger" class="mb-2 block !text-xs">{{ form.errors.items }}</Text>

                <div v-for="(item, index) in form.items" :key="item.key" class="mb-3 grid items-end gap-3 sm:grid-cols-[1fr_100px_130px_110px_36px]">
                    <Field :label="t('admin.common.product')" :error="lineError(index, 'product_id') ?? lineError(index, 'product_variant_id')">
                        <AdminSelect
                            :model-value="selectionValue(item)"
                            :placeholder="t('admin.common.choose')"
                            :options="productOptions"
                            @update:model-value="applySelection(item, String($event))"
                        />
                    </Field>
                    <Field :label="t('admin.common.qty')" :error="lineError(index, 'quantity')">
                        <InputNumber v-model:value="item.quantity" :min="1" class="w-full" />
                    </Field>
                    <Field :label="t('admin.common.unit_cost')" :error="lineError(index, 'unit_cost')">
                        <InputNumber v-model:value="item.unit_cost" :min="0" :step="0.01" :precision="2" class="w-full" />
                    </Field>
                    <p class="pb-2 text-sm font-medium text-ad-text-2">
                        {{ money(item.quantity * (item.unit_cost || 0)) }}
                    </p>
                    <Tooltip :title="t('admin.common.remove')">
                        <Button type="text" size="small" danger class="mb-1.5" :aria-label="t('admin.common.remove')" @click="removeItem(index)">
                            <template #icon><Trash2 class="h-4 w-4" /></template>
                        </Button>
                    </Tooltip>
                </div>

                <Text v-if="form.items.length === 0" type="secondary" class="!text-sm">{{ t('admin.purchase_orders.no_items') }}</Text>
            </Card>
        </div>

        <div class="space-y-6">
            <Card :title="t('admin.purchase_orders.totals')" size="small">
                <div class="grid gap-3">
                    <Field :label="t('admin.common.discount')" :error="form.errors.discount">
                        <InputNumber v-model:value="form.discount" :min="0" :step="0.01" :precision="2" class="w-full" />
                    </Field>
                    <Field :label="t('admin.common.shipping')" :error="form.errors.shipping">
                        <InputNumber v-model:value="form.shipping" :min="0" :step="0.01" :precision="2" class="w-full" />
                    </Field>
                </div>
                <dl class="mt-4 space-y-1 border-t border-ad-line pt-3 text-sm">
                    <div class="flex justify-between text-base font-semibold text-ad-text">
                        <dt>{{ t('admin.common.total') }}</dt>
                        <dd>{{ money(total) }}</dd>
                    </div>
                </dl>
                <Text type="secondary" class="mt-2 block !text-xs">{{ t('admin.purchase_orders.totals_hint') }}</Text>
            </Card>

            <Button type="primary" html-type="submit" block :loading="form.processing" :disabled="form.items.length === 0">
                {{ form.processing ? t('admin.common.saving') : isEditing ? t('admin.common.save') : t('admin.purchase_orders.new') }}
            </Button>
        </div>
    </form>
</template>
