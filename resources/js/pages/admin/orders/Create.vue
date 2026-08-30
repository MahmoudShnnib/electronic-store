<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
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
    price: number;
    price_formatted: string;
}

interface ProductOption {
    id: number;
    name: string;
    sku: string;
    has_variants: boolean;
    price: number;
    price_formatted: string;
    variants: VariantOption[];
}

const props = defineProps<{
    products: ProductOption[];
    cities: { en: string; ar: string }[];
    paymentMethods: { value: string; label: string }[];
}>();

interface ItemRow {
    key: string;
    product_id: number | '';
    product_variant_id: number | null;
    quantity: number;
}

let nextKey = 0;

const form = useForm({
    customer: { name: '', email: '', phone: '' },
    shipping_address: { city: '', area: '' },
    payment_method: props.paymentMethods[0]?.value ?? 'cod',
    source: 'whatsapp',
    customer_note: '',
    items: [{ key: `row-${nextKey++}`, product_id: '', product_variant_id: null, quantity: 1 }] as ItemRow[],
});

const productOptions = computed(() => props.products.map((p) => ({ value: p.id, label: `${p.name} — ${p.sku}` })));

const cityOptions = computed(() => props.cities.map((c) => ({ value: c.en, label: c.ar })));

/** Where the order reached us — kept on the order as `placed_via`. */
const sourceOptions = computed(() => [
    { value: 'whatsapp', label: t('admin.orders.source_whatsapp') },
    { value: 'phone', label: t('admin.orders.source_phone') },
    { value: 'counter', label: t('admin.orders.source_counter') },
]);

function productFor(row: ItemRow): ProductOption | undefined {
    return props.products.find((p) => p.id === row.product_id);
}

function variantOptions(row: ItemRow): { value: number; label: string }[] {
    return (productFor(row)?.variants ?? []).map((v) => ({ value: v.id, label: `${v.name} — ${v.price_formatted}` }));
}

/** Live line total, for a sanity check before saving — the server re-prices anyway. */
function lineTotal(row: ItemRow): number {
    const product = productFor(row);

    if (!product) return 0;

    const variant = product.variants.find((v) => v.id === row.product_variant_id);

    return (variant?.price ?? product.price) * (row.quantity || 0);
}

const total = computed(() => form.items.reduce((sum, row) => sum + lineTotal(row), 0));

function addRow(): void {
    form.items.push({ key: `row-${nextKey++}`, product_id: '', product_variant_id: null, quantity: 1 });
}

function removeRow(index: number): void {
    form.items.splice(index, 1);
}

/** Changing the product invalidates whatever variant was picked for the old one. */
function onProductChange(row: ItemRow): void {
    row.product_variant_id = null;
}

const canSubmit = computed(
    () =>
        form.customer.name.trim() !== '' &&
        form.customer.phone.trim() !== '' &&
        form.shipping_address.city !== '' &&
        form.shipping_address.area.trim() !== '' &&
        form.items.length > 0 &&
        form.items.every((row) => row.product_id !== '' && row.quantity > 0),
);

function submit(): void {
    form.post(route('admin.orders.store'));
}
</script>

<template>
    <Head :title="t('admin.orders.new')" />

    <form @submit.prevent="submit">
        <PageHeader :title="t('admin.orders.new')" :subtitle="t('admin.orders.new_hint')">
            <template #actions>
                <Link :href="route('admin.orders.index')" :class="btnSecondary">{{ t('admin.common.back') }}</Link>
                <button type="submit" :disabled="form.processing || !canSubmit" :class="btnPrimary">
                    {{ form.processing ? t('admin.common.saving') : t('admin.orders.create_order') }}
                </button>
            </template>
        </PageHeader>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.orders.items') }}</h2>
                        <button type="button" :class="btnSecondary" @click="addRow"><Plus class="h-4 w-4" />{{ t('admin.common.add') }}</button>
                    </div>

                    <p v-if="form.errors.items" class="mb-3 text-sm text-red-600">{{ form.errors.items }}</p>

                    <div class="space-y-3">
                        <div v-for="(row, index) in form.items" :key="row.key" class="grid gap-2 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
                            <Field :label="t('admin.common.item')" :error="form.errors[`items.${index}.product_id`]">
                                <AdminSelect
                                    v-model="row.product_id"
                                    :placeholder="t('admin.common.choose')"
                                    :options="productOptions"
                                    @update:model-value="onProductChange(row)"
                                />
                            </Field>
                            <Field
                                v-if="productFor(row)?.has_variants"
                                :label="t('admin.products.variants')"
                                :error="form.errors[`items.${index}.product_variant_id`]"
                            >
                                <AdminSelect
                                    v-model="row.product_variant_id"
                                    :placeholder="t('admin.common.choose')"
                                    :options="variantOptions(row)"
                                />
                            </Field>
                            <Field :label="t('admin.common.qty')" :error="form.errors[`items.${index}.quantity`]">
                                <input v-model.number="row.quantity" type="number" min="1" class="w-24" :class="inputClass" />
                            </Field>
                            <button
                                type="button"
                                class="mb-1 rounded-md p-2 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                                :aria-label="t('admin.common.remove')"
                                @click="removeRow(index)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <p class="mt-4 border-t border-gray-100 pt-3 text-sm font-semibold text-gray-900 dark:border-gray-800 dark:text-gray-100">
                        {{ t('admin.common.total') }}: {{ total.toFixed(2) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400">{{ t('admin.orders.pricing_hint') }}</p>
                </section>
            </div>

            <div class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.customer') }}</h2>
                    <div class="grid gap-3">
                        <Field :label="t('admin.common.name')" required :error="form.errors['customer.name']">
                            <input v-model="form.customer.name" type="text" :class="inputClass" />
                        </Field>
                        <Field :label="t('admin.common.phone')" required :error="form.errors['customer.phone']">
                            <input v-model="form.customer.phone" type="tel" :class="inputClass" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.common.email')" :error="form.errors['customer.email']">
                            <input v-model="form.customer.email" type="email" :class="inputClass" dir="ltr" />
                        </Field>
                        <Field :label="t('admin.common.city')" required :error="form.errors['shipping_address.city']">
                            <AdminSelect v-model="form.shipping_address.city" :placeholder="t('admin.common.choose')" :options="cityOptions" />
                        </Field>
                        <Field :label="t('admin.common.area')" required :error="form.errors['shipping_address.area']">
                            <input v-model="form.shipping_address.area" type="text" :class="inputClass" />
                        </Field>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.orders.order_details') }}</h2>
                    <div class="grid gap-3">
                        <Field :label="t('admin.common.method')" :error="form.errors.payment_method">
                            <AdminSelect v-model="form.payment_method" :options="paymentMethods" />
                        </Field>
                        <Field :label="t('admin.orders.source')" :hint="t('admin.orders.source_hint')" :error="form.errors.source">
                            <AdminSelect v-model="form.source" :options="sourceOptions" />
                        </Field>
                        <Field :label="t('admin.orders.customer_note')" :error="form.errors.customer_note">
                            <textarea v-model="form.customer_note" rows="3" :class="inputClass" />
                        </Field>
                    </div>
                    <p class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-gray-950/50 dark:text-gray-400">
                        {{ t('admin.orders.unpaid_hint') }}
                    </p>
                </section>
            </div>
        </div>
    </form>
</template>
