<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Checkbox, CheckboxGroup, Input, InputPassword, Tag, Tooltip, Typography } from 'ant-design-vue';
import { Pencil, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface UserRow extends Record<string, unknown> {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_active: boolean;
    last_login_at: string | null;
}

defineProps<{
    users: Paginator<UserRow>;
    roles: string[];
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'name', label: t('admin.common.user') },
    { key: 'roles', label: t('admin.common.roles') },
    { key: 'last_login_at', label: t('admin.common.last_login') },
    { key: 'is_active', label: t('admin.common.status') },
];

const modalOpen = ref(false);
const editing = ref<UserRow | null>(null);

const blank = {
    name: '',
    email: '',
    password: '',
    roles: [] as string[],
    is_active: true,
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

function openEdit(user: UserRow): void {
    editing.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    form.roles = [...user.roles];
    form.is_active = user.is_active;
    modalOpen.value = true;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        password: data.password === '' ? null : data.password,
    }));

    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };

    if (editing.value) {
        form.patch(route('admin.users.update', editing.value.id), options);
    } else {
        form.post(route('admin.users.store'), options);
    }
}
</script>

<template>
    <Head :title="t('admin.users.title')" />

    <PageHeader :title="t('admin.users.title')" :subtitle="t('admin.users.count', { count: users.total })">
        <template #actions>
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.users.new') }}
            </Button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.users.index')" :filters="filters" :search-placeholder="t('admin.users.search')" />

    <DataTable :columns="columns" :rows="users.data">
        <template #cell-name="{ row }">
            <div>
                <p class="font-medium">{{ row.name }}</p>
                <Typography.Text type="secondary" class="text-xs">{{ row.email }}</Typography.Text>
            </div>
        </template>
        <template #cell-roles="{ row }">
            <div class="flex flex-wrap gap-1">
                <Tag v-for="role in row.roles" :key="role" class="capitalize">{{ role.replaceAll('_', ' ') }}</Tag>
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
        </template>
    </DataTable>

    <Pagination :paginator="users" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.users.edit') : t('admin.users.new_staff')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field :label="t('admin.common.name')" required :error="form.errors.name">
                <Input v-model:value="form.name" />
            </Field>
            <Field v-if="!editing" :label="t('admin.common.email')" required :error="form.errors.email">
                <Input v-model:value="form.email" type="email" dir="ltr" />
            </Field>
            <Field :label="editing ? t('admin.users.new_password') : t('admin.users.password')" :required="!editing" :error="form.errors.password">
                <InputPassword v-model:value="form.password" autocomplete="new-password" />
            </Field>
            <Field :label="t('admin.common.roles')" required :error="form.errors.roles">
                <CheckboxGroup v-model:value="form.roles" class="mt-1 grid grid-cols-2 gap-2">
                    <Checkbox v-for="role in roles" :key="role" :value="role" class="capitalize">{{ role.replaceAll('_', ' ') }}</Checkbox>
                </CheckboxGroup>
            </Field>
            <Checkbox v-if="editing" v-model:checked="form.is_active">{{ t('admin.users.account_active') }}</Checkbox>
            <div class="flex justify-end gap-3">
                <Button @click="modalOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="form.processing">{{ t('admin.common.save') }}</Button>
            </div>
        </form>
    </FormModal>
</template>
