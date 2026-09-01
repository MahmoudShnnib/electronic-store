<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import Pagination from '@/components/store/Pagination.vue';
import StatusTag from '@/components/store/StatusTag.vue';
import type { PaginationLink } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, Table, Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface ReturnRow {
    id: number;
    rma_number: string;
    order_number: string;
    status: string;
    status_label: string;
    reason_label: string;
    created_at: string;
}

defineProps<{
    returns: { data: ReturnRow[]; links: PaginationLink[] };
}>();

const { Title } = Typography;

const { t } = useI18n();

const columns = [
    { key: 'rma_number', dataIndex: 'rma_number', title: 'RMA' },
    { key: 'order_number', dataIndex: 'order_number', title: t('account.order') },
    { key: 'reason_label', dataIndex: 'reason_label', title: t('account.return_reason') },
    { key: 'status', title: t('account.status') },
    { key: 'created_at', dataIndex: 'created_at', title: t('account.date') },
];
</script>

<template>
    <Head :title="t('account.returns_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <Title :level="1" class="!mb-6 !text-2xl !font-bold">{{ t('account.returns_title') }}</Title>

        <EmptyState v-if="returns.data.length === 0" :message="t('account.no_returns')" />

        <template v-else>
            <Card :body-style="{ padding: '0' }">
                <Table :columns="columns" :data-source="returns.data" row-key="id" :pagination="false" :scroll="{ x: 'max-content' }">
                    <template #bodyCell="{ column, record }">
                        <span v-if="column.key === 'rma_number'" class="font-medium">{{ record.rma_number }}</span>
                        <StatusTag v-else-if="column.key === 'status'" :status="record.status" :label="record.status_label" />
                    </template>
                </Table>
            </Card>

            <Pagination :links="returns.links" />
        </template>
    </div>
</template>
