<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { btnPrimary, btnSecondary, checkboxClass, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface WarehouseRow extends Record<string, unknown> {
    id: number;
    name: string;
    code: string;
    address: string | null;
    is_default: boolean;
    is_active: boolean;
    total_quantity: number;
}

defineProps<{
    warehouses: WarehouseRow[];
}>();

const columns: Column[] = [
    { key: 'name', label: t('admin.common.warehouse') },
    { key: 'code', label: t('admin.common.code') },
    { key: 'address', label: t('admin.common.address') },
    { key: 'total_quantity', label: t('admin.common.units_on_hand') },
    { key: 'is_active', label: t('admin.common.status') },
];

const modalOpen = ref(false);
const editing = ref<WarehouseRow | null>(null);
const deleting = ref<WarehouseRow | null>(null);

const blank = {
    name: '',
    code: '',
    address: '',
    is_default: false,
    is_active: true,
};

const form = useForm({ ...blank });

function openCreate(): void {
    editing.value = null;
    // Inertia 2 promotes the submitted data to the form's defaults on success,
    // so a bare reset() re-opened "New" pre-filled with the last edited record.
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(warehouse: WarehouseRow): void {
    editing.value = warehouse;
    form.clearErrors();
    form.name = warehouse.name;
    form.code = warehouse.code;
    form.address = warehouse.address ?? '';
    form.is_default = warehouse.is_default;
    form.is_active = warehouse.is_active;
    modalOpen.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };

    if (editing.value) {
        form.patch(route('admin.warehouses.update', editing.value.id), options);
    } else {
        form.post(route('admin.warehouses.store'), options);
    }
}

function destroy(): void {
    if (!deleting.value) return;

    router.delete(route('admin.warehouses.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.warehouses.title')" />

    <PageHeader :title="t('admin.warehouses.title')">
        <template #actions>
            <button type="button" :class="btnPrimary" @click="openCreate"><Plus class="h-4 w-4" />{{ t('admin.warehouses.new') }}</button>
        </template>
    </PageHeader>

    <DataTable :columns="columns" :rows="warehouses">
        <template #cell-name="{ row }">
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</span>
            <span
                v-if="row.is_default"
                class="ms-2 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium uppercase text-blue-600 dark:bg-blue-950 dark:text-blue-300"
                >{{ t('admin.common.default') }}</span
            >
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
        </template>
        <template #row-actions="{ row }">
            <button type="button" class="rounded p-1.5 text-gray-400 hover:text-blue-600" :title="t('admin.common.edit')" @click="openEdit(row)">
                <Pencil class="h-4 w-4" />
            </button>
            <button
                v-if="!row.is_default"
                type="button"
                class="rounded p-1.5 text-gray-400 hover:text-red-600"
                :title="t('admin.common.delete')"
                @click="deleting = row"
            >
                <Trash2 class="h-4 w-4" />
            </button>
        </template>
    </DataTable>

    <FormModal :open="modalOpen" :title="editing ? t('admin.warehouses.edit') : t('admin.warehouses.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.name')" required :error="form.errors.name">
                    <input v-model="form.name" type="text" :class="inputClass" />
                </Field>
                <Field :label="t('admin.common.code')" required :hint="t('admin.warehouses.code_hint')" :error="form.errors.code">
                    <input v-model="form.code" type="text" :class="inputClass" dir="ltr" />
                </Field>
            </div>
            <Field :label="t('admin.common.address')" :error="form.errors.address">
                <textarea v-model="form.address" rows="2" :class="inputClass" />
            </Field>
            <div class="flex gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="form.is_default" type="checkbox" :class="checkboxClass" />{{ t('admin.warehouses.default_warehouse') }}</label
                >
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="form.is_active" type="checkbox" :class="checkboxClass" />{{ t('admin.common.active') }}</label
                >
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="modalOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="form.processing" :class="btnPrimary">{{ t('admin.common.save') }}</button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('admin.warehouses.delete_title')"
        :message="t('admin.warehouses.delete_message', { name: deleting?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
