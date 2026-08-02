<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import { btnPrimary, btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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

const modalOpen = ref(false);
const editing = ref<CouponRow | null>(null);
const deleting = ref<CouponRow | null>(null);

const form = useForm({
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
});

function openCreate(): void {
    editing.value = null;
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
            <button type="button" :class="btnPrimary" @click="openCreate"><Plus class="h-4 w-4" />{{ t('admin.coupons.new') }}</button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.coupons.index')" :filters="filters" :search-placeholder="t('admin.coupons.search')" />

    <DataTable :columns="columns" :rows="coupons.data">
        <template #cell-code="{ row }">
            <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ row.code }}</span>
        </template>
        <template #cell-used_count="{ row }">
            {{ row.used_count }}<span v-if="row.usage_limit" class="text-gray-400"> / {{ row.usage_limit }}</span>
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge
                :status="row.currently_valid ? 'active' : 'inactive'"
                :label="row.currently_valid ? t('admin.coupons.valid') : row.is_active ? t('admin.coupons.expired') : t('admin.coupons.disabled')"
            />
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

    <Pagination :links="coupons.links" :total="coupons.total" :from="coupons.from" :to="coupons.to" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.coupons.edit') : t('admin.coupons.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.code')" required :error="form.errors.code">
                    <input v-model="form.code" type="text" class="uppercase" :class="inputClass" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.type')" required :error="form.errors.type">
                    <select v-model="form.type" :class="inputClass">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field v-if="form.type === 'fixed'" :label="t('admin.coupons.discount_amount')" required :error="form.errors.value">
                    <input v-model.number="form.value" type="number" step="0.01" min="0.01" :class="inputClass" />
                </Field>
                <Field v-if="form.type === 'percentage'" :label="t('admin.coupons.percent')" required :error="form.errors.percent">
                    <input v-model.number="form.percent" type="number" step="0.01" min="0.01" max="100" :class="inputClass" />
                </Field>
                <Field :label="t('admin.coupons.max_discount')" :error="form.errors.max_discount">
                    <input v-model.number="form.max_discount" type="number" step="0.01" min="0" :class="inputClass" />
                </Field>
                <Field :label="t('admin.coupons.minimum_order')" :error="form.errors.min_order">
                    <input v-model.number="form.min_order" type="number" step="0.01" min="0" :class="inputClass" />
                </Field>
                <Field :label="t('admin.coupons.usage_limit')" :error="form.errors.usage_limit">
                    <input v-model.number="form.usage_limit" type="number" min="1" :class="inputClass" />
                </Field>
                <Field :label="t('admin.coupons.per_customer_limit')" :error="form.errors.per_customer_limit">
                    <input v-model.number="form.per_customer_limit" type="number" min="1" :class="inputClass" />
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.coupons.applies_to')" :error="form.errors.applies_to">
                    <select v-model="form.applies_to" :class="inputClass">
                        <option value="all">{{ t('admin.coupons.entire_store') }}</option>
                        <option value="products">{{ t('admin.coupons.specific_products') }}</option>
                        <option value="categories">{{ t('admin.coupons.specific_categories') }}</option>
                        <option value="brands">{{ t('admin.coupons.specific_brands') }}</option>
                    </select>
                </Field>
                <Field
                    v-if="form.applies_to !== 'all'"
                    :label="t('admin.coupons.ids')"
                    :hint="t('admin.coupons.ids_hint')"
                    :error="form.errors.applies_to_ids"
                >
                    <input v-model="form.applies_to_ids" type="text" :class="inputClass" />
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.coupons.starts_at')" :error="form.errors.starts_at">
                    <input v-model="form.starts_at" type="datetime-local" :class="inputClass" />
                </Field>
                <Field :label="t('admin.coupons.expires_at')" :error="form.errors.expires_at">
                    <input v-model="form.expires_at" type="datetime-local" :class="inputClass" />
                </Field>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />{{ t('admin.common.active') }}</label
            >
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="modalOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="form.processing" :class="btnPrimary">{{ t('admin.common.save') }}</button>
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
</template>
