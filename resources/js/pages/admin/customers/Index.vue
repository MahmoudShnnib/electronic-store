<script setup lang="ts">
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Tooltip, Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const { Text } = Typography;

interface CustomerRow extends Record<string, unknown> {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    verified: boolean;
    orders_count: number;
    lifetime_formatted: string;
    joined_at: string;
    last_login_at: string | null;
}

defineProps<{
    customers: Paginator<CustomerRow>;
    filters: Record<string, unknown>;
}>();

const columns: Column[] = [
    { key: 'name', label: t('admin.common.customer') },
    { key: 'phone', label: t('admin.common.phone') },
    { key: 'orders_count', label: t('admin.common.orders') },
    { key: 'lifetime_formatted', label: t('admin.customers.lifetime_value') },
    { key: 'joined_at', label: t('admin.common.joined') },
    { key: 'is_active', label: t('admin.common.status') },
];
</script>

<template>
    <Head :title="t('admin.customers.title')" />

    <PageHeader :title="t('admin.customers.title')" :subtitle="t('admin.customers.count', { count: customers.total })" />

    <FilterBar
        :url="route('admin.customers.index')"
        :filters="filters"
        :search-placeholder="t('admin.customers.search')"
        :selects="[
            {
                key: 'status',
                label: t('admin.common.all_accounts'),
                options: [
                    { value: 'active', label: t('admin.common.active') },
                    { value: 'inactive', label: t('admin.common.deactivated') },
                ],
            },
        ]"
    />

    <DataTable :columns="columns" :rows="customers.data">
        <template #cell-name="{ row }">
            <div>
                <Link :href="route('admin.customers.show', row.id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                    {{ row.name }}
                </Link>
                <Text type="secondary" class="block !text-xs">
                    {{ row.email }}
                    <Tooltip v-if="row.verified" :title="t('admin.common.email_verified')"><span>✓</span></Tooltip>
                </Text>
            </div>
        </template>
        <template #cell-is_active="{ row }">
            <StatusBadge
                :status="row.is_active ? 'active' : 'inactive'"
                :label="row.is_active ? t('admin.common.active') : t('admin.common.deactivated')"
            />
        </template>
    </DataTable>

    <Pagination :paginator="customers" />
</template>
