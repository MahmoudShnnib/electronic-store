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
import { btnPrimary, btnSecondary, checkboxClass, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Image as ImageIcon, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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

const form = useForm({
    name: '',
    name_ar: '',
    slug: '',
    parent_id: '' as number | '',
    description: '',
    position: 0,
    is_active: true,
    image: null as File | null,
    remove_image: false,
});

/** Preview of the image already saved against the category being edited. */
const currentImage = ref<string | null>(null);

function pickImage(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.image = input.files?.[0] ?? null;
    if (form.image) form.remove_image = false;
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
            <button type="button" :class="btnPrimary" @click="openCreate"><Plus class="h-4 w-4" />{{ t('admin.categories.new') }}</button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.categories.index')" :filters="filters" :search-placeholder="t('admin.categories.search')" />

    <DataTable :columns="columns" :rows="categories.data">
        <template #cell-name="{ row }">
            <div>
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</span>
                <span v-if="row.name_ar" class="ms-2 text-xs text-gray-400" dir="rtl">{{ row.name_ar }}</span>
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

    <Pagination :links="categories.links" :total="categories.total" :from="categories.from" :to="categories.to" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.categories.edit') : t('admin.categories.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field :label="t('admin.common.name')" required :error="form.errors.name">
                <input v-model="form.name" type="text" :class="inputClass" dir="ltr" />
            </Field>
            <Field :label="t('admin.common.name_ar')" :error="form.errors.name_ar">
                <input v-model="form.name_ar" type="text" dir="rtl" :class="inputClass" />
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.slug')" :hint="t('admin.common.slug_hint')" :error="form.errors.slug">
                    <input v-model="form.slug" type="text" :class="inputClass" dir="ltr" />
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
                <textarea v-model="form.description" rows="3" :class="inputClass" />
            </Field>
            <Field :label="t('admin.categories.image')" :error="form.errors.image">
                <div class="flex items-center gap-4">
                    <img
                        v-if="imagePreview"
                        :src="imagePreview"
                        alt=""
                        class="h-16 w-16 rounded-lg object-cover ring-1 ring-black/10 dark:ring-white/10"
                    />
                    <div v-else class="flex h-16 w-16 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-neutral-800">
                        <ImageIcon class="h-6 w-6" />
                    </div>
                    <div class="flex-1 space-y-2">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-gray-600 dark:text-gray-300"
                            @change="pickImage"
                        />
                        <label v-if="currentImage" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <input v-model="form.remove_image" type="checkbox" :class="checkboxClass" />
                            {{ t('admin.categories.remove_image') }}
                        </label>
                        <p class="text-xs text-gray-500">{{ t('admin.categories.image_hint') }}</p>
                    </div>
                </div>
            </Field>
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.position')" :error="form.errors.position">
                    <input v-model.number="form.position" type="number" min="0" :class="inputClass" />
                </Field>
                <label class="mt-6 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="form.is_active" type="checkbox" :class="checkboxClass" />{{ t('admin.common.active') }}</label
                >
            </div>
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
        :title="t('admin.categories.delete_title')"
        :message="t('admin.categories.delete_message', { name: deleting?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
