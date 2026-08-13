<script setup lang="ts">
import AdminSelect from '@/components/admin/AdminSelect.vue';
import Field from '@/components/admin/Field.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Option } from '@/components/admin/types';
import { btnPrimary, btnSecondary, inputClass } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

interface ReturnItem {
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
        reason: string;
        customer_note: string | null;
        admin_note: string | null;
        images: string[];
        reviewed_by: string | null;
        reviewed_at: string | null;
        received_at: string | null;
        created_at: string;
        items: ReturnItem[];
        refund_total_formatted: string;
        suggested_refund_cents: number;
    };
    warehouses: { id: number; name: string }[];
}>();

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
            <Link :href="route('admin.returns.index')" :class="btnSecondary">{{ t('admin.common.back') }}</Link>
            <Link :href="route('admin.orders.show', productReturn.order.id)" :class="btnSecondary">
                {{ t('admin.returns.order_link', { number: productReturn.order.number }) }}
            </Link>
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950/50">
                        <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-3 py-2.5 text-start">{{ t('admin.common.item') }}</th>
                            <th class="px-3 py-2.5 text-start">{{ t('admin.common.sku') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.qty') }}</th>
                            <th class="px-3 py-2.5 text-end">{{ t('admin.common.unit_price') }}</th>
                            <th class="px-3 py-2.5 text-start">{{ t('admin.returns.condition') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="item in productReturn.items" :key="item.id">
                            <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</td>
                            <td class="px-3 py-2.5 text-gray-500">{{ item.sku }}</td>
                            <td class="px-3 py-2.5 text-end">{{ item.quantity }}</td>
                            <td class="px-3 py-2.5 text-end">{{ item.unit_price_formatted }}</td>
                            <td class="px-3 py-2.5">
                                <template v-if="productReturn.status === 'approved'">
                                    <AdminSelect
                                        v-model="receiveForm.conditions[item.id]"
                                        class="w-36"
                                        :options="[
                                            { value: 'resellable', label: t('admin.returns.resellable') },
                                            { value: 'damaged', label: t('admin.returns.damaged') },
                                        ]"
                                    />
                                </template>
                                <StatusBadge v-else-if="item.condition" :status="item.condition" />
                                <span v-else class="text-gray-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.returns.request_details') }}</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase text-gray-400">{{ t('admin.common.reason') }}</dt>
                        <dd class="capitalize text-gray-800 dark:text-gray-200">{{ productReturn.reason.replaceAll('_', ' ') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-gray-400">{{ t('admin.common.customer') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-200">
                            {{ productReturn.customer?.name ?? '—' }}
                            <span class="text-xs text-gray-400">{{ productReturn.customer?.email }}</span>
                        </dd>
                    </div>
                    <div v-if="productReturn.customer_note" class="sm:col-span-2">
                        <dt class="text-xs uppercase text-gray-400">{{ t('admin.returns.customer_explanation') }}</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ productReturn.customer_note }}</dd>
                    </div>
                </dl>

                <div v-if="productReturn.images.length > 0" class="mt-4">
                    <p class="mb-2 text-xs uppercase text-gray-400">{{ t('admin.returns.photos') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(image, i) in productReturn.images"
                            :key="i"
                            type="button"
                            class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
                            @click="lightbox = image"
                        >
                            <img :src="image" :alt="t('admin.returns.photo_alt')" class="h-20 w-20 object-cover" />
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.status') }}</h2>
                    <StatusBadge :status="productReturn.status" :label="productReturn.status_label" />
                </div>
                <p v-if="productReturn.reviewed_by" class="mb-3 text-xs text-gray-400">
                    {{ t('admin.returns.reviewed_by', { name: productReturn.reviewed_by, date: productReturn.reviewed_at }) }}
                </p>

                <Field :label="t('admin.common.admin_note')" :error="reviewForm.errors.admin_note">
                    <textarea v-model="reviewForm.admin_note" rows="2" :class="inputClass" />
                </Field>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="t in productReturn.allowed_transitions.filter((t) =>
                            ['under_review', 'approved', 'rejected', 'closed'].includes(t.value),
                        )"
                        :key="t.value"
                        type="button"
                        :disabled="reviewForm.processing"
                        :class="
                            t.value === 'rejected'
                                ? 'bg-red-600 text-white hover:bg-red-700'
                                : t.value === 'approved'
                                  ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                  : 'bg-gray-600 text-white hover:bg-gray-700'
                        "
                        class="rounded-md px-3 py-1.5 text-sm font-medium disabled:opacity-50"
                        @click="submitReview(t.value)"
                    >
                        {{ t.label }}
                    </button>
                </div>
                <p v-if="reviewForm.errors.status" class="mt-2 text-xs text-red-600">{{ reviewForm.errors.status }}</p>
            </section>

            <section
                v-if="productReturn.status === 'approved'"
                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.returns.receive_items') }}</h2>
                <p class="mb-3 text-xs text-gray-400">
                    {{ t('admin.returns.receive_hint') }}
                </p>
                <Field :label="t('admin.common.warehouse')" :error="receiveForm.errors.warehouse_id">
                    <AdminSelect v-model="receiveForm.warehouse_id" :options="warehouses.map((w) => ({ value: w.id, label: w.name }))" />
                </Field>
                <button
                    type="button"
                    :disabled="receiveForm.processing"
                    :class="btnPrimary"
                    class="mt-3 w-full justify-center"
                    @click="submitReceive"
                >
                    {{ t('admin.returns.confirm_receipt') }}
                </button>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.common.refund') }}</h2>
                <dl class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between">
                        <dt>{{ t('admin.returns.refunded_so_far') }}</dt>
                        <dd>{{ productReturn.refund_total_formatted }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.returns.suggested_amount') }}</dt>
                        <dd>{{ (productReturn.suggested_refund_cents / 100).toFixed(2) }}</dd>
                    </div>
                </dl>
                <Link :href="route('admin.orders.show', productReturn.order.id)" :class="btnSecondary" class="mt-3 w-full justify-center">{{
                    t('admin.returns.issue_refund_on_order')
                }}</Link>
            </section>
        </div>
    </div>

    <!-- Lightbox -->
    <Teleport to="body">
        <div v-if="lightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-6" @click="lightbox = null">
            <img :src="lightbox" :alt="t('admin.returns.photo_alt')" class="max-h-full max-w-full rounded-lg" />
        </div>
    </Teleport>
</template>
