<script setup lang="ts">
import CitySelect from '@/components/store/CitySelect.vue';
import EmptyState from '@/components/store/EmptyState.vue';
import type { AddressData } from '@/components/store/types';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button, Card, Checkbox, Form, FormItem, Input, Modal, Popconfirm, Tag, Tooltip, Typography } from 'ant-design-vue';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

defineProps<{ addresses: AddressData[]; cities: { en: string; ar: string }[] }>();

const { Title, Text } = Typography;

const { t } = useI18n();

const dialogOpen = ref(false);
const editing = ref<AddressData | null>(null);

const form = useForm({
    label: '',
    name: '',
    phone: '',
    country: 'LY',
    city: '',
    area: '',
    notes: '',
    is_default_shipping: false,
    is_default_billing: false,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(address: AddressData) {
    editing.value = address;
    form.label = address.label ?? '';
    form.name = address.name;
    form.phone = address.phone;
    form.country = address.country;
    form.city = address.city;
    form.area = address.area ?? '';
    form.notes = address.notes ?? '';
    form.is_default_shipping = address.is_default_shipping;
    form.is_default_billing = address.is_default_billing;
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.patch(route('account.addresses.update', editing.value.id), options);
    } else {
        form.post(route('account.addresses.store'), options);
    }
}

function remove(address: AddressData) {
    router.delete(route('account.addresses.destroy', address.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('account.addresses_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mb-6 flex items-center justify-between gap-3">
            <Title :level="1" class="!mb-0 !text-2xl !font-bold">{{ t('account.addresses_title') }}</Title>
            <Button type="primary" shape="round" @click="openCreate">
                <template #icon><Plus class="h-4 w-4" /></template>
                {{ t('account.add_address') }}
            </Button>
        </div>

        <EmptyState v-if="addresses.length === 0" :message="t('account.no_addresses')" />

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="address in addresses" :key="address.id" class="h-full" :body-style="{ padding: '1rem' }">
                <div class="flex h-full flex-col">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <MapPin class="h-4 w-4 text-[#0F9D58]" />
                            <h2 class="font-semibold">{{ address.label ?? address.city }}</h2>
                        </div>
                        <div class="-me-1 flex gap-1">
                            <Tooltip :title="t('common.edit')">
                                <Button type="text" shape="circle" size="small" :aria-label="t('common.edit')" @click="openEdit(address)">
                                    <template #icon><Pencil class="h-4 w-4" /></template>
                                </Button>
                            </Tooltip>
                            <Popconfirm
                                :title="`${t('account.delete_address')}?`"
                                :ok-text="t('common.delete')"
                                :cancel-text="t('common.cancel')"
                                ok-type="danger"
                                @confirm="remove(address)"
                            >
                                <Tooltip :title="t('common.delete')">
                                    <Button type="text" danger shape="circle" size="small" :aria-label="t('common.delete')">
                                        <template #icon><Trash2 class="h-4 w-4" /></template>
                                    </Button>
                                </Tooltip>
                            </Popconfirm>
                        </div>
                    </div>

                    <Text class="!mt-2 !block !text-sm opacity-65" dir="auto">
                        {{ address.name }} · <span dir="ltr">{{ address.phone }}</span
                        ><br />
                        {{ [address.area, address.city].filter(Boolean).join(', ') }}
                    </Text>

                    <div class="mt-auto flex flex-wrap gap-1.5 pt-3">
                        <Tag v-if="address.is_default_shipping" color="processing" class="!m-0">{{ t('account.default_shipping') }}</Tag>
                        <Tag v-if="address.is_default_billing" color="geekblue" class="!m-0">{{ t('account.default_billing') }}</Tag>
                    </div>
                </div>
            </Card>
        </div>

        <Modal
            :open="dialogOpen"
            :title="editing ? t('account.edit_address') : t('account.add_address')"
            :footer="null"
            :width="560"
            @cancel="dialogOpen = false"
        >
            <Form layout="vertical" class="grid gap-x-4 sm:grid-cols-2" @submit.prevent="submit">
                <FormItem class="sm:col-span-2" :label="t('account.label')">
                    <Input v-model:value="form.label" placeholder="Home / Office" />
                </FormItem>

                <FormItem :label="t('checkout.name')" :validate-status="form.errors.name ? 'error' : undefined" :help="form.errors.name">
                    <Input v-model:value="form.name" />
                </FormItem>

                <FormItem :label="t('checkout.phone')" :validate-status="form.errors.phone ? 'error' : undefined" :help="form.errors.phone">
                    <Input v-model:value="form.phone" type="tel" dir="ltr" />
                </FormItem>

                <FormItem :label="t('checkout.city')" :validate-status="form.errors.city ? 'error' : undefined" :help="form.errors.city">
                    <CitySelect v-model="form.city" :cities="cities" :has-error="form.errors.city !== undefined" />
                </FormItem>

                <FormItem :label="t('checkout.area')">
                    <Input v-model:value="form.area" />
                </FormItem>

                <FormItem class="sm:col-span-2" :label="t('checkout.notes')">
                    <Input v-model:value="form.notes" />
                </FormItem>

                <FormItem class="!mb-2">
                    <Checkbox v-model:checked="form.is_default_shipping">{{ t('account.default_shipping') }}</Checkbox>
                </FormItem>
                <FormItem class="!mb-2">
                    <Checkbox v-model:checked="form.is_default_billing">{{ t('account.default_billing') }}</Checkbox>
                </FormItem>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <Button @click="dialogOpen = false">{{ t('common.cancel') }}</Button>
                    <Button type="primary" html-type="submit" :loading="form.processing">{{ t('common.save') }}</Button>
                </div>
            </Form>
        </Modal>
    </div>
</template>
