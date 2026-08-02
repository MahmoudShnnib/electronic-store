<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

interface ReturnableItem {
    id: number;
    name: string;
    quantity: number;
    returnable: number;
}

const props = defineProps<{
    order: { id: number; number: string; items: ReturnableItem[] };
    reasons: string[];
}>();

const { t } = useI18n();

const form = useForm({
    items: [] as { order_item_id: number; quantity: number }[],
    reason: props.reasons[0] ?? 'other',
    customer_note: '',
    images: [] as File[],
});

function isSelected(itemId: number): boolean {
    return form.items.some((item) => item.order_item_id === itemId);
}

function toggleItem(item: ReturnableItem) {
    const index = form.items.findIndex((entry) => entry.order_item_id === item.id);

    if (index >= 0) form.items.splice(index, 1);
    else form.items.push({ order_item_id: item.id, quantity: 1 });
}

function setQuantity(itemId: number, quantity: number, max: number) {
    const entry = form.items.find((item) => item.order_item_id === itemId);
    if (entry) entry.quantity = Math.min(max, Math.max(1, quantity));
}

function onFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    form.images = Array.from(input.files ?? []).slice(0, 5);
}

function submit() {
    form.post(route('account.returns.store', props.order.id));
}
</script>

<template>
    <Head :title="t('account.request_return')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex items-center gap-3">
                <Link
                    :href="route('account.orders.show', order.id)"
                    class="rounded-lg border border-border p-2 hover:bg-muted"
                    :aria-label="t('common.back')"
                >
                    <ArrowLeft class="h-4 w-4 rtl:rotate-180" />
                </Link>
                <h1 class="text-xl font-bold">{{ t('account.request_return') }} — {{ order.number }}</h1>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- Items -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 font-semibold">{{ t('account.items') }}</h2>
                    <p v-if="form.errors.items" class="mb-2 text-sm text-red-500">{{ form.errors.items }}</p>

                    <div class="space-y-2">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-center gap-3 rounded-lg border p-3"
                            :class="[
                                isSelected(item.id) ? 'border-black bg-neutral-100 dark:border-white dark:bg-neutral-800' : 'border-border',
                                item.returnable === 0 ? 'opacity-50' : '',
                            ]"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-input text-black dark:text-white"
                                :checked="isSelected(item.id)"
                                :disabled="item.returnable === 0"
                                @change="toggleItem(item)"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ item.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ t('account.returnable', { count: item.returnable }) }}</p>
                            </div>
                            <input
                                v-if="isSelected(item.id)"
                                type="number"
                                min="1"
                                :max="item.returnable"
                                :value="form.items.find((entry) => entry.order_item_id === item.id)?.quantity ?? 1"
                                class="w-16 rounded-lg border border-input bg-background px-2 py-1.5 text-center text-sm"
                                @change="setQuantity(item.id, Number(($event.target as HTMLInputElement).value), item.returnable)"
                            />
                        </div>
                    </div>
                </section>

                <!-- Reason -->
                <section class="rounded-xl border border-border bg-card p-5">
                    <h2 class="mb-3 font-semibold">{{ t('account.return_reason') }}</h2>
                    <select v-model="form.reason" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm">
                        <option v-for="reason in reasons" :key="reason" :value="reason">
                            {{ reason.replaceAll('_', ' ') }}
                        </option>
                    </select>
                    <p v-if="form.errors.reason" class="mt-1 text-xs text-red-500">{{ form.errors.reason }}</p>

                    <label class="mb-1 mt-4 block text-sm">{{ t('account.return_details') }}</label>
                    <textarea v-model="form.customer_note" rows="3" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm" />

                    <label class="mb-1 mt-4 block text-sm">{{ t('account.return_photos') }}</label>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="block w-full text-sm text-muted-foreground file:me-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-black dark:file:bg-neutral-800 dark:file:text-white"
                        @change="onFiles"
                    />
                </section>

                <button
                    type="submit"
                    :disabled="form.processing || form.items.length === 0"
                    class="w-full rounded-xl bg-black py-3 font-semibold text-white transition hover:opacity-85 disabled:opacity-50 dark:bg-white dark:text-black"
                >
                    {{ t('account.submit_return') }}
                </button>
            </form>
        </div>
    </div>
</template>
