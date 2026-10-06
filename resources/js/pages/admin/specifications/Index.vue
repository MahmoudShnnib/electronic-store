<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button, Card, Checkbox, CheckboxGroup, Empty, Input, InputNumber, Space, Tag, Tooltip, Typography } from 'ant-design-vue';
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
            <Button type="primary" @click="openGroupCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.specifications.new_group') }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid gap-5 lg:grid-cols-2">
        <Card v-for="group in groups" :key="group.id" size="small" :body-style="{ padding: 0 }">
            <template #title>
                <div>
                    <Typography.Text strong>{{ group.name }}</Typography.Text>
                    <Typography.Text v-if="group.name_ar" type="secondary" class="block text-xs" dir="rtl">{{ group.name_ar }}</Typography.Text>
                </div>
            </template>
            <template #extra>
                <Space :size="4">
                    <Button size="small" @click="openAttributeCreate(group)">
                        <template #icon><Plus class="h-3.5 w-3.5" /></template>
                        {{ t('admin.specifications.attribute') }}
                    </Button>
                    <Tooltip :title="t('admin.specifications.edit_group')">
                        <Button type="text" size="small" :aria-label="t('admin.specifications.edit_group')" @click="openGroupEdit(group)">
                            <template #icon><Pencil class="h-4 w-4" /></template>
                        </Button>
                    </Tooltip>
                    <Tooltip :title="t('admin.specifications.delete_group')">
                        <Button type="text" size="small" danger :aria-label="t('admin.specifications.delete_group')" @click="deletingGroup = group">
                            <template #icon><Trash2 class="h-4 w-4" /></template>
                        </Button>
                    </Tooltip>
                </Space>
            </template>
            <ul class="divide-y divide-ad-line">
                <li
                    v-for="attribute in group.attributes"
                    :key="attribute.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm"
                >
                    <div>
                        <span>{{ attribute.name }}</span>
                        <Typography.Text v-if="attribute.unit" type="secondary" class="ms-1 text-xs">({{ attribute.unit }})</Typography.Text>
                        <Tag class="ms-2 uppercase">{{ attribute.type }}</Tag>
                        <Tag v-if="attribute.is_filterable" color="blue" class="uppercase">{{ t('admin.specifications.filter') }}</Tag>
                        <Tag :color="attribute.category_ids.length === 0 ? undefined : 'green'">
                            {{
                                attribute.category_ids.length === 0 ? t('admin.specifications.all_categories') : categoryNames(attribute.category_ids)
                            }}
                        </Tag>
                    </div>
                    <Space :size="4">
                        <Typography.Text type="secondary" class="text-xs">
                            {{ t('admin.specifications.uses_n', { count: attribute.values_count }, attribute.values_count) }}
                        </Typography.Text>
                        <Tooltip :title="t('admin.common.edit')">
                            <Button type="text" size="small" :aria-label="t('admin.common.edit')" @click="openAttributeEdit(group, attribute)">
                                <template #icon><Pencil class="h-3.5 w-3.5" /></template>
                            </Button>
                        </Tooltip>
                        <Tooltip :title="t('admin.common.delete')">
                            <Button type="text" size="small" danger :aria-label="t('admin.common.delete')" @click="deletingAttribute = attribute">
                                <template #icon><Trash2 class="h-3.5 w-3.5" /></template>
                            </Button>
                        </Tooltip>
                    </Space>
                </li>
                <li v-if="group.attributes.length === 0" class="px-4 py-5 text-center text-sm">
                    <Typography.Text type="secondary">{{ t('admin.specifications.no_attributes') }}</Typography.Text>
                </li>
            </ul>
        </Card>
    </div>

    <Card v-if="groups.length === 0">
        <Empty :description="t('admin.specifications.no_groups')" />
    </Card>

    <!-- Group modal -->
    <FormModal
        :open="groupModal"
        :title="editingGroup ? t('admin.specifications.edit_group') : t('admin.specifications.new_group')"
        @close="groupModal = false"
    >
        <form class="grid gap-4" @submit.prevent="submitGroup">
            <Field :label="t('admin.common.name')" required :error="groupForm.errors.name">
                <Input v-model:value="groupForm.name" dir="ltr" />
            </Field>
            <Field :label="t('admin.common.name_ar')" :error="groupForm.errors.name_ar">
                <Input v-model:value="groupForm.name_ar" dir="rtl" />
            </Field>
            <Field :label="t('admin.common.position')" :error="groupForm.errors.position">
                <InputNumber v-model:value="groupForm.position" :min="0" class="w-full" />
            </Field>
            <div class="flex justify-end gap-3">
                <Button @click="groupModal = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="groupForm.processing">{{ t('admin.common.save') }}</Button>
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
                    <Input v-model:value="attributeForm.name" dir="ltr" />
                </Field>
                <Field :label="t('admin.common.name_ar')" :error="attributeForm.errors.name_ar">
                    <Input v-model:value="attributeForm.name_ar" dir="rtl" />
                </Field>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <Field :label="t('admin.common.unit')" :hint="t('admin.specifications.unit_hint')" :error="attributeForm.errors.unit">
                    <Input v-model:value="attributeForm.unit" />
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
                    <InputNumber v-model:value="attributeForm.position" :min="0" class="w-full" />
                </Field>
            </div>
            <Checkbox v-model:checked="attributeForm.is_filterable">{{ t('admin.specifications.filterable') }}</Checkbox>
            <Field :label="t('admin.specifications.categories_label')" :hint="t('admin.specifications.categories_hint')">
                <CheckboxGroup
                    v-model:value="attributeForm.categories"
                    class="grid max-h-44 w-full grid-cols-2 gap-1 overflow-y-auto rounded-md border border-ad-line p-2"
                >
                    <Checkbox v-for="category in categories" :key="category.id" :value="category.id">
                        <span :class="{ 'ps-3 text-ad-muted': category.parent_id !== null }">{{ category.name }}</span>
                    </Checkbox>
                </CheckboxGroup>
            </Field>
            <div class="flex justify-end gap-3">
                <Button @click="attributeModal = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="attributeForm.processing">{{ t('admin.common.save') }}</Button>
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
