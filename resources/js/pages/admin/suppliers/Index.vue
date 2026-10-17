<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button, Input, Textarea, Tooltip } from 'ant-design-vue';
import { BookOpenText, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
    /** What the shop owes this supplier; negative = the supplier owes the shop. */
    balance_cents: number;
    balance_formatted: string;
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
    { key: 'balance', label: t('admin.suppliers.balance'), class: '!text-end' },
    { key: 'status', label: t('admin.common.status') },
];

const modalOpen = ref(false);
const editing = ref<SupplierRow | null>(null);
const deleting = ref<SupplierRow | null>(null);

const blank = {
    company_name: '',
    contact_person: '',
    email: '',
    phone: '',
    address: '',
    tax_number: '',
    status: 'active',
    notes: '',
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
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.suppliers.new') }}
            </Button>
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
            <Link :href="route('admin.suppliers.show', row.id)" class="font-medium text-ad-text hover:text-ad-accent">{{ row.company_name }}</Link>
        </template>
        <template #cell-balance="{ row }">
            <span v-if="row.balance_cents === 0" class="text-ad-muted">—</span>
            <span v-else class="flex flex-col items-end">
                <bdi dir="ltr" class="font-medium tabular-nums" :class="row.balance_cents > 0 ? 'text-ad-danger' : 'text-ad-success'">{{
                    row.balance_formatted
                }}</bdi>
                <span class="text-xs text-ad-muted">{{
                    row.balance_cents > 0 ? t('admin.supplier_account.we_owe') : t('admin.supplier_account.they_owe')
                }}</span>
            </span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" />
        </template>
        <template #row-actions="{ row }">
            <Tooltip :title="t('admin.suppliers.account')">
                <Link :href="route('admin.suppliers.show', row.id)" :aria-label="t('admin.suppliers.account')">
                    <Button type="text" size="small">
                        <template #icon><BookOpenText class="h-4 w-4" /></template>
                    </Button>
                </Link>
            </Tooltip>
            <Tooltip :title="t('admin.common.edit')">
                <Button type="text" size="small" :aria-label="t('admin.common.edit')" @click="openEdit(row)">
                    <template #icon><Pencil class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
            <Tooltip :title="t('admin.common.delete')">
                <Button type="text" size="small" danger :aria-label="t('admin.common.delete')" @click="deleting = row">
                    <template #icon><Trash2 class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
        </template>
    </DataTable>

    <Pagination :paginator="suppliers" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.suppliers.edit') : t('admin.suppliers.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field :label="t('admin.suppliers.company_name')" required :error="form.errors.company_name">
                <Input v-model:value="form.company_name" />
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.suppliers.contact_person')" :error="form.errors.contact_person">
                    <Input v-model:value="form.contact_person" />
                </Field>
                <Field :label="t('admin.common.tax_number')" :error="form.errors.tax_number">
                    <Input v-model:value="form.tax_number" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.email')" :error="form.errors.email">
                    <Input v-model:value="form.email" type="email" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.phone')" :error="form.errors.phone">
                    <Input v-model:value="form.phone" />
                </Field>
            </div>
            <Field :label="t('admin.common.address')" :error="form.errors.address">
                <Textarea v-model:value="form.address" :rows="2" />
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.status')" required :error="form.errors.status">
                    <AdminSelect
                        v-model="form.status"
                        :options="[
                            { value: 'active', label: t('admin.common.active') },
                            { value: 'inactive', label: t('admin.common.inactive') },
                        ]"
                    />
                </Field>
            </div>
            <Field :label="t('admin.common.notes')" :error="form.errors.notes">
                <Textarea v-model:value="form.notes" :rows="2" />
            </Field>
            <div class="flex justify-end gap-3">
                <Button @click="modalOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="form.processing">{{ t('admin.common.save') }}</Button>
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
