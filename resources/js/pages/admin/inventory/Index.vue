<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Checkbox, Input, InputNumber, Tooltip, TypographyText } from 'ant-design-vue';
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

defineProps<{
    levels: Paginator<StockRow>;
    warehouses: { id: number; name: string; code: string }[];
    filters: Record<string, unknown>;
    movementTypes: Option[];
}>();

const columns: Column[] = [
    { key: 'name', label: t('admin.common.product') },
    { key: 'sku', label: t('admin.common.sku') },
    { key: 'warehouse', label: t('admin.common.warehouse') },
    { key: 'quantity', label: t('admin.inventory.available'), help: t('admin.help.stock_available') },
    { key: 'reserved', label: t('admin.inventory.reserved'), help: t('admin.help.stock_reserved') },
    { key: 'damaged', label: t('admin.common.damaged'), help: t('admin.help.stock_damaged') },
    { key: 'incoming', label: t('admin.inventory.incoming'), help: t('admin.help.stock_incoming') },
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
    from_damaged: false,
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
            <Link :href="route('admin.inventory.movements')">
                <Button>{{ t('admin.inventory.movement_ledger') }}</Button>
            </Link>
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
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-quantity="{ row }">
            <TypographyText :type="row.quantity <= 0 ? 'danger' : row.low_stock ? 'warning' : undefined" :strong="row.quantity <= 0 || row.low_stock">
                {{ row.quantity }}
            </TypographyText>
            <TypographyText v-if="row.low_stock && row.quantity > 0" type="warning" class="ms-1 text-[10px] uppercase">
                {{ t('admin.inventory.low') }}
            </TypographyText>
        </template>
        <template #row-actions="{ row }">
            <Tooltip :title="t('admin.inventory.adjust_stock')">
                <Button type="text" size="small" :aria-label="t('admin.inventory.adjust_stock')" @click="openAdjust(row)">
                    <template #icon><PencilRuler class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
            <Tooltip :title="t('admin.inventory.transfer')">
                <Button type="text" size="small" :aria-label="t('admin.inventory.transfer')" @click="openTransfer(row)">
                    <template #icon><ArrowLeftRight class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
        </template>
    </DataTable>

    <Pagination :paginator="levels" />

    <!-- Adjust stock -->
    <FormModal :open="adjustOpen" :title="t('admin.inventory.adjust_stock')" @close="adjustOpen = false">
        <form class="grid gap-4" @submit.prevent="submitAdjust">
            <TypographyText type="secondary">{{ adjustForm.label }}</TypographyText>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.change')" required :hint="t('admin.inventory.adjust_hint')" :error="adjustForm.errors.quantity_change">
                    <!-- No `:min` — adjustments are signed, negative values decrease stock. -->
                    <InputNumber v-model:value="adjustForm.quantity_change" class="w-full" />
                </Field>
                <Field :label="t('admin.common.type')" required :error="adjustForm.errors.type">
                    <AdminSelect v-model="adjustForm.type" :options="movementTypes" />
                </Field>
            </div>
            <Checkbox v-if="adjustForm.type === 'supplier_return'" v-model:checked="adjustForm.from_damaged">
                {{ t('admin.inventory.return_damaged') }}
            </Checkbox>
            <Field :label="t('admin.common.reason')" required :error="adjustForm.errors.reason">
                <Input v-model:value="adjustForm.reason" :placeholder="t('admin.inventory.adjust_reason_hint')" />
            </Field>
            <div class="flex justify-end gap-3">
                <Button @click="adjustOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="adjustForm.processing">{{ t('admin.inventory.apply_adjustment') }}</Button>
            </div>
        </form>
    </FormModal>

    <!-- Transfer stock -->
    <FormModal :open="transferOpen" :title="t('admin.inventory.transfer_stock')" @close="transferOpen = false">
        <form class="grid gap-4" @submit.prevent="submitTransfer">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.inventory.from_warehouse')" required :error="transferForm.errors.from_warehouse_id">
                    <AdminSelect
                        v-model="transferForm.from_warehouse_id"
                        :placeholder="t('admin.common.choose')"
                        :options="warehouses.map((w) => ({ value: w.id, label: w.name }))"
                    />
                </Field>
                <Field :label="t('admin.inventory.to_warehouse')" required :error="transferForm.errors.to_warehouse_id">
                    <AdminSelect
                        v-model="transferForm.to_warehouse_id"
                        :placeholder="t('admin.common.choose')"
                        :options="warehouses.filter((w) => w.id !== transferForm.from_warehouse_id).map((w) => ({ value: w.id, label: w.name }))"
                    />
                </Field>
            </div>

            <div v-for="(item, index) in transferForm.items" :key="index" class="flex items-end gap-3">
                <TypographyText class="flex-1 text-sm">{{ item.label }}</TypographyText>
                <Field :label="t('admin.common.quantity')">
                    <InputNumber v-model:value="item.quantity" :min="1" class="w-24" />
                </Field>
            </div>
            <TypographyText v-if="transferForm.errors.items" type="danger" class="text-xs">{{ transferForm.errors.items }}</TypographyText>

            <Field :label="t('admin.common.notes')" :error="transferForm.errors.notes">
                <Input v-model:value="transferForm.notes" />
            </Field>
            <div class="flex justify-end gap-3">
                <Button @click="transferOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="transferForm.processing" :disabled="transferForm.items.length === 0">
                    {{ t('admin.inventory.transfer') }}
                </Button>
            </div>
        </form>
    </FormModal>
</template>
