<script setup lang="ts">
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { btnPrimary, btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

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

const props = defineProps<{
    purchaseOrder: null;
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

const form = useForm({
    supplier_id: '' as number | '',
    warehouse_id: '' as number | '',
    discount: 0,
    tax: 0,
    shipping: 0,
    expected_at: '',
    notes: '',
    items: [] as ItemRow[],
});

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
        item.unit_cost = variant?.cost_price ?? product.cost_price;
    }
}

const subtotal = computed(() => form.items.reduce((sum, item) => sum + item.quantity * (item.unit_cost || 0), 0));

const total = computed(() => Math.max(0, subtotal.value - (form.discount || 0)) + (form.tax || 0) + (form.shipping || 0));

function submit(): void {
    form.transform((data) => ({
        ...data,
        expected_at: data.expected_at === '' ? null : data.expected_at,
        items: data.items.map(({ key: _key, ...item }) => item),
    })).post(route('admin.purchase-orders.store'));
}
</script>

<template>
    <Head :title="t('admin.purchase_orders.new')" />

    <PageHeader :title="t('admin.purchase_orders.new')">
        <template #actions>
            <Link :href="route('admin.purchase-orders.index')" :class="btnSecondary">{{ t('admin.common.back_to_list') }}</Link>
        </template>
    </PageHeader>

    <form class="grid gap-6 xl:grid-cols-3" @submit.prevent="submit">
        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.purchase_orders.details') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Field :label="t('admin.common.supplier')" required :error="form.errors.supplier_id">
                        <select v-model="form.supplier_id" :class="inputClass">
                            <option value="" disabled>{{ t('admin.common.choose') }}</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name }}</option>
                        </select>
                    </Field>
                    <Field :label="t('admin.common.destination_warehouse')" required :error="form.errors.warehouse_id">
                        <select v-model="form.warehouse_id" :class="inputClass">
                            <option value="" disabled>{{ t('admin.common.choose') }}</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </Field>
                    <Field :label="t('admin.purchase_orders.expected_date')" :error="form.errors.expected_at">
                        <input v-model="form.expected_at" type="date" :class="inputClass" />
                    </Field>
                </div>
                <Field class="mt-4" :label="t('admin.common.notes')" :error="form.errors.notes">
                    <textarea v-model="form.notes" rows="2" :class="inputClass" />
                </Field>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.items') }}</h2>
                    <button type="button" :class="btnSecondary" @click="addItem">
                        <Plus class="h-4 w-4" />{{ t('admin.purchase_orders.add_item') }}
                    </button>
                </div>
                <p v-if="form.errors.items" class="mb-2 text-xs text-red-600">{{ form.errors.items }}</p>

                <div v-for="(item, index) in form.items" :key="item.key" class="mb-3 grid items-end gap-3 sm:grid-cols-[1fr_100px_130px_110px_36px]">
                    <Field :label="t('admin.common.product')">
                        <select
                            :value="selectionValue(item)"
                            :class="inputClass"
                            @change="applySelection(item, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="" disabled>{{ t('admin.common.choose') }}</option>
                            <template v-for="product in products" :key="product.id">
                                <option v-if="product.variants.length === 0" :value="String(product.id)">
                                    {{ product.name }} ({{ product.sku }})
                                </option>
                                <optgroup v-else :label="product.name">
                                    <option v-for="variant in product.variants" :key="variant.id" :value="`${product.id}:${variant.id}`">
                                        {{ product.name }} — {{ variant.name }} ({{ variant.sku }})
                                    </option>
                                </optgroup>
                            </template>
                        </select>
                    </Field>
                    <Field :label="t('admin.common.qty')">
                        <input v-model.number="item.quantity" type="number" min="1" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.common.unit_cost')">
                        <input v-model.number="item.unit_cost" type="number" step="0.01" min="0" :class="inputClass" />
                    </Field>
                    <p class="pb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ (item.quantity * (item.unit_cost || 0)).toFixed(2) }}
                    </p>
                    <button type="button" class="mb-1.5 text-red-500 hover:text-red-700" :title="t('admin.common.remove')" @click="removeItem(index)">
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>

                <p v-if="form.items.length === 0" class="text-sm text-gray-400">{{ t('admin.purchase_orders.no_items') }}</p>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.purchase_orders.totals') }}</h2>
                <div class="grid gap-3">
                    <Field :label="t('admin.common.discount')" :error="form.errors.discount">
                        <input v-model.number="form.discount" type="number" step="0.01" min="0" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.common.tax')" :error="form.errors.tax">
                        <input v-model.number="form.tax" type="number" step="0.01" min="0" :class="inputClass" />
                    </Field>
                    <Field :label="t('admin.common.shipping')" :error="form.errors.shipping">
                        <input v-model.number="form.shipping" type="number" step="0.01" min="0" :class="inputClass" />
                    </Field>
                </div>
                <dl class="mt-4 space-y-1 border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                    <div class="flex justify-between text-gray-500 dark:text-gray-400">
                        <dt>{{ t('admin.common.subtotal') }}</dt>
                        <dd>{{ subtotal.toFixed(2) }}</dd>
                    </div>
                    <div class="flex justify-between text-base font-semibold text-gray-900 dark:text-gray-100">
                        <dt>{{ t('admin.common.total') }}</dt>
                        <dd>{{ total.toFixed(2) }}</dd>
                    </div>
                </dl>
                <p class="mt-2 text-xs text-gray-400">{{ t('admin.purchase_orders.totals_hint') }}</p>
            </section>

            <button type="submit" :disabled="form.processing || form.items.length === 0" :class="btnPrimary" class="w-full justify-center">
                {{ form.processing ? 'Saving…' : 'Create purchase order' }}
            </button>
        </div>
    </form>
</template>
