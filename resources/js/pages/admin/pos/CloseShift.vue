<script setup lang="ts">
import ConfirmDialog from '@/components/admin/ConfirmDialog.vue';
import Field from '@/components/admin/Field.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { formatMoney } from '@/lib/money';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Alert, Button, Card, Descriptions, InputNumber, Textarea, Typography } from 'ant-design-vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: PosLayout });

const DescriptionsItem = Descriptions.Item;
const { Title } = Typography;
const { t } = useI18n();
const page = usePage();

const props = defineProps<{
    shift: {
        id: number;
        cashier: string | null;
        warehouse: { id: number; name: string | null };
        opened_at: string;
        orders_count: number;
        opening_float_formatted: string;
        takings: { method: string; label: string; amount_formatted: string }[];
        cash_refunded_formatted: string;
        has_cash_refunds: boolean;
        expected_cash_cents: number;
        expected_cash_formatted: string;
    };
}>();

/**
 * Starts empty, not at 0: a 0 left in place by a stray Enter would close the
 * shift with the whole drawer recorded as missing, and a closed shift can't be
 * reopened.
 */
const form = useForm({ counted_cash: null as number | null, note: '' });

function money(amount: number): string {
    return formatMoney(amount, (page.props.locale as string) ?? 'en');
}

/**
 * Shown live so the cashier sees the gap before committing, not after. The
 * server recomputes the expected figure regardless — this is a preview.
 */
const difference = computed(() => (form.counted_cash ?? 0) - props.shift.expected_cash_cents / 100);

const counted = computed(() => form.counted_cash !== null && Number.isFinite(form.counted_cash));
const balanced = computed(() => Math.abs(difference.value) < 0.0005);

const differenceLabel = computed(() => {
    if (Math.abs(difference.value) < 0.0005) return t('admin.pos.balanced');

    return difference.value > 0
        ? t('admin.pos.over_by', { amount: money(difference.value) })
        : t('admin.pos.short_by', { amount: money(Math.abs(difference.value)) });
});

/** A drawer that doesn't balance posts the gap to the treasury, so it is confirmed first. */
const confirmingVariance = ref(false);

function submit(): void {
    if (!counted.value) return;

    if (!balanced.value) {
        confirmingVariance.value = true;

        return;
    }

    close();
}

function close(): void {
    confirmingVariance.value = false;
    form.post(route('admin.pos.shifts.closed'));
}
</script>

<template>
    <Head :title="t('admin.pos.close_shift')" />

    <div class="flex h-full items-center justify-center overflow-y-auto p-4">
        <Card class="w-full max-w-xl">
            <Title :level="4" class="!mb-4">{{ t('admin.pos.close_shift') }}</Title>

            <Descriptions :column="1" size="small" bordered class="mb-5">
                <DescriptionsItem :label="t('admin.pos.cashier')">{{ shift.cashier ?? '—' }}</DescriptionsItem>
                <DescriptionsItem :label="t('admin.pos.sell_from')">{{ shift.warehouse.name ?? '—' }}</DescriptionsItem>
                <DescriptionsItem :label="t('admin.pos.opened_at')">{{ shift.opened_at }}</DescriptionsItem>
                <DescriptionsItem :label="t('admin.pos.sales_count')">{{ shift.orders_count }}</DescriptionsItem>
                <DescriptionsItem :label="t('admin.pos.opening_float')">{{ shift.opening_float_formatted }}</DescriptionsItem>
                <DescriptionsItem v-for="taking in shift.takings" :key="taking.method" :label="taking.label">
                    {{ taking.amount_formatted }}
                </DescriptionsItem>
                <DescriptionsItem v-if="shift.has_cash_refunds" :label="t('admin.pos.cash_refunded')">
                    − {{ shift.cash_refunded_formatted }}
                </DescriptionsItem>
                <DescriptionsItem :label="t('admin.pos.expected_in_drawer')">
                    <span class="font-semibold">{{ shift.expected_cash_formatted }}</span>
                </DescriptionsItem>
            </Descriptions>

            <form @submit.prevent="submit">
                <Field :label="t('admin.pos.counted_cash')" :error="form.errors.counted_cash" :help="t('admin.pos.counted_cash_help')" required>
                    <InputNumber v-model:value="form.counted_cash" :min="0" :step="1" size="large" class="!w-full" autofocus />
                </Field>

                <Alert v-if="counted" class="!mb-4" :type="balanced ? 'success' : 'warning'" :message="differenceLabel" show-icon />

                <Field :label="t('admin.common.notes')" :error="form.errors.note">
                    <Textarea v-model:value="form.note" :rows="2" :maxlength="500" />
                </Field>

                <div class="flex gap-2">
                    <Button type="primary" html-type="submit" size="large" block :loading="form.processing" :disabled="!counted">
                        {{ t('admin.pos.confirm_close') }}
                    </Button>
                    <Link :href="route('admin.pos.register')">
                        <Button size="large">{{ t('admin.common.cancel') }}</Button>
                    </Link>
                </div>
            </form>
        </Card>
    </div>

    <ConfirmDialog
        :open="confirmingVariance"
        :title="t('admin.pos.confirm_variance_title')"
        :message="t('admin.pos.confirm_variance_message', { variance: differenceLabel })"
        :confirm-label="t('admin.pos.confirm_close')"
        danger
        :processing="form.processing"
        @confirm="close"
        @close="confirmingVariance = false"
    />
</template>
