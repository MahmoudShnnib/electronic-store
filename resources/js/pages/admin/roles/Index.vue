<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { btnPrimary, btnSecondary, checkboxClass, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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

const form = useForm({
    name: '',
    permissions: [] as string[],
});

function openCreate(): void {
    editing.value = null;
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

function togglePermission(permission: string): void {
    form.permissions = form.permissions.includes(permission) ? form.permissions.filter((p) => p !== permission) : [...form.permissions, permission];
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
            <button type="button" :class="btnPrimary" @click="openCreate"><Plus class="h-4 w-4" />{{ t('admin.roles.new') }}</button>
        </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <section
            v-for="role in roles"
            :key="role.id"
            class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold capitalize text-gray-900 dark:text-gray-100">
                    <ShieldCheck class="h-4 w-4 text-blue-500" />
                    {{ role.name.replaceAll('_', ' ') }}
                </h2>
                <div class="flex items-center gap-1">
                    <button
                        v-if="role.name !== 'super_admin'"
                        type="button"
                        class="rounded p-1.5 text-gray-400 hover:text-blue-600"
                        :title="t('admin.common.edit')"
                        @click="openEdit(role)"
                    >
                        <Pencil class="h-4 w-4" />
                    </button>
                    <button
                        v-if="!role.protected"
                        type="button"
                        class="rounded p-1.5 text-gray-400 hover:text-red-600"
                        :title="t('admin.common.delete')"
                        @click="deleting = role"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>
            <p class="mb-2 text-xs text-gray-400">{{ t('admin.roles.users_n', { count: role.users_count }) }}</p>
            <p v-if="role.name === 'super_admin'" class="text-sm text-gray-500 dark:text-gray-400">{{ t('admin.roles.super_admin_note') }}</p>
            <p v-else-if="role.name === 'customer'" class="text-sm text-gray-500 dark:text-gray-400">{{ t('admin.roles.customer_note') }}</p>
            <div v-else class="flex flex-wrap gap-1">
                <span
                    v-for="permission in role.permissions"
                    :key="permission"
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                >
                    {{ permission }}
                </span>
                <span v-if="role.permissions.length === 0" class="text-sm text-gray-400">{{ t('admin.roles.no_permissions') }}</span>
            </div>
        </section>
    </div>

    <FormModal :open="modalOpen" :title="editing ? t('admin.roles.edit_named', { name: editing.name }) : 'New role'" wide @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field v-if="!editing" :label="t('admin.roles.role_name')" required :hint="t('admin.roles.role_name_hint')" :error="form.errors.name">
                <input v-model="form.name" type="text" :class="inputClass" />
            </Field>

            <div>
                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('admin.common.permissions') }}</p>
                <p v-if="form.errors.permissions" class="mb-2 text-xs text-red-600">{{ form.errors.permissions }}</p>
                <div class="grid max-h-96 gap-4 overflow-y-auto sm:grid-cols-2">
                    <div v-for="(list, group) in groupedPermissions" :key="group" class="rounded-md border border-gray-100 p-3 dark:border-gray-800">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ group }}</p>
                        <label
                            v-for="permission in list"
                            :key="permission"
                            class="flex items-center gap-2 py-0.5 text-sm text-gray-700 dark:text-gray-300"
                        >
                            <input
                                type="checkbox"
                                :class="checkboxClass"
                                :checked="form.permissions.includes(permission)"
                                @change="togglePermission(permission)"
                            />
                            {{ permission }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" :class="btnSecondary" @click="modalOpen = false">{{ t('admin.common.cancel') }}</button>
                <button type="submit" :disabled="form.processing" :class="btnPrimary">{{ t('admin.common.save') }}</button>
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
