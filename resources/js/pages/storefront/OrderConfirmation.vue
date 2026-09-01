<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Alert, Button, Card, Result, Typography } from 'ant-design-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

defineProps<{
    order: {
        number: string;
        total_formatted: string;
        payment_method: string;
        status: string;
        items: { name: string; quantity: number; line_total_formatted: string }[];
    };
    paymentInstructions: string | null;
}>();

const { Text } = Typography;

const { t } = useI18n();
const page = usePage();
const authenticated = computed(() => (page.props.auth as { user: unknown | null }).user !== null);
</script>

<template>
    <Head :title="t('checkout.confirmation_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mx-auto max-w-xl">
            <!-- `Result` is AntD's own end-of-flow page; it already owns the
                 icon, the heading hierarchy and the action row. -->
            <Result
                status="success"
                :title="t('checkout.confirmation_title')"
                :sub-title="t('checkout.confirmation_subtitle', { number: order.number })"
            >
                <template #extra>
                    <Link v-if="authenticated" :href="route('account.orders.index')">
                        <Button type="primary" shape="round" size="large">{{ t('checkout.view_orders') }}</Button>
                    </Link>
                    <Link :href="route('catalog.index')">
                        <Button shape="round" size="large">{{ t('cart.continue_shopping') }}</Button>
                    </Link>
                </template>

                <Card size="small" class="text-start">
                    <ul class="store-totals">
                        <li v-for="(item, index) in order.items" :key="index" class="store-totals-row">
                            <span>{{ item.name }} × {{ item.quantity }}</span>
                            <span class="store-money font-medium">{{ item.line_total_formatted }}</span>
                        </li>
                        <li class="store-totals-row store-totals-grand">
                            <span>{{ t('cart.total') }}</span>
                            <span>{{ order.total_formatted }}</span>
                        </li>
                    </ul>
                    <Text class="!mt-3 !block !text-sm opacity-60">{{ t('checkout.payment') }}: {{ order.payment_method }}</Text>
                </Card>

                <Alert
                    v-if="paymentInstructions"
                    type="warning"
                    show-icon
                    class="!mt-4 text-start"
                    :message="t('checkout.payment_instructions')"
                    :description="paymentInstructions"
                />
            </Result>
        </div>
    </div>
</template>
