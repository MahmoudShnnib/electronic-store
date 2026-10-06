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
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button, Checkbox, Input, InputNumber, Textarea, Tooltip, TypographyText, Upload } from 'ant-design-vue';
import { Image as ImageIcon, Pencil, Plus, Trash2, Upload as UploadIcon } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface CategoryRow extends Record<string, unknown> {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    parent: string | null;
    parent_id: number | null;
    position: number;
    is_active: boolean;
    products_count: number;
    description: string | null;
    image_url: string | null;
}

defineProps<{
    categories: Paginator<CategoryRow>;
    parentOptions: { id: number; name: string }[];
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'name', label: t('admin.common.name') },
    { key: 'slug', label: t('admin.common.slug') },
    { key: 'parent', label: t('admin.common.parent') },
    { key: 'position', label: t('admin.common.position') },
    { key: 'products_count', label: t('admin.common.products') },
    { key: 'is_active', label: t('admin.common.status') },
];

const modalOpen = ref(false);
const editing = ref<CategoryRow | null>(null);
const deleting = ref<CategoryRow | null>(null);

const blank = {
    name: '',
    name_ar: '',
    slug: '',
    parent_id: '' as number | '',
    description: '',
    position: 0,
    is_active: true,
    image: null as File | null,
    remove_image: false,
};

const form = useForm({ ...blank });

/** Preview of the image already saved against the category being edited. */
const currentImage = ref<string | null>(null);

/**
 * `:before-upload` returning false keeps AntD from POSTing the file itself;
 * Inertia sends it with the rest of the form.
 */
function pickImage(file: File): boolean {
    form.image = file;
    form.remove_image = false;
    return false;
}

/**
 * Object URLs for the freshly picked file are revoked when they stop being
 * shown, otherwise every re-pick leaks a blob for the life of the page.
 * createObjectURL must live here in <script setup>: URL is not one of the
 * globals Vue exposes to template expressions.
 */
const pickedUrl = ref<string | null>(null);

watch(
    () => form.image,
    (file) => {
        if (pickedUrl.value) URL.revokeObjectURL(pickedUrl.value);
        pickedUrl.value = file ? URL.createObjectURL(file) : null;
    },
);

onBeforeUnmount(() => {
    if (pickedUrl.value) URL.revokeObjectURL(pickedUrl.value);
});

const imagePreview = computed(() => {
    if (pickedUrl.value) return pickedUrl.value;
    return form.remove_image ? null : currentImage.value;
});

function openCreate(): void {
    editing.value = null;
    // Inertia 2 promotes the submitted data to the form's defaults on success,
    // so a bare reset() re-opened "New" pre-filled with the last edited record.
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
    currentImage.value = null;
    modalOpen.value = true;
}

function openEdit(category: CategoryRow): void {
    editing.value = category;
    form.clearErrors();
    form.name = category.name;
    form.name_ar = category.name_ar ?? '';
    form.slug = category.slug;
    form.parent_id = category.parent_id ?? '';
    form.description = category.description ?? '';
    form.position = category.position;
    form.is_active = category.is_active;
    form.image = null;
    form.remove_image = false;
    currentImage.value = category.image_url;
    modalOpen.value = true;
}

function submit(): void {
    const isEdit = editing.value !== null;

    form.transform((data) => ({
        ...data,
        parent_id: data.parent_id === '' ? null : data.parent_id,
        slug: data.slug === '' ? null : data.slug,
        // A File can only travel as multipart/form-data, and multipart cannot be
        // sent over PATCH — so updates POST with a spoofed method instead.
        ...(isEdit ? { _method: 'patch' } : {}),
    }));

    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            modalOpen.value = false;
            form.image = null;
            form.remove_image = false;
        },
    };

    if (isEdit) {
        form.post(route('admin.categories.update', editing.value!.id), options);
    } else {
        form.post(route('admin.categories.store'), options);
    }
}

function destroy(): void {
    if (!deleting.value) return;

    router.delete(route('admin.categories.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.categories.title')" />

    <PageHeader :title="t('admin.categories.title')" :subtitle="t('admin.categories.count', { count: categories.total })">
        <template #actions>
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.categories.new') }}
            </Button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.categories.index')" :filters="filters" :search-placeholder="t('admin.categories.search')" />

    <DataTable :columns="columns" :rows="categories.data">
        <template #cell-name="{ row }">
            <!-- Flex gap, not a margin: the Arabic span carries dir="rtl", so its own
                 margin-inline-start would resolve to the far side and the two names
                 would run together. -->
            <div class="flex items-center gap-2">
                <span class="font-medium">{{ row.name }}</span>
                <TypographyText v-if="row.name_ar" type="secondary" class="text-xs" dir="rtl">{{ row.name_ar }}</TypographyText>
            </div>
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

    <Pagination :paginator="categories" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.categories.edit') : t('admin.categories.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field :label="t('admin.common.name')" required :error="form.errors.name">
                <Input v-model:value="form.name" dir="ltr" />
            </Field>
            <Field :label="t('admin.common.name_ar')" :error="form.errors.name_ar">
                <Input v-model:value="form.name_ar" dir="rtl" />
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.slug')" :hint="t('admin.common.slug_hint')" :error="form.errors.slug">
                    <Input v-model:value="form.slug" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.parent')" :error="form.errors.parent_id">
                    <AdminSelect
                        v-model="form.parent_id"
                        :empty-label="t('admin.categories.root')"
                        :options="parentOptions.filter((o) => o.id !== editing?.id).map((p) => ({ value: p.id, label: p.name }))"
                    />
                </Field>
            </div>
            <Field :label="t('admin.common.description')" :error="form.errors.description">
                <Textarea v-model:value="form.description" :rows="3" />
            </Field>
            <Field :label="t('admin.categories.image')" :error="form.errors.image">
                <div class="flex items-center gap-4">
                    <img
                        v-if="imagePreview"
                        :src="imagePreview"
                        alt=""
                        width="64"
                        height="64"
                        class="h-16 w-16 rounded-lg object-cover ring-1 ring-ad-line"
                    />
                    <div v-else class="flex h-16 w-16 items-center justify-center rounded-lg bg-ad-icon-bg text-ad-faint">
                        <ImageIcon class="h-6 w-6" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <Upload :max-count="1" :show-upload-list="false" accept="image/jpeg,image/png,image/webp" :before-upload="pickImage">
                            <Button>
                                <template #icon><UploadIcon class="h-4 w-4" /></template>
                                {{ t('admin.common.choose') }}
                            </Button>
                        </Upload>
                        <Checkbox v-if="currentImage" v-model:checked="form.remove_image">{{ t('admin.categories.remove_image') }}</Checkbox>
                        <TypographyText type="secondary" class="block text-xs">{{ t('admin.categories.image_hint') }}</TypographyText>
                    </div>
                </div>
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.position')" :error="form.errors.position">
                    <InputNumber v-model:value="form.position" :min="0" class="w-full" />
                </Field>
                <Checkbox v-model:checked="form.is_active" class="mt-6">{{ t('admin.common.active') }}</Checkbox>
            </div>
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
        :title="t('admin.categories.delete_title')"
        :message="t('admin.categories.delete_message', { name: deleting?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
