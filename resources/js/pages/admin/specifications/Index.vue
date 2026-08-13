<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { btnPrimary, btnSecondary, checkboxClass, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface Attribute {
    id: number;
    name: string;
    name_ar: string | null;
    unit: string | null;
    type: string;
    is_filterable: boolean;
    position: number;
    values_count: number;
    category_ids: number[];
}

interface Group {
    id: number;
    name: string;
    name_ar: string | null;
    position: number;
    attributes: Attribute[];
}

const props = defineProps<{
    groups: Group[];
    categories: { id: number; name: string; name_ar: string | null; parent_id: number | null }[];
}>();

/** "PC, Mobile" chip text for the attributes table; empty scope = all. */
function categoryNames(ids: number[]): string {
    return props.categories
        .filter((c) => ids.includes(c.id))
        .map((c) => c.name)
        .join('، ');
}

// -- Group modal --------------------------------------------------------------

const groupModal = ref(false);
const editingGroup = ref<Group | null>(null);

const groupForm = useForm({ name: '', name_ar: '', position: 0 });

function openGroupCreate(): void {
    editingGroup.value = null;
    groupForm.reset();
    groupForm.clearErrors();
    groupModal.value = true;
}

function openGroupEdit(group: Group): void {
    editingGroup.value = group;
    groupForm.clearErrors();
    groupForm.name = group.name;
    groupForm.name_ar = group.name_ar ?? '';
    groupForm.position = group.position;
    groupModal.value = true;
}

function submitGroup(): void {
    const options = { preserveScroll: true, onSuccess: () => (groupModal.value = false) };

    if (editingGroup.value) {
        groupForm.patch(route('admin.specification-groups.update', editingGroup.value.id), options);
    } else {
        groupForm.post(route('admin.specification-groups.store'), options);
    }
}

const deletingGroup = ref<Group | null>(null);

function destroyGroup(): void {
    if (!deletingGroup.value) return;

    router.delete(route('admin.specification-groups.destroy', deletingGroup.value.id), {
        preserveScroll: true,
        onFinish: () => (deletingGroup.value = null),
    });
}

// -- Attribute modal ----------------------------------------------------------

const attributeModal = ref(false);
const editingAttribute = ref<Attribute | null>(null);

const attributeForm = useForm({
    specification_group_id: 0,
    name: '',
    name_ar: '',
    unit: '',
    type: 'text',
    is_filterable: false,
    position: 0,
    categories: [] as number[],
});

function openAttributeCreate(group: Group): void {
    editingAttribute.value = null;
    attributeForm.reset();
    attributeForm.clearErrors();
    attributeForm.specification_group_id = group.id;
    attributeModal.value = true;
}

function openAttributeEdit(group: Group, attribute: Attribute): void {
    editingAttribute.value = attribute;
    attributeForm.clearErrors();
    attributeForm.specification_group_id = group.id;
    attributeForm.name = attribute.name;
    attributeForm.name_ar = attribute.name_ar ?? '';
    attributeForm.unit = attribute.unit ?? '';
    attributeForm.type = attribute.type;
    attributeForm.is_filterable = attribute.is_filterable;
    attributeForm.position = attribute.position;
    attributeForm.categories = [...attribute.category_ids];
    attributeModal.value = true;
}

function submitAttribute(): void {
    attributeForm.transform((data) => ({ ...data, unit: data.unit === '' ? null : data.unit }));

    const options = { preserveScroll: true, onSuccess: () => (attributeModal.value = false) };

    if (editingAttribute.value) {
        attributeForm.patch(route('admin.specifications.update', editingAttribute.value.id), options);
    } else {
        attributeForm.post(route('admin.specifications.store'), options);
    }
}

const deletingAttribute = ref<Attribute | null>(null);

function destroyAttribute(): void {
    if (!deletingAttribute.value) return;

    router.delete(route('admin.specifications.destroy', deletingAttribute.value.id), {
        preserveScroll: true,
        onFinish: () => (deletingAttribute.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.specifications.title')" />

    <PageHeader :title="t('admin.specifications.title')" :subtitle="t('admin.specifications.subtitle')">
        <template #actions>
            <button type="button" :class="btnPrimary" @click="openGroupCreate">
                <Plus class="h-4 w-4" />{{ t('admin.specifications.new_group') }}
            </button>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-2">
        <section
            v-for="group in groups"
            :key="group.id"
            class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ group.name }}</h2>
                    <p v-if="group.name_ar" class="text-xs text-gray-400" dir="rtl">{{ group.name_ar }}</p>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" :class="btnSecondary" class="!px-2 !py-1 text-xs" @click="openAttributeCreate(group)">
                        <Plus class="h-3.5 w-3.5" />{{ t('admin.specifications.attribute') }}
                    </button>
                    <button
                        type="button"
                        class="rounded p-1.5 text-gray-400 hover:text-blue-600"
                        :title="t('admin.specifications.edit_group')"
                        @click="openGroupEdit(group)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded p-1.5 text-gray-400 hover:text-red-600"
                        :title="t('admin.specifications.delete_group')"
                        @click="deletingGroup = group"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                <li v-for="attribute in group.attributes" :key="attribute.id" class="flex items-center justify-between px-4 py-2.5 text-sm">
                    <div>
                        <span class="text-gray-800 dark:text-gray-200">{{ attribute.name }}</span>
                        <span v-if="attribute.unit" class="ms-1 text-xs text-gray-400">({{ attribute.unit }})</span>
                        <span class="ms-2 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            {{ attribute.type }}
                        </span>
                        <span
                            v-if="attribute.is_filterable"
                            class="ms-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] uppercase text-blue-600 dark:bg-blue-950 dark:text-blue-300"
                            >{{ t('admin.specifications.filter') }}</span
                        >
                        <span
                            class="ms-1 rounded px-1.5 py-0.5 text-[10px]"
                            :class="
                                attribute.category_ids.length === 0
                                    ? 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500'
                                    : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300'
                            "
                        >
                            {{
                                attribute.category_ids.length === 0 ? t('admin.specifications.all_categories') : categoryNames(attribute.category_ids)
                            }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-gray-400">{{ t('admin.specifications.uses_n', { count: attribute.values_count }) }}</span>
                        <button
                            type="button"
                            class="rounded p-1 text-gray-400 hover:text-blue-600"
                            :title="t('admin.common.edit')"
                            @click="openAttributeEdit(group, attribute)"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="rounded p-1 text-gray-400 hover:text-red-600"
                            :title="t('admin.common.delete')"
                            @click="deletingAttribute = attribute"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </li>
                <li v-if="group.attributes.length === 0" class="px-4 py-5 text-center text-sm text-gray-400">
                    {{ t('admin.specifications.no_attributes') }}
                </li>
            </ul>
        </section>
    </div>

    <p
        v-if="groups.length === 0"
        class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-400 dark:border-gray-700 dark:bg-gray-900"
    >
        {{ t('admin.specifications.no_groups') }}
    </p>

    <!-- Group modal -->
    <FormModal
        :open="groupModal"
        :title="editingGroup ? t('admin.specifications.edit_group') : t('admin.specifications.new_group')"
        @close="groupModal = false"
    >
        <form class="grid gap-4" @submit.prevent="submitGroup">
            <Field :label="t('admin.common.name')" required :error="groupForm.errors.name">
                <input v-model="groupForm.name" type="text" :class="inputClass" dir="ltr" />
            </Field>
            <Field :label="t('admin.common.name_ar')" :error="groupForm.errors.name_ar">
                <input v-model="groupForm.name_ar" type="text" dir="rtl" :class="inputClass" />
            </Field>
            <Field :label="t('admin.common.position')" :error="groupForm.errors.position">
                <input v-model.number="groupForm.position" type="number" min="0" :class="inputClass" />
            </Field>
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="groupModal = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="groupForm.processing" :class="btnPrimary">{{ t('admin.common.save') }}</button>
            </div>
        </form>
    </FormModal>

    <!-- Attribute modal -->
    <FormModal
        :open="attributeModal"
        :title="editingAttribute ? t('admin.specifications.edit_attribute') : t('admin.specifications.new_attribute')"
        @close="attributeModal = false"
    >
        <form class="grid gap-4" @submit.prevent="submitAttribute">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.common.name')" required :error="attributeForm.errors.name">
                    <input v-model="attributeForm.name" type="text" :class="inputClass" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.name_ar')" :error="attributeForm.errors.name_ar">
                    <input v-model="attributeForm.name_ar" type="text" dir="rtl" :class="inputClass" />
                </Field>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <Field :label="t('admin.common.unit')" :hint="t('admin.specifications.unit_hint')" :error="attributeForm.errors.unit">
                    <input v-model="attributeForm.unit" type="text" :class="inputClass" />
                </Field>
                <Field :label="t('admin.common.type')" :error="attributeForm.errors.type">
                    <AdminSelect
                        v-model="attributeForm.type"
                        :options="[
                            { value: 'text', label: t('admin.specifications.text') },
                            { value: 'number', label: t('admin.specifications.number') },
                            { value: 'boolean', label: t('admin.specifications.boolean') },
                        ]"
                    />
                </Field>
                <Field :label="t('admin.common.position')" :error="attributeForm.errors.position">
                    <input v-model.number="attributeForm.position" type="number" min="0" :class="inputClass" />
                </Field>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input v-model="attributeForm.is_filterable" type="checkbox" :class="checkboxClass" />{{
                    t('admin.specifications.filterable')
                }}</label
            >
            <Field :label="t('admin.specifications.categories_label')" :hint="t('admin.specifications.categories_hint')">
                <div class="grid max-h-44 grid-cols-2 gap-1 overflow-y-auto rounded-md border border-gray-200 p-2 dark:border-gray-700">
                    <label v-for="category in categories" :key="category.id" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input v-model="attributeForm.categories" type="checkbox" :value="category.id" :class="checkboxClass" />
                        <span :class="{ 'ps-3 text-gray-500': category.parent_id !== null }">{{ category.name }}</span>
                    </label>
                </div>
            </Field>
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="attributeModal = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="attributeForm.processing" :class="btnPrimary">{{ t('admin.common.save') }}</button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="deletingGroup !== null"
        :title="t('admin.specifications.delete_group')"
        :message="t('admin.specifications.delete_group_message', { name: deletingGroup?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroyGroup"
        @close="deletingGroup = null"
    />

    <ConfirmDialog
        :open="deletingAttribute !== null"
        :title="t('admin.specifications.delete_attribute')"
        :message="t('admin.specifications.delete_attribute_message', { name: deletingAttribute?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroyAttribute"
        @close="deletingAttribute = null"
    />
</template>
