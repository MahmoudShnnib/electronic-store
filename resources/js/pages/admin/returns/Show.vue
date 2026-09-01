<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button, Card, Descriptions, Modal, Space, Textarea, Typography } from 'ant-design-vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { Text } = Typography;
const DescriptionsItem = Descriptions.Item;

const { t } = useI18n();

interface ReturnItem extends Record<string, unknown> {
    id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price_formatted: string;
    condition: string | null;
    restock: boolean | null;
}

const props = defineProps<{
    productReturn: {
        id: number;
        rma_number: string;
        order: { id: number; number: string; payment_status: string };
        customer: { id: number; name: string; email: string } | null;
        status: string;
        status_label: string;
        allowed_transitions: Option[];
        reason_label: string;
        customer_note: string | null;
        admin_note: string | null;
        images: string[];
        reviewed_by: string | null;
        reviewed_at: string | null;
        received_at: string | null;
        created_at: string;
        items: ReturnItem[];
        refund_total_formatted: string;
        suggested_refund_formatted: string;
    };
    warehouses: { id: number; name: string }[];
}>();

const itemColumns: Column[] = [
    { key: 'name', label: t('admin.common.item') },
    { key: 'sku', label: t('admin.common.sku') },
    { key: 'quantity', label: t('admin.common.qty'), class: 'text-end' },
    { key: 'unit_price_formatted', label: t('admin.common.unit_price'), class: 'text-end' },
    { key: 'condition', label: t('admin.returns.condition') },
];

const reviewForm = useForm({
    status: '',
    admin_note: props.productReturn.admin_note ?? '',
});

function submitReview(status: string): void {
    reviewForm.status = status;
    reviewForm.post(route('admin.returns.review', props.productReturn.id), { preserveScroll: true });
}

const receiveForm = useForm({
    warehouse_id: props.warehouses[0]?.id ?? 0,
    conditions: Object.fromEntries(props.productReturn.items.map((i) => [i.id, 'resellable'])) as Record<number, string>,
});

function submitReceive(): void {
    receiveForm.post(route('admin.returns.receive', props.productReturn.id), { preserveScroll: true });
}

const lightbox = ref<string | null>(null);
</script>

<template>
    <Head :title="productReturn.rma_number" />

    <PageHeader :title="productReturn.rma_number" :subtitle="t('admin.returns.requested_at', { date: productReturn.created_at })">
        <template #actions>
            <Link :href="route('admin.returns.index')">
                <Button>{{ t('admin.common.back') }}</Button>
            </Link>
            <Link :href="route('admin.orders.show', productReturn.order.id)">
                <Button>{{ t('admin.returns.order_link', { number: productReturn.order.number }) }}</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <DataTable :columns="itemColumns" :rows="productReturn.items">
                <template #cell-name="{ row }">
                    <span class="font-medium">{{ row.name }}</span>
                </template>
                <template #cell-sku="{ row }">
                    <Text type="secondary">{{ row.sku }}</Text>
                </template>
                <template #cell-condition="{ row }">
                    <AdminSelect
                        v-if="productReturn.status === 'approved'"
                        v-model="receiveForm.conditions[row.id]"
                        class="w-36"
                        :options="[
                            { value: 'resellable', label: t('admin.returns.resellable') },
                            { value: 'damaged', label: t('admin.returns.damaged') },
                        ]"
                    />
                    <StatusBadge v-else-if="row.condition" :status="row.condition" />
                    <Text v-else type="secondary">—</Text>
                </template>
            </DataTable>

            <Card :title="t('admin.returns.request_details')" size="small">
                <Descriptions :column="{ xs: 1, sm: 2 }" size="small" layout="vertical" :colon="false">
                    <DescriptionsItem :label="t('admin.common.reason')">{{ productReturn.reason_label }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.customer')">
                        <!-- The item content is a flex row, so name and email need a column
                             of their own; inline they rendered as "QA Customercustomer@…". -->
                        <div class="flex flex-col items-start">
                            <span dir="auto">{{ productReturn.customer?.name ?? '—' }}</span>
                            <Text v-if="productReturn.customer?.email" dir="auto" type="secondary" class="!text-xs">
                                {{ productReturn.customer.email }}
                            </Text>
                        </div>
                    </DescriptionsItem>
                    <DescriptionsItem v-if="productReturn.customer_note" :label="t('admin.returns.customer_explanation')" :span="2">
                        {{ productReturn.customer_note }}
                    </DescriptionsItem>
                </Descriptions>

                <div v-if="productReturn.images.length > 0" class="mt-4">
                    <Text type="secondary" class="mb-2 block !text-xs uppercase">{{ t('admin.returns.photos') }}</Text>
                    <div class="flex flex-wrap gap-2">
                        <!-- Raw button: it is a thumbnail trigger for the custom lightbox below, not chrome. -->
                        <button
                            v-for="(image, i) in productReturn.images"
                            :key="i"
                            type="button"
                            class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
                            @click="lightbox = image"
                        >
                            <img :src="image" :alt="t('admin.returns.photo_alt')" width="80" height="80" loading="lazy" class="h-20 w-20 object-cover" />
                        </button>
                    </div>
                </div>
            </Card>
        </div>

        <div class="space-y-6">
            <Card :title="t('admin.common.status')" size="small">
                <template #extra>
                    <StatusBadge :status="productReturn.status" :label="productReturn.status_label" />
                </template>

                <Text v-if="productReturn.reviewed_by" type="secondary" class="mb-3 block !text-xs">
                    {{ t('admin.returns.reviewed_by', { name: productReturn.reviewed_by, date: productReturn.reviewed_at }) }}
                </Text>

                <Field :label="t('admin.common.admin_note')" :error="reviewForm.errors.admin_note">
                    <Textarea v-model:value="reviewForm.admin_note" :rows="2" />
                </Field>

                <Space wrap>
                    <Button
                        v-for="transition in productReturn.allowed_transitions.filter((option) =>
                            ['under_review', 'approved', 'rejected', 'closed'].includes(option.value),
                        )"
                        :key="transition.value"
                        :type="transition.value === 'approved' ? 'primary' : 'default'"
                        :danger="transition.value === 'rejected'"
                        :disabled="reviewForm.processing"
                        :loading="reviewForm.processing && reviewForm.status === transition.value"
                        @click="submitReview(transition.value)"
                    >
                        {{ transition.label }}
                    </Button>
                </Space>
                <Text v-if="reviewForm.errors.status" type="danger" class="mt-2 block !text-xs">{{ reviewForm.errors.status }}</Text>
            </Card>

            <Card v-if="productReturn.status === 'approved'" :title="t('admin.returns.receive_items')" size="small">
                <Text type="secondary" class="mb-3 block !text-xs">{{ t('admin.returns.receive_hint') }}</Text>
                <Field :label="t('admin.common.warehouse')" :error="receiveForm.errors.warehouse_id">
                    <AdminSelect v-model="receiveForm.warehouse_id" :options="warehouses.map((w) => ({ value: w.id, label: w.name }))" />
                </Field>
                <Button type="primary" block :loading="receiveForm.processing" @click="submitReceive">
                    {{ t('admin.returns.confirm_receipt') }}
                </Button>
            </Card>

            <Card :title="t('admin.common.refund')" size="small">
                <Descriptions :column="1" size="small">
                    <DescriptionsItem :label="t('admin.returns.refunded_so_far')">{{ productReturn.refund_total_formatted }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.returns.suggested_amount')">{{ productReturn.suggested_refund_formatted }}</DescriptionsItem>
                </Descriptions>
                <Link :href="route('admin.orders.show', productReturn.order.id)" class="mt-3 block">
                    <Button block>{{ t('admin.returns.issue_refund_on_order') }}</Button>
                </Link>
            </Card>
        </div>
    </div>

    <!-- Lightbox. An AntD Modal rather than a hand-rolled overlay: Escape, the
         focus trap and the restore-focus-on-close all come with it, and the
         hand-rolled version had none of the three. -->
    <Modal
        :open="lightbox !== null"
        :footer="null"
        :title="t('admin.returns.photo_alt')"
        centered
        width="min(90vw, 900px)"
        @cancel="lightbox = null"
    >
        <img v-if="lightbox" :src="lightbox" :alt="t('admin.returns.photo_alt')" class="max-h-[75vh] w-full rounded-lg object-contain" />
    </Modal>
</template>
