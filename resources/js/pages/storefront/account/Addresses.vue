<script setup lang="ts">
import EmptyState from '@/components/store/EmptyState.vue';
import type { AddressData } from '@/components/store/types';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

defineProps<{ addresses: AddressData[]; cities: { en: string; ar: string }[] }>();

const { t } = useI18n();
const page = usePage();
const locale = computed(() => page.props.locale as string);

/** The English name is the stored value; the label follows the UI language. */
function cityLabel(city: { en: string; ar: string }): string {
    return locale.value === 'ar' ? city.ar : city.en;
}

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
    if (confirm(t('account.delete_address') + '?')) {
        router.delete(route('account.addresses.destroy', address.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('account.addresses_title')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ t('account.addresses_title') }}</h1>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg bg-black px-3 py-2 text-sm font-semibold text-white transition hover:opacity-85 dark:bg-white dark:text-black"
                @click="openCreate"
            >
                <Plus class="h-4 w-4" />
                {{ t('account.add_address') }}
            </button>
        </div>

        <EmptyState v-if="addresses.length === 0" :message="t('account.addresses_title')" />

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article v-for="address in addresses" :key="address.id" class="flex flex-col rounded-xl border border-border bg-card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <MapPin class="h-4 w-4 text-black dark:text-white" />
                        <h2 class="font-semibold">{{ address.label ?? address.city }}</h2>
                    </div>
                    <div class="flex gap-1">
                        <button
                            type="button"
                            class="rounded p-1.5 text-muted-foreground hover:bg-muted"
                            :aria-label="t('common.edit')"
                            @click="openEdit(address)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded p-1.5 text-muted-foreground hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                            :aria-label="t('common.delete')"
                            @click="remove(address)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <p class="mt-2 text-sm text-muted-foreground">
                    {{ address.name }} · <span dir="ltr">{{ address.phone }}</span
                    ><br />
                    {{ [address.area, address.city].filter(Boolean).join(', ') }}
                </p>

                <div class="mt-auto flex flex-wrap gap-1.5 pt-3">
                    <span
                        v-if="address.is_default_shipping"
                        class="rounded-full bg-neutral-200 px-2 py-0.5 text-[11px] font-medium text-black dark:bg-neutral-800 dark:text-white"
                    >
                        {{ t('account.default_shipping') }}
                    </span>
                    <span
                        v-if="address.is_default_billing"
                        class="rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                    >
                        {{ t('account.default_billing') }}
                    </span>
                </div>
            </article>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editing ? t('account.edit_address') : t('account.add_address') }}</DialogTitle>
                </DialogHeader>

                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm">{{ t('account.label') }}</label>
                        <input
                            v-model="form.label"
                            type="text"
                            placeholder="Home / Office"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">{{ t('checkout.name') }}</label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">{{ t('checkout.phone') }}</label>
                        <input v-model="form.phone" type="tel" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">{{ t('checkout.city') }}</label>
                        <select v-model="form.city" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                            <option value="" disabled>{{ t('checkout.select_city') }}</option>
                            <option v-for="city in cities" :key="city.en" :value="city.en">{{ cityLabel(city) }}</option>
                        </select>
                        <p v-if="form.errors.city" class="mt-1 text-xs text-red-500">{{ form.errors.city }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">{{ t('checkout.area') }}</label>
                        <input v-model="form.area" type="text" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm">{{ t('checkout.notes') }}</label>
                        <input v-model="form.notes" type="text" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_default_shipping" type="checkbox" class="h-4 w-4 rounded border-input text-black dark:text-white" />
                        {{ t('account.default_shipping') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_default_billing" type="checkbox" class="h-4 w-4 rounded border-input text-black dark:text-white" />
                        {{ t('account.default_billing') }}
                    </label>

                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button
                            type="button"
                            class="rounded-lg border border-border px-4 py-2 text-sm transition hover:bg-muted"
                            @click="dialogOpen = false"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-black px-4 py-2 text-sm font-semibold text-white transition hover:opacity-85 disabled:opacity-50 dark:bg-white dark:text-black"
                        >
                            {{ t('common.save') }}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
