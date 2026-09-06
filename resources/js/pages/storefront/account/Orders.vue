<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import Pagination from '@/components/store/Pagination.vue';
import StatusTag from '@/components/store/StatusTag.vue';
import type { PaginationLink } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Card, Popconfirm, Table, Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface OrderRow {
    id: number;
    number: string;
    status: string;
    status_label: string;
    payment_status: string;
    total_formatted: string;
    items_count: number;
    created_at: string;
    cancellable: boolean;
}

defineProps<{
    orders: { data: OrderRow[]; links: PaginationLink[] };
}>();

const { Title } = Typography;

const { t } = useI18n();

function cancelOrder(id: number) {
    router.post(route('account.orders.cancel', id), {}, { preserveScroll: true });
}

const columns = [
    { key: 'number', dataIndex: 'number', title: t('account.order') },
    { key: 'created_at', dataIndex: 'created_at', title: t('account.date') },
    { key: 'items_count', dataIndex: 'items_count', title: t('account.items') },
    { key: 'status', title: t('account.status') },
    { key: 'total', title: t('account.total'), customHeaderCell: () => ({ class: 'text-end' }), customCell: () => ({ class: 'text-end' }) },
    { key: 'actions', title: '', customHeaderCell: () => ({ class: 'text-end' }), customCell: () => ({ class: 'text-end' }) },
];
</script>

<template>
    <Head :title="t('account.orders_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <Title :level="1" class="!mb-6 !text-2xl !font-bold">{{ t('account.orders_title') }}</Title>

        <EmptyState
            v-if="orders.data.length === 0"
            :message="t('account.no_orders')"
            :action-label="t('cart.continue_shopping')"
            :action-href="route('catalog.index')"
        />

        <template v-else>
            <Card :body-style="{ padding: '0' }">
                <Table :columns="columns" :data-source="orders.data" row-key="id" :pagination="false" :scroll="{ x: 'max-content' }">
                    <template #bodyCell="{ column, record }">
                        <span v-if="column.key === 'number'" class="font-medium">{{ record.number }}</span>

                        <StatusTag v-else-if="column.key === 'status'" :status="record.status" :label="record.status_label" />

                        <span v-else-if="column.key === 'total'" class="store-money font-semibold">{{ record.total_formatted }}</span>

                        <div v-else-if="column.key === 'actions'" class="-me-1 flex items-center justify-end gap-1">
                            <Link :href="route('account.orders.show', record.id)">
                                <Button type="link" size="small">{{ t('account.view') }}</Button>
                            </Link>

                            <!-- `Popconfirm`, not `window.confirm`: a native dialog blocks the
                                 whole page and cannot be styled or translated. -->
                            <Popconfirm
                                v-if="record.cancellable"
                                :title="t('account.cancel_confirm')"
                                :ok-text="t('account.cancel_order')"
                                :cancel-text="t('common.cancel')"
                                ok-type="danger"
                                @confirm="cancelOrder(record.id)"
                            >
                                <Button type="link" size="small" danger>{{ t('account.cancel_order') }}</Button>
                            </Popconfirm>
                        </div>
                    </template>
                </Table>
            </Card>

            <Pagination :links="orders.links" />
        </template>
    </div>
</template>
