<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Avatar, Button, Checkbox, Space, Tooltip } from 'ant-design-vue';
import { Plus, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface ProductRow extends Record<string, unknown> {
    id: number;
    name: string;
    sku: string;
    slug: string;
    category: string | null;
    brand: string | null;
    price_formatted: string;
    status: string;
    status_label: string;
    is_featured: boolean;
    stock: number;
    low_stock: boolean;
    image: string | null;
    deleted_at: string | null;
}

const props = defineProps<{
    products: Paginator<ProductRow>;
    filters: Record<string, unknown>;
    statuses: Option[];
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const columns: Column[] = [
    // The id was only readable off the /admin/products/{id}/edit URL before.
    { key: 'id', label: t('admin.common.id'), sortable: true },
    { key: 'name', label: t('admin.common.product'), sortable: true },
    { key: 'sku', label: t('admin.common.sku'), sortable: true },
    { key: 'category', label: t('admin.common.category') },
    { key: 'brand', label: t('admin.common.brand') },
    { key: 'price_formatted', label: t('admin.common.price') },
    { key: 'stock', label: t('admin.nav.stock'), sortable: true },
    { key: 'status', label: t('admin.common.status') },
];

const selected = ref<number[]>([]);
const bulkAction = ref('');
const deleting = ref<ProductRow | null>(null);

const allSelected = computed(() => props.products.data.length > 0 && selected.value.length === props.products.data.length);

function toggleAll(): void {
    selected.value = allSelected.value ? [] : props.products.data.map((p) => p.id);
}

function toggleRow(id: number): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
}

function applyBulk(): void {
    if (bulkAction.value === '' || selected.value.length === 0) return;

    router.post(
        route('admin.products.bulk'),
        { ids: selected.value, action: bulkAction.value },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

function sortBy(key: string): void {
    const dir = props.filters.sort === key && props.filters.dir !== 'desc' ? 'desc' : 'asc';
    router.get(route('admin.products.index'), { ...props.filters, sort: key, dir }, { preserveState: true, preserveScroll: true });
}

function toggleTrashed(): void {
    router.get(route('admin.products.index'), { ...props.filters, trashed: props.filters.trashed ? undefined : 1 }, { preserveState: true });
}

function destroy(): void {
    if (!deleting.value) return;

    router.delete(route('admin.products.destroy', deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleting.value = null),
    });
}

function restore(product: ProductRow): void {
    router.post(route('admin.products.restore', product.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('admin.products.title')" />

    <PageHeader :title="t('admin.products.title')" :subtitle="t('admin.products.count', { count: products.total })">
        <template #actions>
            <Button @click="toggleTrashed">
                {{ filters.trashed ? t('admin.common.hide_trash') : t('admin.common.show_trash') }}
            </Button>
            <Link v-if="can.create" :href="route('admin.products.create')">
                <Button type="primary">
                    <template #icon><Plus class="h-4 w-4" /></template>
                    {{ t('admin.products.new') }}
                </Button>
            </Link>
        </template>
    </PageHeader>

    <FilterBar
        :url="route('admin.products.index')"
        :filters="filters"
        :search-placeholder="t('admin.products.search')"
        :selects="[
            { key: 'status', label: t('admin.common.all_statuses'), options: statuses },
            { key: 'category_id', label: t('admin.common.all_categories'), options: categories.map((c) => ({ value: String(c.id), label: c.name })) },
            { key: 'brand_id', label: t('admin.common.all_brands'), options: brands.map((b) => ({ value: String(b.id), label: b.name })) },
        ]"
    >
        <Space v-if="selected.length > 0 && can.update">
            <AdminSelect
                v-model="bulkAction"
                class="!w-auto"
                :empty-label="t('admin.products.bulk_action', { count: selected.length })"
                :options="[
                    { value: 'activate', label: t('admin.common.activate') },
                    { value: 'deactivate', label: t('admin.common.deactivate') },
                    { value: 'feature', label: t('admin.products.feature') },
                    { value: 'unfeature', label: t('admin.products.unfeature') },
                    { value: 'delete', label: t('admin.common.delete') },
                ]"
            />
            <Button @click="applyBulk">{{ t('admin.common.apply') }}</Button>
        </Space>
    </FilterBar>

    <DataTable :columns="columns" :rows="products.data" :sort="String(filters.sort ?? '')" :dir="String(filters.dir ?? '')" @sort="sortBy">
        <template #select>
            <Checkbox :checked="allSelected" :indeterminate="selected.length > 0 && !allSelected" @change="toggleAll" />
        </template>
        <template #row-select="{ row }">
            <Checkbox :checked="selected.includes(row.id)" @change="toggleRow(row.id)" />
        </template>

        <template #cell-name="{ row }">
            <div class="flex items-center gap-3">
                <Avatar :src="row.image ?? undefined" :alt="row.name" shape="square" :size="36">—</Avatar>
                <div>
                    <Link
                        v-if="can.update && !row.deleted_at"
                        :href="route('admin.products.edit', row.id)"
                        class="font-medium text-gray-900 hover:text-blue-600 dark:text-gray-100"
                    >
                        {{ row.name }}
                    </Link>
                    <span v-else class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</span>
                    <p v-if="row.is_featured" class="text-xs text-amber-600">{{ t('admin.products.featured') }}</p>
                </div>
            </div>
        </template>

        <template #cell-stock="{ row }">
            <span :class="row.low_stock ? 'font-semibold text-amber-600' : ''">{{ row.stock }}</span>
        </template>

        <template #cell-status="{ row }">
            <!-- In the trash view a soft-deleted product still carries status
                 "active", so without this the rows are indistinguishable from
                 the live ones. -->
            <StatusBadge v-if="row.deleted_at" status="deleted" />
            <StatusBadge v-else :status="row.status" :label="row.status_label" />
        </template>

        <template #row-actions="{ row }">
            <Tooltip v-if="row.deleted_at && can.delete" :title="t('admin.common.restore')">
                <Button type="text" size="small" :aria-label="t('admin.common.restore')" @click="restore(row)">
                    <template #icon><RotateCcw class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
            <Tooltip v-else-if="can.delete" :title="t('admin.common.delete')">
                <Button type="text" size="small" danger :aria-label="t('admin.common.delete')" @click="deleting = row">
                    <template #icon><Trash2 class="h-4 w-4" /></template>
                </Button>
            </Tooltip>
        </template>
    </DataTable>

    <Pagination :paginator="products" />

    <ConfirmDialog
        :open="deleting !== null"
        :title="t('admin.products.delete_title')"
        :message="t('admin.products.delete_message', { name: deleting?.name })"
        :confirm-label="t('admin.common.delete')"
        danger
        @confirm="destroy"
        @close="deleting = null"
    />
</template>
