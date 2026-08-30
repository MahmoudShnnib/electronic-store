<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { btnDanger, btnPrimary, btnSecondary } from '@/components/admin/ui';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: AdminLayout });

const { t } = useI18n();

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
        reviews_count: number;
    };
}>();

const confirmToggle = ref(false);

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
            <Link :href="route('admin.customers.index')" :class="btnSecondary">{{ t('admin.common.back') }}</Link>
            <button type="button" :class="customer.is_active ? btnDanger : btnPrimary" @click="confirmToggle = true">
                {{ customer.is_active ? t('admin.customers.deactivate_account') : t('admin.customers.activate_account') }}
            </button>
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.customers.profile') }}</h2>
                <dl class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.email') }}</dt>
                        <dd>{{ customer.email }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.phone') }}</dt>
                        <dd>{{ customer.phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.email_verified') }}</dt>
                        <dd>{{ customer.verified ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.common.status') }}</dt>
                        <dd><StatusBadge :status="customer.is_active ? 'active' : 'inactive'" /></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>{{ t('admin.nav.reviews') }}</dt>
                        <dd>{{ customer.reviews_count }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('admin.customers.addresses') }}</h2>
                <ul class="space-y-3 text-sm">
                    <li v-for="address in customer.addresses" :key="address.id" class="rounded-md border border-gray-100 p-3 dark:border-gray-800">
                        <p class="font-medium text-gray-900 dark:text-gray-100">
                            {{ address.label ?? 'Address' }}
                            <span v-if="address.is_default_shipping" class="ms-1 text-[10px] uppercase text-blue-500">{{
                                t('admin.customers.shipping')
                            }}</span>
                            <span v-if="address.is_default_billing" class="ms-1 text-[10px] uppercase text-purple-500">{{
                                t('admin.customers.billing')
                            }}</span>
                        </p>
                        <p class="text-gray-600 dark:text-gray-300">{{ address.name }} · {{ address.phone }}</p>
                        <p class="text-gray-500">
                            {{ [address.area, address.city, address.country].filter(Boolean).join(', ') }}
                        </p>
                    </li>
                    <li v-if="customer.addresses.length === 0" class="text-gray-400">{{ t('admin.customers.no_addresses') }}</li>
                </ul>
            </section>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-2">
            <h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700 dark:border-gray-800 dark:text-gray-300">
                {{ t('admin.customers.recent_orders') }}
            </h2>
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-for="order in customer.orders" :key="order.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2.5">
                            <Link :href="route('admin.orders.show', order.id)" class="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                {{ order.number }}
                            </Link>
                        </td>
                        <td class="px-4 py-2.5">{{ order.status_label }}</td>
                        <td class="px-4 py-2.5 font-medium">{{ order.total_formatted }}</td>
                        <td class="px-4 py-2.5 text-gray-400">{{ order.created_at }}</td>
                    </tr>
                    <tr v-if="customer.orders.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="4">{{ t('admin.customers.no_orders') }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>

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
