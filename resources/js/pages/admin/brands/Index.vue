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
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Avatar, Button, Checkbox, Input, InputNumber, Space, Textarea, Tooltip, Upload } from 'ant-design-vue';
import { Pencil, Plus, Trash2, Upload as UploadIcon } from 'lucide-vue-next';
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

const blank = {
    name: '',
    name_ar: '',
    slug: '',
    description: '',
    position: 0,
    is_active: true,
    logo: null as File | null,
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
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.brands.new') }}
            </Button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.brands.index')" :filters="filters" :search-placeholder="t('admin.brands.search')" />

    <DataTable :columns="columns" :rows="brands.data">
        <template #cell-name="{ row }">
            <Space>
                <Avatar :src="row.logo ?? undefined" shape="square" :size="32">{{ row.name.charAt(0) }}</Avatar>
                <span class="font-medium">{{ row.name }}</span>
            </Space>
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
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

    <Pagination :paginator="brands" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.brands.edit') : t('admin.brands.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.name')" required :error="form.errors.name">
                    <Input v-model:value="form.name" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.name_ar')" :error="form.errors.name_ar">
                    <Input v-model:value="form.name_ar" dir="rtl" />
                </Field>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.slug')" :hint="t('admin.common.slug_hint')" :error="form.errors.slug">
                    <Input v-model:value="form.slug" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.position')" :error="form.errors.position">
                    <InputNumber v-model:value="form.position" :min="0" class="w-full" />
                </Field>
            </div>
            <Field :label="t('admin.common.logo')" :error="form.errors.logo">
                <!-- `:before-upload` returning false keeps AntD from POSTing the
                     file itself; Inertia sends it with the rest of the form. -->
                <Upload
                    :max-count="1"
                    accept="image/*"
                    :before-upload="
                        (file: File) => {
                            form.logo = file;
                            return false;
                        }
                    "
                    @remove="form.logo = null"
                >
                    <Button>
                        <template #icon><UploadIcon class="h-4 w-4" /></template>
                        {{ t('admin.common.choose') }}
                    </Button>
                </Upload>
            </Field>
            <Field :label="t('admin.common.description')" :error="form.errors.description">
                <Textarea v-model:value="form.description" :rows="3" />
            </Field>
            <Checkbox v-model:checked="form.is_active">{{ t('admin.common.active') }}</Checkbox>
            <div class="flex justify-end gap-3">
                <Button @click="modalOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="form.processing">
                    {{ form.processing ? t('admin.common.saving') : t('admin.common.save') }}
                </Button>
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
