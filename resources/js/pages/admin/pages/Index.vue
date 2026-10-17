<script setup lang="ts">
/**
 * The storefront pages the headless shop renders (ADR 0001). System pages
 * (home) are always listed first and cannot be deleted.
 */
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Button, Checkbox, Input, Tag, Tooltip } from 'ant-design-vue';
import { LayoutTemplate, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

interface PageRow extends Record<string, unknown> {
    id: number;
    key: string | null;
    slug: string | null;
    title: string;
    title_ar: string | null;
    is_published: boolean;
    is_system: boolean;
    sections_count: number;
    active_sections_count: number;
}

defineProps<{ pages: PageRow[] }>();

const { t } = useI18n();

const columns: Column[] = [
    { key: 'title', label: t('admin.pages.page') },
    { key: 'address', label: t('admin.pages.address') },
    { key: 'sections_count', label: t('admin.pages.sections') },
    { key: 'is_published', label: t('admin.common.status') },
];

const creating = ref(false);
const form = useForm({ title: '', title_ar: '', slug: '', is_published: true });

function openCreate(): void {
    form.reset();
    form.clearErrors();
    creating.value = true;
}

function create(): void {
    form.post(route('admin.pages.store'), { onSuccess: () => (creating.value = false) });
}

const deleting = ref<PageRow | null>(null);
const deletingBusy = ref(false);

function destroy(): void {
    if (!deleting.value) return;
    deletingBusy.value = true;
    router.delete(route('admin.pages.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = null;
            deletingBusy.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('admin.pages.title')" />

    <PageHeader :title="t('admin.pages.title')" :subtitle="t('admin.pages.subtitle')">
        <template #actions>
            <Button type="primary" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('admin.pages.new') }}
            </Button>
        </template>
    </PageHeader>

    <DataTable :columns="columns" :rows="pages">
        <template #cell-title="{ row }">
            <Link :href="route('admin.pages.edit', row.id)" class="font-medium">{{ row.title }}</Link>
            <span v-if="row.title_ar" class="admin-muted block text-xs" dir="rtl">{{ row.title_ar }}</span>
        </template>
        <template #cell-address="{ row }">
            <Tag v-if="row.is_system" color="processing" class="!m-0">{{ t('admin.pages.system') }} · {{ row.key }}</Tag>
            <code v-else dir="ltr">/{{ row.slug }}</code>
        </template>
        <template #cell-sections_count="{ row }">
            {{ t('admin.pages.sections_shown', { shown: row.active_sections_count, total: row.sections_count }) }}
        </template>
        <template #cell-is_published="{ row }">
            <StatusBadge :status="row.is_published ? 'active' : 'inactive'" />
        </template>
        <template #row-actions="{ row }">
            <Tooltip :title="t('admin.pages.open_builder')">
                <Link :href="route('admin.pages.edit', row.id)">
                    <Button type="text" size="small" :aria-label="t('admin.pages.open_builder')">
                        <template #icon><LayoutTemplate class="h-4 w-4" /></template>
                    </Button>
                </Link>
            </Tooltip>
            <Tooltip :title="row.is_system ? t('admin.pages.system_cannot_delete') : t('admin.common.delete')">
                <Button type="text" size="small" danger :disabled="row.is_system" :aria-label="t('admin.common.delete')" @click="deleting = row">
                    <template #icon><Trash2 class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
        </template>
    </DataTable>

    <FormModal :open="creating" :title="t('admin.pages.new')" @close="creating = false">
        <form class="grid gap-1" @submit.prevent="create">
            <div class="grid grid-cols-2 gap-3">
                <Field :label="t('admin.pages.title_en')" required :error="form.errors.title">
                    <Input v-model:value="form.title" :maxlength="120" dir="ltr" />
                </Field>
                <Field :label="t('admin.pages.title_ar')" :error="form.errors.title_ar">
                    <Input v-model:value="form.title_ar" :maxlength="120" dir="rtl" />
                </Field>
            </div>
            <Field :label="t('admin.common.slug')" required :hint="t('admin.pages.slug_hint')" :error="form.errors.slug">
                <Input v-model:value="form.slug" :maxlength="80" dir="ltr" placeholder="about-us" />
            </Field>
            <Checkbox v-model:checked="form.is_published" class="mb-4">{{ t('admin.pages.published') }}</Checkbox>
            <div class="flex justify-end gap-3">
                <Button @click="creating = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="form.processing">{{ t('admin.common.save') }}</Button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="deleting !== null"
        :message="deleting ? t('admin.pages.delete_confirm', { title: deleting.title }) : ''"
        :confirm-label="t('admin.common.delete')"
        danger
        :processing="deletingBusy"
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
