<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import DataTable, { type Column } from '@/components/admin/DataTable.vue';
import FilterBar from '@/components/admin/FilterBar.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/admin/Pagination.vue';
import PhoneLinks from '@/components/admin/PhoneLinks.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option, Paginator } from '@/components/admin/types';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button, Checkbox, Space, Typography } from 'ant-design-vue';
import { Bike, CheckCheck, Plus, Printer } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { Text } = Typography;

const { t } = useI18n();

interface OrderRow extends Record<string, unknown> {
    id: number;
    number: string;
    customer_name: string;
    customer_email: string;
    phone: string | null;
    city: string | null;
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    payment_method: string;
    payment_method_value: string;
    can_complete: boolean;
    driver: string | null;
    confirmed: boolean;
    no_answer_count: number;
    items_count: number;
    total_formatted: string;
    created_at: string;
}

const props = defineProps<{
    orders: Paginator<OrderRow>;
    statuses: Option[];
    paymentStatuses: Option[];
    paymentMethods: Option[];
    cities: Option[];
    drivers: Option[];
    filters: Record<string, unknown>;
    can: { manage: boolean };
}>();

const columns: Column[] = [
    { key: 'number', label: t('admin.common.order') },
    { key: 'customer_name', label: t('admin.common.customer') },
    { key: 'phone', label: t('admin.common.phone') },
    { key: 'city', label: t('admin.common.city') },
    { key: 'status', label: t('admin.common.status') },
    { key: 'payment_status', label: t('admin.common.payment') },
    { key: 'payment_method', label: t('admin.common.method') },
    { key: 'items_count', label: t('admin.common.items') },
    { key: 'total_formatted', label: t('admin.common.total') },
    { key: 'created_at', label: t('admin.common.date') },
];

/**
 * Dispatch works in batches: the driver comes back with a run of cash-on-
 * delivery orders, or a run is packed and needs its slips. Selection lives on
 * this page only and clears when the list changes.
 */
const selected = ref<number[]>([]);

watch(
    () => props.orders.data,
    () => (selected.value = []),
);

const allSelected = computed(() => props.orders.data.length > 0 && selected.value.length === props.orders.data.length);

function toggleAll(): void {
    selected.value = allSelected.value ? [] : props.orders.data.map((order) => order.id);
}

function toggleRow(id: number): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
}

/** Only in-progress orders can be completed; the rest of the selection is skipped (and the server re-checks). */
const completable = computed(() => props.orders.data.filter((order) => selected.value.includes(order.id) && order.can_complete).length);

const confirmingComplete = ref(false);
const completing = ref(false);

function completeSelected(): void {
    router.post(
        route('admin.orders.bulk-complete'),
        { ids: selected.value },
        {
            preserveScroll: true,
            onStart: () => (completing.value = true),
            onFinish: () => {
                completing.value = false;
                confirmingComplete.value = false;
            },
        },
    );
}

/** Loading a driver's run: every selected order that can go out is handed to them. */
const bulkDriver = ref<number | string | null>(null);
const dispatching = ref(false);

function dispatchSelected(): void {
    router.post(
        route('admin.orders.bulk-dispatch'),
        { ids: selected.value, driver_id: bulkDriver.value },
        {
            preserveScroll: true,
            onStart: () => (dispatching.value = true),
            onFinish: () => (dispatching.value = false),
        },
    );
}

/** A new tab: the list (and its selection) stays put while the run prints. */
function printSlips(): void {
    window.open(route('admin.orders.slips', { ids: selected.value }), '_blank', 'noopener');
}
</script>

<template>
    <Head :title="t('admin.orders.title')" />

    <PageHeader :title="t('admin.orders.title')" :subtitle="t('admin.orders.count', { count: orders.total })">
        <template #actions>
            <Link v-if="can.manage" :href="route('admin.orders.create')">
                <Button type="primary">
                    <template #icon><Plus class="h-4 w-4" /></template>
                    {{ t('admin.orders.new') }}
                </Button>
            </Link>
        </template>
    </PageHeader>

    <FilterBar
        :url="route('admin.orders.index')"
        :filters="filters"
        :search-placeholder="t('admin.orders.search')"
        dates
        :selects="[
            { key: 'status', label: t('admin.common.all_statuses'), options: statuses },
            { key: 'payment_status', label: t('admin.common.all_payment_states'), options: paymentStatuses },
            { key: 'payment_method', label: t('admin.orders.all_payment_methods'), options: paymentMethods },
            { key: 'city', label: t('admin.orders.all_cities'), options: cities },
            {
                key: 'confirmation',
                label: t('admin.orders.all_confirmation'),
                options: [
                    { value: 'unconfirmed', label: t('admin.orders.unconfirmed') },
                    { value: 'confirmed', label: t('admin.orders.confirmed') },
                ],
            },
            { key: 'driver_id', label: t('admin.orders.all_drivers'), options: drivers },
        ]"
    />

    <!-- Bulk bar: only while something is selected, so the list stays calm otherwise. -->
    <div
        v-if="selected.length > 0"
        class="admin-bulk-bar mb-3 mt-4 flex flex-wrap items-center gap-3"
        role="region"
        :aria-label="t('admin.orders.selected_count', { count: selected.length })"
    >
        <Text strong>{{ t('admin.orders.selected_count', { count: selected.length }) }}</Text>
        <Space wrap>
            <Button v-if="props.can.manage" type="primary" :disabled="completable === 0" @click="confirmingComplete = true">
                <template #icon><CheckCheck class="h-4 w-4" /></template>
                {{ t('admin.orders.bulk_complete') }}
            </Button>
            <template v-if="props.can.manage && drivers.length > 0">
                <AdminSelect v-model="bulkDriver" :options="drivers" :empty-label="t('admin.orders.choose_driver')" class="min-w-40" />
                <Button :disabled="!bulkDriver" :loading="dispatching" @click="dispatchSelected">
                    <template #icon><Bike class="h-4 w-4" /></template>
                    {{ t('admin.orders.bulk_dispatch') }}
                </Button>
            </template>
            <Button @click="printSlips">
                <template #icon><Printer class="h-4 w-4" /></template>
                {{ t('admin.orders.bulk_slips') }}
            </Button>
            <Button type="link" @click="selected = []">{{ t('admin.orders.clear_selection') }}</Button>
        </Space>
        <Text v-if="props.can.manage && completable === 0" type="secondary" class="!text-xs">{{ t('admin.orders.bulk_none_completable') }}</Text>
    </div>

    <DataTable :columns="columns" :rows="orders.data" class="mt-4">
        <template #select>
            <Checkbox
                :checked="allSelected"
                :indeterminate="selected.length > 0 && !allSelected"
                :aria-label="t('admin.orders.bulk_slips')"
                @change="toggleAll"
            />
        </template>
        <template #row-select="{ row }">
            <Checkbox :checked="selected.includes(row.id)" :aria-label="row.number" @change="toggleRow(row.id)" />
        </template>
        <template #cell-number="{ row }">
            <Link :href="route('admin.orders.show', row.id)" class="admin-link font-medium hover:underline">
                {{ row.number }}
            </Link>
        </template>
        <template #cell-customer_name="{ row }">
            <div>
                <p>{{ row.customer_name }}</p>
                <Text type="secondary" class="!text-xs">{{ row.customer_email }}</Text>
            </div>
        </template>
        <template #cell-phone="{ row }">
            <PhoneLinks :phone="row.phone" />
        </template>
        <template #cell-city="{ row }">{{ row.city ?? '—' }}</template>
        <template #cell-status="{ row }">
            <StatusBadge :status="row.status" :label="row.status_label" />
            <!-- What dispatch needs at a glance: confirmed yet? with which driver? -->
            <div v-if="row.status === 'in_progress'" class="mt-1 flex flex-wrap gap-1 text-xs">
                <Text v-if="row.driver" type="success">{{ row.driver }}</Text>
                <Text v-else-if="row.confirmed" type="secondary">{{ t('admin.orders.confirmed') }}</Text>
                <Text v-else type="warning">
                    {{ t('admin.orders.not_confirmed')
                    }}<template v-if="row.no_answer_count > 0"> · {{ t('admin.orders.no_answer_count', { count: row.no_answer_count }) }}</template>
                </Text>
            </div>
        </template>
        <template #cell-payment_status="{ row }">
            <StatusBadge :status="row.payment_status" :label="row.payment_status_label" />
        </template>
    </DataTable>

    <Pagination :paginator="orders" />

    <ConfirmDialog
        :open="confirmingComplete"
        :title="t('admin.orders.bulk_complete_title', { count: completable })"
        :message="t('admin.orders.bulk_complete_message')"
        :confirm-label="t('admin.orders.bulk_complete_ok', { count: completable })"
        :processing="completing"
        @confirm="completeSelected"
        @close="confirmingComplete = false"
    />
</template>
