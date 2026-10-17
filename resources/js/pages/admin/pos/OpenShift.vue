<script setup lang="ts">
import Field from '@/components/admin/Field.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button, Card, InputNumber, Radio, Textarea, Typography } from 'ant-design-vue';
import { Warehouse as WarehouseIcon } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: PosLayout });

const RadioGroup = Radio.Group;
const { Title, Text } = Typography;
const { t } = useI18n();

interface WarehouseOption {
    id: number;
    name: string;
    code: string;
    is_default: boolean;
    /** What the last drawer here was counted at when it closed; null if none closed yet. */
    last_counted: number | null;
    last_counted_formatted: string | null;
}

const props = defineProps<{ warehouses: WarehouseOption[] }>();

const initialWarehouse = props.warehouses.find((w) => w.is_default) ?? props.warehouses[0] ?? null;

const form = useForm({
    warehouse_id: initialWarehouse?.id ?? null,
    // The cash left in the drawer at the last close; change it only if some was taken out.
    opening_float: initialWarehouse?.last_counted ?? 0,
    note: '',
});

const selectedWarehouse = computed(() => props.warehouses.find((w) => w.id === form.warehouse_id) ?? null);

watch(selectedWarehouse, (warehouse) => {
    form.opening_float = warehouse?.last_counted ?? 0;
});

function submit(): void {
    form.post(route('admin.pos.shifts.store'));
}
</script>

<template>
    <Head :title="t('admin.pos.open_shift')" />

    <div class="flex h-full items-center justify-center overflow-y-auto p-4">
        <Card class="w-full max-w-lg">
            <div class="mb-6 text-center">
                <WarehouseIcon class="mx-auto mb-3 size-8 text-ad-accent" />
                <Title :level="4" class="!mb-1">{{ t('admin.pos.open_shift') }}</Title>
                <Text type="secondary" class="!text-sm">{{ t('admin.pos.open_shift_hint') }}</Text>
            </div>

            <form @submit.prevent="submit">
                <Field :label="t('admin.pos.sell_from')" :error="form.errors.warehouse_id" required>
                    <!-- Radios, not a select: the branch decides which shelf the
                         stock leaves, so it should never be a one-line default a
                         cashier can skip past without reading. -->
                    <RadioGroup v-model:value="form.warehouse_id" class="!flex !flex-col !gap-2">
                        <Radio v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                            {{ warehouse.name }}
                            <Text type="secondary" class="!text-xs">({{ warehouse.code }})</Text>
                        </Radio>
                    </RadioGroup>
                </Field>

                <Field :label="t('admin.pos.opening_float')" :error="form.errors.opening_float" :help="t('admin.pos.opening_float_help')" required>
                    <InputNumber v-model:value="form.opening_float" :min="0" :step="1" :precision="2" class="!w-full" />
                    <Text v-if="selectedWarehouse?.last_counted_formatted" type="secondary" class="mt-1 block !text-xs">
                        {{ t('admin.pos.last_counted_hint', { amount: selectedWarehouse.last_counted_formatted }) }}
                    </Text>
                </Field>

                <Field :label="t('admin.common.notes')" :error="form.errors.note">
                    <Textarea v-model:value="form.note" :rows="2" :maxlength="500" />
                </Field>

                <Button type="primary" html-type="submit" size="large" block :loading="form.processing">
                    {{ t('admin.pos.start_selling') }}
                </Button>
            </form>
        </Card>
    </div>
</template>
