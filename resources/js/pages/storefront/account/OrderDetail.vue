<script setup lang="ts">
import StatusTag from '@/components/store/StatusTag.vue';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Card, Descriptions, DescriptionsItem, Popconfirm, Table, Timeline, TimelineItem, Typography } from 'ant-design-vue';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface AddressSnapshot {
    name?: string;
    phone?: string;
    country?: string;
    city?: string;
    area?: string | null;
    notes?: string | null;
}

const props = defineProps<{
    order: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        payment_status: string;
        payment_method: string;
        customer: { name?: string; email?: string; phone?: string };
        shipping_address: AddressSnapshot;
        billing_address: AddressSnapshot;
        discount_formatted: string;
        total_formatted: string;
        coupon_code: string | null;
        customer_note: string | null;
        created_at: string;
        cancellable: boolean;
        items: {
            id: number;
            name: string;
            sku: string;
            quantity: number;
            unit_price_formatted: string;
            line_total_formatted: string;
        }[];
        timeline: { status: string; comment: string | null; at: string }[];
        refunds: { amount_formatted: string; method: string; at: string }[];
    };
}>();

const { Title, Text } = Typography;

const { t } = useI18n();

function cancelOrder() {
    router.post(route('account.orders.cancel', props.order.id), {}, { preserveScroll: true });
}

function formatAddress(address: AddressSnapshot): string {
    return [address.name, address.phone, address.area, address.city, address.country].filter(Boolean).join(' · ');
}

const itemColumns = [
    { key: 'name', title: t('account.items') },
    { key: 'unit', title: '' },
    { key: 'total', title: '', customHeaderCell: () => ({ class: 'text-end' }), customCell: () => ({ class: 'text-end' }) },
];
</script>

<template>
    <Head :title="`${t('account.order')} ${order.number}`" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link :href="route('account.orders.index')">
                    <Button shape="circle" :aria-label="t('common.back')">
                        <template #icon><ArrowLeft class="h-4 w-4 rtl:rotate-180" /></template>
                    </Button>
                </Link>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Title :level="1" class="!mb-0 !text-xl !font-bold">{{ order.number }}</Title>
                        <StatusTag :status="order.status" :label="order.status_label" />
                    </div>
                    <Text class="!text-sm opacity-60">{{ order.created_at }}</Text>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Popconfirm
                    v-if="order.cancellable"
                    :title="t('account.cancel_confirm')"
                    :ok-text="t('account.cancel_order')"
                    :cancel-text="t('common.cancel')"
                    ok-type="danger"
                    @confirm="cancelOrder"
                >
                    <Button danger>{{ t('account.cancel_order') }}</Button>
                </Popconfirm>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Items -->
                <Card :body-style="{ padding: '0' }">
                    <Table :columns="itemColumns" :data-source="order.items" row-key="id" :pagination="false" :scroll="{ x: 'max-content' }">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'name'">
                                <p class="font-medium">{{ record.name }}</p>
                                <Text class="!text-xs opacity-55">{{ record.sku }}</Text>
                            </template>
                            <Text v-else-if="column.key === 'unit'" class="opacity-60" dir="auto">
                                {{ record.unit_price_formatted }} × {{ record.quantity }}
                            </Text>
                            <span v-else-if="column.key === 'total'" class="store-money font-semibold">{{ record.line_total_formatted }}</span>
                        </template>
                    </Table>
                </Card>

                <!-- Timeline -->
                <Card :title="t('account.timeline')">
                    <Timeline class="!mt-2">
                        <TimelineItem v-for="(entry, index) in order.timeline" :key="index" :color="index === 0 ? 'green' : 'gray'">
                            <p class="text-sm font-medium">{{ entry.status }}</p>
                            <Text v-if="entry.comment" class="!block !text-xs opacity-60" dir="auto">{{ entry.comment }}</Text>
                            <Text class="!block !text-xs opacity-55">{{ entry.at }}</Text>
                        </TimelineItem>
                    </Timeline>
                </Card>
            </div>

            <aside class="space-y-6">
                <!-- Totals -->
                <Card>
                    <dl class="store-totals">
                        <div class="store-totals-row">
                            <dt class="opacity-60">
                                {{ t('cart.discount') }} <template v-if="order.coupon_code">({{ order.coupon_code }})</template>
                            </dt>
                            <dd>{{ order.discount_formatted }}</dd>
                        </div>
                        <div class="store-totals-row store-totals-grand">
                            <dt>{{ t('cart.total') }}</dt>
                            <dd>{{ order.total_formatted }}</dd>
                        </div>
                    </dl>

                    <Text class="!mt-3 !block !text-sm opacity-60">{{ t('account.payment') }}: {{ order.payment_method }}</Text>

                    <div v-if="order.refunds.length > 0" class="mt-3 border-t border-black/10 pt-3 dark:border-white/10">
                        <Text v-for="(refund, index) in order.refunds" :key="index" class="!block !text-sm opacity-60">
                            ↩ {{ refund.amount_formatted }} — {{ refund.method }} ({{ refund.at }})
                        </Text>
                    </div>
                </Card>

                <!-- Addresses -->
                <Card size="small">
                    <Descriptions :column="1" size="small" layout="vertical" :colon="false">
                        <DescriptionsItem :label="t('checkout.shipping_address')">
                            <span dir="auto">{{ formatAddress(order.shipping_address) }}</span>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </aside>
        </div>
    </div>
</template>
