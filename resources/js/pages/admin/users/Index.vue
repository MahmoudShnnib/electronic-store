<script setup lang="ts">
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
import { Head, useForm } from '@inertiajs/vue3';
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

const form = useForm({
    name: '',
    email: '',
    password: '',
    roles: [] as string[],
    is_active: true,
});

function openCreate(): void {
    editing.value = null;
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

function toggleRole(role: string): void {
    form.roles = form.roles.includes(role) ? form.roles.filter((r) => r !== role) : [...form.roles, role];
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
            <button type="button" :class="btnPrimary" @click="openCreate"><Plus class="h-4 w-4" />{{ t('admin.users.new') }}</button>
        </template>
    </PageHeader>

    <FilterBar :url="route('admin.users.index')" :filters="filters" :search-placeholder="t('admin.users.search')" />

    <DataTable :columns="columns" :rows="users.data">
        <template #cell-name="{ row }">
            <div>
                <p class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</p>
                <p class="text-xs text-gray-400">{{ row.email }}</p>
            </div>
        </template>
        <template #cell-roles="{ row }">
            <div class="flex flex-wrap gap-1">
                <span
                    v-for="role in row.roles"
                    :key="role"
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                >
                    {{ role.replaceAll('_', ' ') }}
                </span>
            </div>
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
        </template>
        <template #row-actions="{ row }">
            <button type="button" class="rounded p-1.5 text-gray-400 hover:text-blue-600" :title="t('admin.common.edit')" @click="openEdit(row)">
                <Pencil class="h-4 w-4" />
            </button>
        </template>
    </DataTable>

    <Pagination :links="users.links" :total="users.total" :from="users.from" :to="users.to" />

    <FormModal :open="modalOpen" :title="editing ? t('admin.users.edit') : t('admin.users.new_staff')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field :label="t('admin.common.name')" required :error="form.errors.name">
                <input v-model="form.name" type="text" :class="inputClass" />
            </Field>
            <Field v-if="!editing" :label="t('admin.common.email')" required :error="form.errors.email">
                <input v-model="form.email" type="email" :class="inputClass" dir="ltr" />
            </Field>
            <Field :label="editing ? t('admin.users.new_password') : t('admin.users.password')" :required="!editing" :error="form.errors.password">
                <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass" />
            </Field>
            <Field :label="t('admin.common.roles')" required :error="form.errors.roles">
                <div class="mt-1 grid grid-cols-2 gap-2">
                    <label v-for="role in roles" :key="role" class="flex items-center gap-2 text-sm capitalize text-gray-700 dark:text-gray-300">
                        <input type="checkbox" class="rounded border-gray-300" :checked="form.roles.includes(role)" @change="toggleRole(role)" />
                        {{ role.replaceAll('_', ' ') }}
                    </label>
                </div>
            </Field>
            <label v-if="editing" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />{{ t('admin.users.account_active') }}</label
            >
            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="modalOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="form.processing" :class="btnPrimary">{{ t('admin.common.save') }}</button>
            </div>
        </form>
    </FormModal>
</template>
