<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import type { Option, Paginator } from '@/components/admin/types';
import { btnPrimary, btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, PencilRuler } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface StockRow extends Record<string, unknown> {
    id: number;
    product_id: number;
    variant_id: number | null;
    name: string;
    sku: string;
    warehouse: string;
    warehouse_id: number;
    quantity: number;
    reserved: number;
    damaged: number;
    incoming: number;
    low_stock: boolean;
}

const props = defineProps<{
    levels: Paginator<StockRow>;
    warehouses: { id: number; name: string; code: string }[];
    filters: Record<string, unknown>;
    movementTypes: Option[];
}>();

const columns: Column[] = [
    { key: 'name', label: t('admin.common.product') },
    { key: 'sku', label: t('admin.common.sku') },
    { key: 'warehouse', label: t('admin.common.warehouse') },
    { key: 'quantity', label: t('admin.inventory.available') },
    { key: 'reserved', label: t('admin.inventory.reserved') },
    { key: 'damaged', label: t('admin.common.damaged') },
    { key: 'incoming', label: t('admin.inventory.incoming') },
];

// -- Adjust modal -------------------------------------------------------------

const adjustOpen = ref(false);

const adjustForm = useForm({
    warehouse_id: 0,
    product_id: 0,
    product_variant_id: null as number | null,
    quantity_change: 0,
    type: 'manual_increase',
    reason: '',
    label: '',
});

function openAdjust(row: StockRow): void {
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.warehouse_id = row.warehouse_id;
    adjustForm.product_id = row.product_id;
    adjustForm.product_variant_id = row.variant_id;
    adjustForm.label = `${row.name} @ ${row.warehouse}`;
    adjustOpen.value = true;
}

function submitAdjust(): void {
    adjustForm
        .transform(({ label: _label, ...data }) => data)
        .post(route('admin.inventory.adjust'), {
            preserveScroll: true,
            onSuccess: () => (adjustOpen.value = false),
        });
}

// -- Transfer modal -----------------------------------------------------------

const transferOpen = ref(false);

interface TransferItem {
    product_id: number;
    product_variant_id: number | null;
    quantity: number;
    label: string;
}

const transferForm = useForm({
    from_warehouse_id: 0,
    to_warehouse_id: 0,
    notes: '',
    items: [] as TransferItem[],
});

function openTransfer(row?: StockRow): void {
    transferForm.reset();
    transferForm.clearErrors();

    if (row) {
        transferForm.from_warehouse_id = row.warehouse_id;
        transferForm.items = [{ product_id: row.product_id, product_variant_id: row.variant_id, quantity: 1, label: row.name }];
    }

    transferOpen.value = true;
}

function submitTransfer(): void {
    transferForm
        .transform((data) => ({
            ...data,
            items: data.items.map(({ label: _label, ...item }) => item),
        }))
        .post(route('admin.inventory.transfer'), {
            preserveScroll: true,
            onSuccess: () => (transferOpen.value = false),
        });
}
</script>

<template>
    <Head :title="t('admin.inventory.title')" />

    <PageHeader :title="t('admin.inventory.title')" :subtitle="t('admin.inventory.count', { count: levels.total })">
        <template #actions>
            <Link :href="route('admin.inventory.movements')" :class="btnSecondary">{{ t('admin.inventory.movement_ledger') }}</Link>
        </template>
    </PageHeader>

    <FilterBar
        :url="route('admin.inventory.index')"
        :filters="filters"
        :search-placeholder="t('admin.inventory.search')"
        :selects="[
            {
                key: 'warehouse_id',
                label: t('admin.common.all_warehouses'),
                options: warehouses.map((w) => ({ value: String(w.id), label: w.name })),
            },
            {
                key: 'filter',
                label: t('admin.common.all_stock'),
                options: [
                    { value: 'low', label: t('admin.common.low_stock') },
                    { value: 'out', label: t('admin.common.out_of_stock') },
                ],
            },
        ]"
    />

    <DataTable :columns="columns" :rows="levels.data">
        <template #cell-name="{ row }">
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</span>
        </template>
        <template #cell-quantity="{ row }">
            <span
                :class="
                    row.quantity <= 0
                        ? 'font-semibold text-red-600'
                        : row.low_stock
                          ? 'font-semibold text-amber-600'
                          : 'text-gray-900 dark:text-gray-100'
                "
            >
                {{ row.quantity }}
            </span>
            <span v-if="row.low_stock && row.quantity > 0" class="ms-1 text-[10px] uppercase text-amber-500">{{ t('admin.inventory.low') }}</span>
        </template>
        <template #row-actions="{ row }">
            <button
                type="button"
                class="rounded p-1.5 text-gray-400 hover:text-blue-600"
                :title="t('admin.inventory.adjust_stock')"
                @click="openAdjust(row)"
            >
                <PencilRuler class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-gray-400 hover:text-blue-600"
                :title="t('admin.inventory.transfer')"
                @click="openTransfer(row)"
            >
                <ArrowLeftRight class="h-4 w-4" />
            </button>
        </template>
    </DataTable>

    <Pagination :links="levels.links" :total="levels.total" :from="levels.from" :to="levels.to" />

    <!-- Adjust stock -->
    <FormModal :open="adjustOpen" :title="t('admin.inventory.adjust_stock')" @close="adjustOpen = false">
        <form class="grid gap-4" @submit.prevent="submitAdjust">
            <p class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ adjustForm.label }}</p>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.change')" required :hint="t('admin.inventory.adjust_hint')" :error="adjustForm.errors.quantity_change">
                    <input v-model.number="adjustForm.quantity_change" type="number" :class="inputClass" />
                </Field>
                <Field :label="t('admin.common.type')" required :error="adjustForm.errors.type">
                    <select v-model="adjustForm.type" :class="inputClass">
                        <option v-for="t in movementTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </Field>
            </div>
            <Field :label="t('admin.common.reason')" required :error="adjustForm.errors.reason">
                <input v-model="adjustForm.reason" type="text" :placeholder="t('admin.inventory.adjust_reason_hint')" :class="inputClass" />
            </Field>
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="adjustOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="adjustForm.processing" :class="btnPrimary">{{ t('admin.inventory.apply_adjustment') }}</button>
            </div>
        </form>
    </FormModal>

    <!-- Transfer stock -->
    <FormModal :open="transferOpen" :title="t('admin.inventory.transfer_stock')" @close="transferOpen = false">
        <form class="grid gap-4" @submit.prevent="submitTransfer">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.inventory.from_warehouse')" required :error="transferForm.errors.from_warehouse_id">
                    <select v-model.number="transferForm.from_warehouse_id" :class="inputClass">
                        <option :value="0" disabled>{{ t('admin.common.choose') }}</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </Field>
                <Field :label="t('admin.inventory.to_warehouse')" required :error="transferForm.errors.to_warehouse_id">
                    <select v-model.number="transferForm.to_warehouse_id" :class="inputClass">
                        <option :value="0" disabled>{{ t('admin.common.choose') }}</option>
                        <option v-for="w in warehouses.filter((w) => w.id !== transferForm.from_warehouse_id)" :key="w.id" :value="w.id">
                            {{ w.name }}
                        </option>
                    </select>
                </Field>
            </div>

            <div v-for="(item, index) in transferForm.items" :key="index" class="flex items-end gap-3">
                <p class="flex-1 text-sm text-gray-700 dark:text-gray-300">{{ item.label }}</p>
                <Field :label="t('admin.common.quantity')">
                    <input v-model.number="item.quantity" type="number" min="1" class="w-24" :class="inputClass" />
                </Field>
            </div>
            <p v-if="transferForm.errors.items" class="text-xs text-red-600">{{ transferForm.errors.items }}</p>

            <Field :label="t('admin.common.notes')" :error="transferForm.errors.notes">
                <input v-model="transferForm.notes" type="text" :class="inputClass" />
            </Field>
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="transferOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="transferForm.processing || transferForm.items.length === 0" :class="btnPrimary">
                    {{ t('admin.inventory.transfer') }}
                </button>
            </div>
        </form>
    </FormModal>
</template>
