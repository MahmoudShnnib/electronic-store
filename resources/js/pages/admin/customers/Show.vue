<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { TableColumnType } from 'ant-design-vue';
import { Button, Card, Col, Descriptions, DescriptionsItem, List, ListItem, Row, Table, Tag, Typography } from 'ant-design-vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

const { Text } = Typography;

interface AddressRow {
    id: number;
    label: string | null;
    name: string;
    phone: string;
    country: string;
    city: string;
    area: string | null;
    is_default_shipping: boolean;
    is_default_billing: boolean;
}

const props = defineProps<{
    customer: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        is_active: boolean;
        verified: boolean;
        joined_at: string;
        addresses: AddressRow[];
        orders: { id: number; number: string; status_label: string; total_formatted: string; created_at: string }[];
        returns_count: number;
        reviews_count: number;
    };
}>();

const confirmToggle = ref(false);

/** The original markup had no header row; AntD needs titles, so reuse the shared column labels. */
const orderColumns: TableColumnType[] = [
    { key: 'number', dataIndex: 'number', title: t('admin.common.order') },
    { key: 'status_label', dataIndex: 'status_label', title: t('admin.common.status') },
    { key: 'total_formatted', dataIndex: 'total_formatted', title: t('admin.common.total') },
    { key: 'created_at', dataIndex: 'created_at', title: t('admin.common.date') },
];

function toggleActive(): void {
    router.post(
        route('admin.customers.toggle-active', props.customer.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (confirmToggle.value = false),
        },
    );
}
</script>

<template>
    <Head :title="customer.name" />

    <PageHeader :title="customer.name" :subtitle="t('admin.customers.joined', { date: customer.joined_at })">
        <template #actions>
            <Link :href="route('admin.customers.index')">
                <Button>{{ t('admin.common.back') }}</Button>
            </Link>
            <!-- Reactivating is the constructive action and gets the solid primary;
                 deactivating stays red but outlined, so the loudest control on the
                 page is never the destructive one. -->
            <Button :type="customer.is_active ? 'default' : 'primary'" :danger="customer.is_active" @click="confirmToggle = true">
                {{ customer.is_active ? t('admin.customers.deactivate_account') : t('admin.customers.activate_account') }}
            </Button>
        </template>
    </PageHeader>

    <Row :gutter="[16, 16]">
        <Col :xs="24" :xl="8">
            <Card :title="t('admin.customers.profile')" size="small">
                <Descriptions :column="1" size="small">
                    <DescriptionsItem :label="t('admin.common.email')">{{ customer.email }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.phone')">{{ customer.phone ?? '—' }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.email_verified')">{{ customer.verified ? t('admin.common.yes') : t('admin.common.no') }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.status')">
                        <StatusBadge :status="customer.is_active ? 'active' : 'inactive'" />
                    </DescriptionsItem>
                    <DescriptionsItem :label="t('admin.nav.returns')">{{ customer.returns_count }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.nav.reviews')">{{ customer.reviews_count }}</DescriptionsItem>
                </Descriptions>
            </Card>

            <Card :title="t('admin.customers.addresses')" size="small" class="mt-4">
                <List size="small" :data-source="customer.addresses" row-key="id" :locale="{ emptyText: t('admin.customers.no_addresses') }">
                    <template #renderItem="{ item }">
                        <ListItem>
                            <div>
                                <p class="mb-1 font-medium">
                                    {{ item.label ?? t('admin.common.address') }}
                                    <Tag v-if="item.is_default_shipping" color="blue" class="ms-1">{{ t('admin.customers.shipping') }}</Tag>
                                    <Tag v-if="item.is_default_billing" color="purple" class="ms-1">{{ t('admin.customers.billing') }}</Tag>
                                </p>
                                <p class="mb-0">{{ item.name }} · {{ item.phone }}</p>
                                <Text type="secondary" class="!text-xs">
                                    {{ [item.area, item.city, item.country].filter(Boolean).join(', ') }}
                                </Text>
                            </div>
                        </ListItem>
                    </template>
                </List>
            </Card>
        </Col>

        <Col :xs="24" :xl="16">
            <Card :title="t('admin.customers.recent_orders')" size="small" :body-style="{ padding: 0 }">
                <Table
                    :columns="orderColumns"
                    :data-source="customer.orders"
                    row-key="id"
                    :pagination="false"
                    size="middle"
                    :scroll="{ x: 'max-content' }"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'number'">
                            <Link
                                :href="route('admin.orders.show', record.id)"
                                class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >{{ record.number }}</Link
                            >
                        </template>
                        <template v-else-if="column.key === 'total_formatted'">
                            <span class="font-medium">{{ record.total_formatted }}</span>
                        </template>
                        <template v-else-if="column.key === 'created_at'">
                            <Text type="secondary">{{ record.created_at }}</Text>
                        </template>
                    </template>
                    <template #emptyText>{{ t('admin.customers.no_orders') }}</template>
                </Table>
            </Card>
        </Col>
    </Row>

    <ConfirmDialog
        :open="confirmToggle"
        :title="customer.is_active ? t('admin.customers.deactivate_account') : t('admin.customers.activate_account')"
        :message="
            customer.is_active
                ? t('admin.customers.deactivate_confirm', { name: customer.name })
                : t('admin.customers.reactivate_confirm', { name: customer.name })
        "
        :confirm-label="customer.is_active ? t('admin.common.deactivate') : t('admin.common.activate')"
        :danger="customer.is_active"
        @confirm="toggleActive"
        @close="confirmToggle = false"
    />
</template>
