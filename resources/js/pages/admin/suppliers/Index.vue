<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Paginator } from '@/components/admin/types';
import { btnPrimary, btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface SupplierRow extends Record<string, unknown> {
    id: number;
    company_name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    tax_number: string | null;
    status: string;
    notes: string | null;
    products_count: number;
    purchase_orders_count: number;
}

defineProps<{
    suppliers: Paginator<SupplierRow>;
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'company_name', label: t('admin.common.company') },
    { key: 'contact_person', label: t('admin.common.contact') },
    { key: 'email', label: t('admin.common.email') },
    { key: 'phone', label: t('admin.common.phone') },
    { key: 'purchase_orders_count', label: t('admin.suppliers.pos') },
    { key: 'status', label: t('admin.common.status') },
];

const modalOpen = ref(false);
const editing = ref<SupplierRow | null>(null);
const deleting = ref<SupplierRow | null>(null);

const form = useForm({
    company_name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    tax_number: '',
    status: 'active',
    notes: '',
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(supplier: SupplierRow): void {
    editing.value = supplier;
    form.clearErrors();
    form.company_name = supplier.company_name;
    form.contact_person = supplier.contact_person ?? '';
    form.email = supplier.email ?? '';
    form.phone = supplier.phone ?? '';
    form.address = supplier.address ?? '';
    form.tax_number = supplier.tax_number ?? '';
    form.status = supplier.status;
    form.notes = supplier.notes ?? '';
    modalOpen.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };

    if (editing.value) {
        form.patch(route('admin.suppliers.update', editing.value.id), options);
    } else {
        form.post(route('admin.suppliers.store'), options);
    }
}

function destroy(): void {
    if (!deleting.value) return;

    router.delete(route('admin.suppliers.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.suppliers.title')" />

    <PageHeader :title="t('admin.suppliers.title')" :subtitle="t('admin.suppliers.count', { count: suppliers.total })">
        <template #actions>
            <button type="button" :class="btnPrimary" @click="openCreate"><Plus class="h-4 w-4" />{{ t('admin.suppliers.new') }}</button>
        </template>
    </PageHeader>

    <FilterBar
        :url="route('admin.suppliers.index')"
        :filters="filters"
        :search-placeholder="t('admin.suppliers.search')"
        :selects="[
            {
                key: 'status',
                label: t('admin.common.all_statuses'),
                options: [
                    { value: 'active', label: t('admin.common.active') },
                    { value: 'inactive', label: t('admin.common.inactive') },
                ],
            },
        ]"
    />

    <DataTable :columns="columns" :rows="suppliers.data">
        <template #cell-company_name="{ row }">
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ row.company_name }}</span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" />
        </template>
        <template #row-actions="{ row }">
            <button type="button" class="rounded p-1.5 text-gray-400 hover:text-blue-600" :title="t('admin.common.edit')" @click="openEdit(row)">
                <Pencil class="h-4 w-4" />
            </button>
            <button type="button" class="rounded p-1.5 text-gray-400 hover:text-red-600" :title="t('admin.common.delete')" @click="deleting = row">
                <Trash2 class="h-4 w-4" />
            </button>
        </template>
    </DataTable>

    <Pagination :links="suppliers.links" :total="suppliers.total" :from="suppliers.from" :to="suppliers.to" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.suppliers.edit') : t('admin.suppliers.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field :label="t('admin.suppliers.company_name')" required :error="form.errors.company_name">
                <input v-model="form.company_name" type="text" :class="inputClass" />
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.suppliers.contact_person')" :error="form.errors.contact_person">
                    <input v-model="form.contact_person" type="text" :class="inputClass" />
                </Field>
                <Field :label="t('admin.common.tax_number')" :error="form.errors.tax_number">
                    <input v-model="form.tax_number" type="text" :class="inputClass" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.email')" :error="form.errors.email">
                    <input v-model="form.email" type="email" :class="inputClass" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.phone')" :error="form.errors.phone">
                    <input v-model="form.phone" type="text" :class="inputClass" />
                </Field>
            </div>
            <Field :label="t('admin.common.address')" :error="form.errors.address">
                <textarea v-model="form.address" rows="2" :class="inputClass" />
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.status')" required :error="form.errors.status">
                    <select v-model="form.status" :class="inputClass">
                        <option value="active">{{ t('admin.common.active') }}</option>
                        <option value="inactive">{{ t('admin.common.inactive') }}</option>
                    </select>
                </Field>
            </div>
            <Field :label="t('admin.common.notes')" :error="form.errors.notes">
                <textarea v-model="form.notes" rows="2" :class="inputClass" />
            </Field>
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="modalOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="form.processing" :class="btnPrimary">{{ t('admin.common.save') }}</button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('admin.suppliers.delete_title')"
        :message="t('admin.suppliers.delete_message', { name: deleting?.company_name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
