<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button, Card, Checkbox, CheckboxGroup, Input, Space, Tag, Tooltip, Typography } from 'ant-design-vue';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface RoleRow {
    id: number;
    name: string;
    permissions: string[];
    users_count: number;
    protected: boolean;
}

const props = defineProps<{
    roles: RoleRow[];
    permissions: string[];
}>();

/** Group permissions by their resource prefix for a tidy checklist. */
const groupedPermissions = computed(() => {
    const groups: Record<string, string[]> = {};

    for (const permission of props.permissions) {
        const prefix = permission.split('.')[0];
        (groups[prefix] ??= []).push(permission);
    }

    return groups;
});

const modalOpen = ref(false);
const editing = ref<RoleRow | null>(null);
const deleting = ref<RoleRow | null>(null);

const blank = {
    name: '',
    permissions: [] as string[],
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

function openEdit(role: RoleRow): void {
    editing.value = role;
    form.clearErrors();
    form.name = role.name;
    form.permissions = [...role.permissions];
    modalOpen.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };

    if (editing.value) {
        form.patch(route('admin.roles.update', editing.value.id), options);
    } else {
        form.post(route('admin.roles.store'), options);
    }
}

function destroy(): void {
    if (!deleting.value) return;

    router.delete(route('admin.roles.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head :title="t('admin.roles.title')" />

    <PageHeader :title="t('admin.roles.title')" :subtitle="t('admin.roles.subtitle')">
        <template #actions>
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.roles.new') }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Card v-for="role in roles" :key="role.id" size="small">
            <template #title>
                <Space>
                    <ShieldCheck class="h-4 w-4 text-blue-500" />
                    <span class="capitalize">{{ role.name.replaceAll('_', ' ') }}</span>
                </Space>
            </template>
            <template #extra>
                <Tooltip v-if="role.name !== 'super_admin'" :title="t('admin.common.edit')">
                    <Button type="text" size="small" :aria-label="t('admin.common.edit')" @click="openEdit(role)">
                        <template #icon><Pencil class="h-4 w-4" /></template>
                    </Button>
                </Tooltip>
                <Tooltip v-if="!role.protected" :title="t('admin.common.delete')">
                    <Button type="text" size="small" danger :aria-label="t('admin.common.delete')" @click="deleting = role">
                        <template #icon><Trash2 class="h-4 w-4" /></template>
                    </Button>
                </Tooltip>
            </template>
            <Typography.Text type="secondary" class="mb-2 block text-xs">{{ t('admin.roles.users_n', { count: role.users_count }) }}</Typography.Text>
            <Typography.Text v-if="role.name === 'super_admin'" type="secondary">{{ t('admin.roles.super_admin_note') }}</Typography.Text>
            <Typography.Text v-else-if="role.name === 'customer'" type="secondary">{{ t('admin.roles.customer_note') }}</Typography.Text>
            <div v-else class="flex flex-wrap gap-1">
                <Tag v-for="permission in role.permissions" :key="permission">{{ permission }}</Tag>
                <Typography.Text v-if="role.permissions.length === 0" type="secondary">{{ t('admin.roles.no_permissions') }}</Typography.Text>
            </div>
        </Card>
    </div>

    <FormModal :open="modalOpen" :title="editing ? t('admin.roles.edit_named', { name: editing.name }) : 'New role'" wide @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field v-if="!editing" :label="t('admin.roles.role_name')" required :hint="t('admin.roles.role_name_hint')" :error="form.errors.name">
                <Input v-model:value="form.name" />
            </Field>

            <div>
                <Typography.Text strong class="mb-2 block text-sm">{{ t('admin.common.permissions') }}</Typography.Text>
                <Typography.Text v-if="form.errors.permissions" type="danger" class="mb-2 block text-xs">{{
                    form.errors.permissions
                }}</Typography.Text>
                <!-- One CheckboxGroup wraps every group, so the submitted array stays
                     `form.permissions` exactly as before while the visual grouping holds. -->
                <CheckboxGroup v-model:value="form.permissions" class="grid max-h-96 gap-4 overflow-y-auto sm:grid-cols-2">
                    <div v-for="(list, group) in groupedPermissions" :key="group" class="rounded-md border border-gray-100 p-3 dark:border-gray-800">
                        <Typography.Text type="secondary" class="mb-2 block text-xs font-semibold uppercase tracking-wide">{{
                            group
                        }}</Typography.Text>
                        <div class="grid gap-0.5">
                            <Checkbox v-for="permission in list" :key="permission" :value="permission">{{ permission }}</Checkbox>
                        </div>
                    </div>
                </CheckboxGroup>
            </div>

            <div class="flex justify-end gap-3">
                <Button @click="modalOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="form.processing">{{ t('admin.common.save') }}</Button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('admin.roles.delete_title')"
        :message="t('admin.roles.delete_message', { name: deleting?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
