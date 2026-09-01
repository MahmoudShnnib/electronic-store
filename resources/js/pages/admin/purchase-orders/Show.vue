<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import FormModal from '@/components/admin/FormModal.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { TableColumnType } from 'ant-design-vue';
import { Button, Card, Descriptions, InputNumber, Table, Typography } from 'ant-design-vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { Text, Paragraph } = Typography;
const DescriptionsItem = Descriptions.Item;

const { t } = useI18n();

interface PoItem {
    id: number;
    name: string;
    sku: string;
    quantity_ordered: number;
    quantity_received: number;
    outstanding: number;
    unit_cost_formatted: string;
    line_total_formatted: string;
}

const props = defineProps<{
    purchaseOrder: {
        id: number;
        po_number: string;
        supplier: { id: number; company_name: string; contact_person: string | null; email: string | null; phone: string | null };
        warehouse: { id: number; name: string };
        status: string;
        status_label: string;
        is_editable: boolean;
        is_receivable: boolean;
        subtotal_formatted: string;
        discount_formatted: string;
        tax_formatted: string;
        shipping_formatted: string;
        total_formatted: string;
        expected_at: string | null;
        received_at: string | null;
        notes: string | null;
        created_by: string | null;
        created_at: string;
        items: PoItem[];
    };
}>();

/** `text-end` (not AntD's `align`) so the numeric columns follow the RTL flip. */
const endCell = {
    customCell: () => ({ class: 'text-end' }),
    customHeaderCell: () => ({ class: 'text-end' }),
};

const itemColumns: TableColumnType<PoItem>[] = [
    { key: 'name', dataIndex: 'name', title: t('admin.common.item'), customCell: () => ({ class: 'font-medium' }) },
    { key: 'sku', dataIndex: 'sku', title: t('admin.common.sku') },
    { key: 'quantity_ordered', dataIndex: 'quantity_ordered', title: t('admin.purchase_orders.ordered'), ...endCell },
    { key: 'quantity_received', dataIndex: 'quantity_received', title: t('admin.purchase_orders.received'), ...endCell },
    {
        key: 'outstanding',
        dataIndex: 'outstanding',
        title: t('admin.common.outstanding'),
        customHeaderCell: () => ({ class: 'text-end' }),
        customCell: (record: PoItem) => ({
            class: record.outstanding > 0 ? 'text-end font-semibold text-amber-600' : 'text-end text-emerald-600',
        }),
    },
    { key: 'unit_cost_formatted', dataIndex: 'unit_cost_formatted', title: t('admin.common.unit_cost'), ...endCell },
    {
        key: 'line_total_formatted',
        dataIndex: 'line_total_formatted',
        title: t('admin.common.line_total'),
        customHeaderCell: () => ({ class: 'text-end' }),
        customCell: () => ({ class: 'text-end font-medium' }),
    },
];

const receiveOpen = ref(false);
const cancelOpen = ref(false);

const receiveForm = useForm({
    quantities: {} as Record<number, number>,
});

function openReceive(): void {
    receiveForm.clearErrors();
    receiveForm.quantities = Object.fromEntries(props.purchaseOrder.items.filter((i) => i.outstanding > 0).map((i) => [i.id, i.outstanding]));
    receiveOpen.value = true;
}

function submitReceive(): void {
    receiveForm.post(route('admin.purchase-orders.receive', props.purchaseOrder.id), {
        preserveScroll: true,
        onSuccess: () => (receiveOpen.value = false),
    });
}

function send(): void {
    router.post(route('admin.purchase-orders.send', props.purchaseOrder.id), {}, { preserveScroll: true });
}

function cancel(): void {
    router.post(
        route('admin.purchase-orders.cancel', props.purchaseOrder.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (cancelOpen.value = false),
        },
    );
}
</script>

<template>
    <Head :title="purchaseOrder.po_number" />

    <PageHeader
        :title="purchaseOrder.po_number"
        :subtitle="t('admin.purchase_orders.created', { date: purchaseOrder.created_at, by: purchaseOrder.created_by ?? '—' })"
    >
        <template #actions>
            <Link :href="route('admin.purchase-orders.index')">
                <Button>{{ t('admin.common.back') }}</Button>
            </Link>
            <Link v-if="purchaseOrder.is_editable" :href="route('admin.purchase-orders.edit', purchaseOrder.id)">
                <Button>{{ t('admin.common.edit') }}</Button>
            </Link>
            <Button v-if="purchaseOrder.is_editable" type="primary" @click="send">
                {{ t('admin.purchase_orders.send_to_supplier') }}
            </Button>
            <Button v-if="purchaseOrder.is_receivable" type="primary" @click="openReceive">
                {{ t('admin.purchase_orders.receive_stock') }}
            </Button>
            <Button v-if="purchaseOrder.status !== 'received' && purchaseOrder.status !== 'cancelled'" @click="cancelOpen = true">
                {{ t('admin.purchase_orders.cancel_po') }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <Table
                :columns="itemColumns"
                :data-source="purchaseOrder.items"
                row-key="id"
                :pagination="false"
                size="middle"
                :scroll="{ x: 'max-content' }"
            />
        </div>

        <div class="space-y-6">
            <Card :title="t('admin.common.status')" size="small">
                <template #extra>
                    <StatusBadge :status="purchaseOrder.status" :label="purchaseOrder.status_label" />
                </template>
                <Descriptions :column="1" size="small">
                    <DescriptionsItem :label="t('admin.common.warehouse')">{{ purchaseOrder.warehouse.name }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.expected')">{{ purchaseOrder.expected_at ?? '—' }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.purchase_orders.fully_received')">
                        {{ purchaseOrder.received_at ?? '—' }}
                    </DescriptionsItem>
                </Descriptions>
            </Card>

            <Card :title="t('admin.common.supplier')" size="small">
                <p class="text-sm font-medium">{{ purchaseOrder.supplier.company_name }}</p>
                <Text type="secondary" class="block !text-sm">{{ purchaseOrder.supplier.contact_person }}</Text>
                <Text type="secondary" class="block !text-sm">{{ purchaseOrder.supplier.email }}</Text>
                <Text type="secondary" class="block !text-sm">{{ purchaseOrder.supplier.phone }}</Text>
            </Card>

            <Card :title="t('admin.purchase_orders.totals')" size="small">
                <Descriptions :column="1" size="small">
                    <DescriptionsItem :label="t('admin.common.subtotal')">{{ purchaseOrder.subtotal_formatted }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.discount')">
                        <bdi dir="ltr">−{{ purchaseOrder.discount_formatted }}</bdi>
                    </DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.tax')">{{ purchaseOrder.tax_formatted }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.shipping')">{{ purchaseOrder.shipping_formatted }}</DescriptionsItem>
                    <DescriptionsItem :label="t('admin.common.total')">
                        <span class="text-base font-semibold">{{ purchaseOrder.total_formatted }}</span>
                    </DescriptionsItem>
                </Descriptions>
            </Card>

            <Card v-if="purchaseOrder.notes" :title="t('admin.common.notes')" size="small">
                <Paragraph class="!mb-0 whitespace-pre-line !text-sm">{{ purchaseOrder.notes }}</Paragraph>
            </Card>
        </div>
    </div>

    <!-- Receive modal -->
    <FormModal :open="receiveOpen" :title="t('admin.purchase_orders.receive_stock')" @close="receiveOpen = false">
        <form class="grid gap-4" @submit.prevent="submitReceive">
            <Text type="secondary" class="!text-sm">{{ t('admin.purchase_orders.receive_hint') }}</Text>
            <div v-for="item in purchaseOrder.items.filter((i) => i.outstanding > 0)" :key="item.id" class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ item.name }}</p>
                    <Text type="secondary" class="!text-xs">{{ t('admin.purchase_orders.outstanding_n', { count: item.outstanding }) }}</Text>
                </div>
                <InputNumber v-model:value="receiveForm.quantities[item.id]" :min="0" :max="item.outstanding" class="w-24" />
            </div>
            <Text v-if="receiveForm.errors.quantities" type="danger" class="!text-xs">{{ receiveForm.errors.quantities }}</Text>
            <div class="flex justify-end gap-3">
                <Button @click="receiveOpen = false">{{ t('admin.common.cancel') }}</Button>
                <Button type="primary" html-type="submit" :loading="receiveForm.processing">{{ t('admin.purchase_orders.receive') }}</Button>
            </div>
        </form>
    </FormModal>

    <ConfirmDialog
        :open="cancelOpen"
        :title="t('admin.purchase_orders.cancel_title')"
        :message="t('admin.purchase_orders.cancel_confirm', { number: purchaseOrder.po_number })"
        :confirm-label="t('admin.purchase_orders.cancel_po')"
        danger
        @confirm="cancel"
        @close="cancelOpen = false"
    />
</template>
