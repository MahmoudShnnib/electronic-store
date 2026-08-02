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

interface BrandRow extends Record<string, unknown> {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    logo: string | null;
    position: number;
    is_active: boolean;
    products_count: number;
    description: string | null;
}

defineProps<{
    brands: Paginator<BrandRow>;
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'name', label: t('admin.common.brand') },
    { key: 'slug', label: t('admin.common.slug') },
    { key: 'position', label: t('admin.common.position') },
    { key: 'products_count', label: t('admin.common.products') },
    { key: 'is_active', label: t('admin.common.status') },
];

const modalOpen = ref(false);
const editing = ref<BrandRow | null>(null);
const deleting = ref<BrandRow | null>(null);

const form = useForm({
    name: '',
    name_ar: '',
    slug: '',
    description: '',
    position: 0,
    is_active: true,
    logo: null as File | null,
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(brand: BrandRow): void {
    editing.value = brand;
    form.clearErrors();
    form.name = brand.name;
    form.name_ar = brand.name_ar ?? '';
    form.slug = brand.slug;
    form.description = brand.description ?? '';
    form.position = brand.position;
    form.is_active = brand.is_active;
    form.logo = null;
    modalOpen.value = true;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        slug: data.slug === '' ? null : data.slug,
        ...(editing.value ? { _method: 'patch' } : {}),
    }));

    const options = { preserveScroll: true, forceFormData: true, onSuccess: () => (modalOpen.value = false) };

    if (editing.value) {
        form.post(route('admin.brands.update', editing.value.id), options);
    } else {
        form.post(route('admin.brands.store'), options);
    }
}

function destroy(): void {
    if (!deleting.value) return;

    router.delete(route('admin.brands.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.brands.title')" />

    <PageHeader :title="t('admin.brands.title')" :subtitle="t('admin.brands.count', { count: brands.total })">
        <template #actions>
            <button type="button" :class="btnPrimary" @click="openCreate"><Plus class="h-4 w-4" />{{ t('admin.brands.new') }}</button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.brands.index')" :filters="filters" :search-placeholder="t('admin.brands.search')" />

    <DataTable :columns="columns" :rows="brands.data">
        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.logo" :src="row.logo" :alt="row.name" class="h-8 w-8 rounded object-contain" />
                <div v-else class="flex h-8 w-8 items-center justify-center rounded bg-gray-100 text-xs font-semibold text-gray-400 dark:bg-gray-800">
                    {{ row.name.charAt(0) }}
                </div>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</span>
            </div>
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
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

    <Pagination :links="brands.links" :total="brands.total" :from="brands.from" :to="brands.to" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.brands.edit') : t('admin.brands.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.name')" required :error="form.errors.name">
                    <input v-model="form.name" type="text" :class="inputClass" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.name_ar')" :error="form.errors.name_ar">
                    <input v-model="form.name_ar" type="text" dir="rtl" :class="inputClass" />
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.slug')" :hint="t('admin.common.slug_hint')" :error="form.errors.slug">
                    <input v-model="form.slug" type="text" :class="inputClass" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.position')" :error="form.errors.position">
                    <input v-model.number="form.position" type="number" min="0" :class="inputClass" />
                </Field>
            </div>
            <Field :label="t('admin.common.logo')" :error="form.errors.logo">
                <input type="file" accept="image/*" class="text-sm" @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
            </Field>
            <Field :label="t('admin.common.description')" :error="form.errors.description">
                <textarea v-model="form.description" rows="3" :class="inputClass" />
            </Field>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />{{ t('admin.common.active') }}</label
            >
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="modalOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="form.processing" :class="btnPrimary">
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('admin.brands.delete_title')"
        :message="t('admin.brands.delete_message', { name: deleting?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
