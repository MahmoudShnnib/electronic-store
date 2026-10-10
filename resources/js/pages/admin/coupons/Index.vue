<script setup lang="ts">
import AdminDateInput from '@/components/admin/AdminDateInput.vue';
import AdminSelect from '@/components/admin/AdminSelect.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button, Checkbox, Input, InputNumber, Table, Tooltip, Typography } from 'ant-design-vue';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface CouponRow extends Record<string, unknown> {
    id: number;
    code: string;
    type: string;
    type_label: string;
    value: string;
    value_cents: number | null;
    percent: number | null;
    min_order: number | null;
    max_discount: number | null;
    usage_limit: number | null;
    per_customer_limit: number | null;
    used_count: number;
    redemptions: { id: number; customer: string; order_number: string | null; amount_formatted: string; at: string; released: boolean }[];
    applies_to: string;
    applies_to_ids: number[] | null;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
    currently_valid: boolean;
}

defineProps<{
    coupons: Paginator<CouponRow>;
    types: Option[];
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'code', label: t('admin.common.code') },
    { key: 'type_label', label: t('admin.common.type') },
    { key: 'value', label: t('admin.common.value') },
    { key: 'used_count', label: t('admin.common.used') },
    { key: 'expires_at', label: t('admin.common.expires') },
    { key: 'is_active', label: t('admin.common.status') },
];

/** Redemption history table (AntD `Table` columns, not the admin `DataTable` ones). */
const historyColumns = [
    { key: 'customer', dataIndex: 'customer', title: t('admin.common.customer') },
    { key: 'order_number', dataIndex: 'order_number', title: t('admin.common.order') },
    { key: 'amount_formatted', dataIndex: 'amount_formatted', title: t('admin.common.amount') },
    { key: 'at', dataIndex: 'at', title: t('admin.common.date') },
];

const modalOpen = ref(false);
const editing = ref<CouponRow | null>(null);
/** "Used" was a bare number with no way to see what it was spent on. */
const history = ref<CouponRow | null>(null);
const deleting = ref<CouponRow | null>(null);

const blank = {
    code: '',
    type: 'fixed',
    value: null as number | null,
    percent: null as number | null,
    min_order: null as number | null,
    max_discount: null as number | null,
    usage_limit: null as number | null,
    per_customer_limit: null as number | null,
    applies_to: 'all',
    applies_to_ids: '' as string,
    starts_at: '',
    expires_at: '',
    is_active: true,
};

const form = useForm({ ...blank });

function openCreate(): void {
    editing.value = null;
    // Inertia 2 promotes the submitted data to the form's defaults on success,
    // so a bare reset() re-opened "New coupon" pre-filled with the last one.
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(coupon: CouponRow): void {
    editing.value = coupon;
    form.clearErrors();
    form.code = coupon.code;
    form.type = coupon.type;
    form.value = coupon.value_cents !== null ? coupon.value_cents / 100 : null;
    form.percent = coupon.percent;
    form.min_order = coupon.min_order;
    form.max_discount = coupon.max_discount;
    form.usage_limit = coupon.usage_limit;
    form.per_customer_limit = coupon.per_customer_limit;
    form.applies_to = coupon.applies_to;
    form.applies_to_ids = (coupon.applies_to_ids ?? []).join(',');
    form.starts_at = coupon.starts_at ?? '';
    form.expires_at = coupon.expires_at ?? '';
    form.is_active = coupon.is_active;
    modalOpen.value = true;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        applies_to_ids:
            data.applies_to === 'all'
                ? null
                : data.applies_to_ids
                      .split(',')
                      .map((s) => Number(s.trim()))
                      .filter((n) => Number.isFinite(n) && n > 0),
        starts_at: data.starts_at === '' ? null : data.starts_at,
        expires_at: data.expires_at === '' ? null : data.expires_at,
    }));

    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };

    if (editing.value) {
        form.patch(route('admin.coupons.update', editing.value.id), options);
    } else {
        form.post(route('admin.coupons.store'), options);
    }
}

function destroy(): void {
    if (!deleting.value) return;

    router.delete(route('admin.coupons.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.coupons.title')" />

    <PageHeader :title="t('admin.coupons.title')" :subtitle="t('admin.coupons.count', { count: coupons.total })">
        <template #actions>
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.coupons.new') }}
            </Button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.coupons.index')" :filters="filters" :search-placeholder="t('admin.coupons.search')" />

    <DataTable :columns="columns" :rows="coupons.data">
        <template #cell-code="{ row }">
            <span class="font-mono font-medium">{{ row.code }}</span>
        </template>
        <template #cell-used_count="{ row }">
            <Tooltip :title="t('admin.coupons.history')">
                <Button
                    type="link"
                    size="small"
                    class="!px-0"
                    :disabled="row.redemptions.length === 0"
                    :aria-label="t('admin.coupons.history')"
                    @click="history = row"
                >
                    {{ row.used_count }}<Typography.Text v-if="row.usage_limit" type="secondary"> / {{ row.usage_limit }}</Typography.Text>
                </Button>
            </Tooltip>
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge
                :status="row.currently_valid ? 'active' : 'inactive'"
                :label="row.currently_valid ? t('admin.coupons.valid') : row.is_active ? t('admin.coupons.expired') : t('admin.coupons.disabled')"
            />
        </template>
        <template #row-actions="{ row }">
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

    <Pagination :paginator="coupons" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.coupons.edit') : t('admin.coupons.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.code')" required :error="form.errors.code">
                    <Input v-model:value="form.code" class="uppercase" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.type')" required :error="form.errors.type">
                    <AdminSelect v-model="form.type" :options="types" />
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field v-if="form.type === 'fixed'" :label="t('admin.coupons.discount_amount')" required :error="form.errors.value">
                    <InputNumber v-model:value="form.value" :min="0.01" :step="0.01" class="w-full" />
                </Field>
                <Field v-if="form.type === 'percentage'" :label="t('admin.coupons.percent')" required :error="form.errors.percent">
                    <InputNumber v-model:value="form.percent" :min="0.01" :max="100" :step="0.01" class="w-full" />
                </Field>
                <Field :label="t('admin.coupons.max_discount')" :error="form.errors.max_discount">
                    <InputNumber v-model:value="form.max_discount" :min="0" :step="0.01" class="w-full" />
                </Field>
                <Field :label="t('admin.coupons.minimum_order')" :error="form.errors.min_order">
                    <InputNumber v-model:value="form.min_order" :min="1" :step="1" class="w-full" />
                </Field>
                <Field :label="t('admin.coupons.usage_limit')" :error="form.errors.usage_limit">
                    <InputNumber v-model:value="form.usage_limit" :min="1" class="w-full" />
                </Field>
                <Field :label="t('admin.coupons.per_customer_limit')" :error="form.errors.per_customer_limit">
                    <InputNumber v-model:value="form.per_customer_limit" :min="1" class="w-full" />
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.coupons.applies_to')" :error="form.errors.applies_to">
                    <AdminSelect
                        v-model="form.applies_to"
                        :options="[
                            { value: 'all', label: t('admin.coupons.entire_store') },
                            { value: 'products', label: t('admin.coupons.specific_products') },
                            { value: 'categories', label: t('admin.coupons.specific_categories') },
                            { value: 'brands', label: t('admin.coupons.specific_brands') },
                        ]"
                    />
                </Field>
                <Field
                    v-if="form.applies_to !== 'all'"
                    :label="t('admin.coupons.ids')"
                    :hint="t('admin.coupons.ids_hint')"
                    :error="form.errors.applies_to_ids"
                >
                    <Input v-model:value="form.applies_to_ids" />
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.coupons.starts_at')" :error="form.errors.starts_at">
                    <AdminDateInput v-model="form.starts_at" with-time />
                </Field>
                <Field :label="t('admin.coupons.expires_at')" :error="form.errors.expires_at">
                    <AdminDateInput v-model="form.expires_at" with-time />
                </Field>
            </div>
            <Checkbox v-model:checked="form.is_active">{{ t('admin.common.active') }}</Checkbox>
            <div class="flex justify-end gap-3">
                <Button @click="modalOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="form.processing">{{ t('admin.common.save') }}</Button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('admin.coupons.delete_title')"
        :message="t('admin.coupons.delete_message', { code: deleting?.code })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />

    <FormModal :open="history !== null" :title="t('admin.coupons.history_title', { code: history?.code ?? '' })" @close="history = null">
        <Table
            :columns="historyColumns"
            :data-source="history?.redemptions ?? []"
            :pagination="false"
            row-key="id"
            size="small"
            :scroll="{ x: true }"
        >
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'order_number'">
                    {{ record.order_number ?? '—' }}
                    <StatusBadge v-if="record.released" status="cancelled" :label="t('admin.coupons.released')" class="ms-2" />
                </template>
            </template>
        </Table>
    </FormModal>
</template>
