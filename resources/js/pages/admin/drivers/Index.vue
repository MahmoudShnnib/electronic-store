<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PhoneLinks from '@/components/admin/PhoneLinks.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Checkbox, Input, Tooltip } from 'ant-design-vue';
import { ClipboardList, Pencil, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface DriverRow extends Record<string, unknown> {
    id: number;
    name: string;
    phone: string | null;
    is_active: boolean;
    out_count: number;
}

defineProps<{ drivers: DriverRow[] }>();

// computed: labels follow the admin's language even on the first page after logging in.
const columns = computed<Column[]>(() => [
    { key: 'name', label: t('admin.drivers.name') },
    { key: 'phone', label: t('admin.drivers.phone') },
    { key: 'out_count', label: t('admin.drivers.out_now') },
    { key: 'is_active', label: t('admin.common.status') },
]);

const modalOpen = ref(false);
const editing = ref<DriverRow | null>(null);

const blank = { name: '', phone: '', is_active: true };
const form = useForm({ ...blank });

function openCreate(): void {
    editing.value = null;
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
    modalOpen.value = true;
}

function openEdit(driver: DriverRow): void {
    editing.value = driver;
    form.clearErrors();
    form.name = driver.name;
    form.phone = driver.phone ?? '';
    form.is_active = driver.is_active;
    modalOpen.value = true;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (modalOpen.value = false) };

    if (editing.value) {
        form.patch(route('admin.drivers.update', editing.value.id), options);
    } else {
        form.post(route('admin.drivers.store'), options);
    }
}
</script>

<template>
    <Head :title="t('admin.drivers.title')" />

    <PageHeader :title="t('admin.drivers.title')" :subtitle="t('admin.drivers.subtitle')">
        <template #actions>
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.drivers.new') }}
            </Button>
        </template>
    </PageHeader>

    <DataTable :columns="columns" :rows="drivers">
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-phone="{ row }">
            <PhoneLinks :phone="row.phone" />
        </template>
        <template #cell-out_count="{ row }">
            <Link v-if="row.out_count > 0" :href="route('admin.orders.index', { driver_id: row.id })" class="admin-link hover:underline">
                {{ row.out_count }}
            </Link>
            <span v-else>0</span>
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge
                :status="row.is_active ? 'active' : 'inactive'"
                :label="row.is_active ? t('admin.drivers.active') : t('admin.drivers.inactive')"
            />
        </template>
        <template #row-actions="{ row }">
            <Tooltip :title="t('admin.drivers.manifest')">
                <a :href="route('admin.drivers.manifest', row.id)" target="_blank" rel="noopener">
                    <Button type="text" size="small" :aria-label="t('admin.drivers.manifest')">
                        <template #icon><ClipboardList class="h-4 w-4" /></template>
                    </Button>
                </a>
            </Tooltip>
            <Tooltip :title="t('admin.common.edit')">
                <Button type="text" size="small" :aria-label="t('admin.common.edit')" @click="openEdit(row)">
                    <template #icon><Pencil class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
        </template>
        <template #empty>{{ t('admin.orders.no_drivers') }}</template>
    </DataTable>

    <FormModal :open="modalOpen" :title="editing ? t('admin.drivers.edit') : t('admin.drivers.new')" @close="modalOpen = false">
        <form class="grid gap-4" @submit.prevent="submit">
            <Field :label="t('admin.drivers.name')" required :error="form.errors.name">
                <Input v-model:value="form.name" />
            </Field>
            <Field :label="t('admin.drivers.phone')" :error="form.errors.phone">
                <Input v-model:value="form.phone" dir="ltr" type="tel" />
            </Field>
            <Checkbox v-model:checked="form.is_active">{{ t('admin.drivers.active') }}</Checkbox>
            <div class="flex justify-end gap-3">
                <Button @click="modalOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="form.processing">{{ t('admin.common.save') }}</Button>
            </div>
        </form>
    </FormModal>
</template>
