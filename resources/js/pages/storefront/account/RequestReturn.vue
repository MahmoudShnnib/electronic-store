<script setup lang="ts">
import StoreLayout from '@/layouts/StoreLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Alert, Button, Card, Checkbox, Form, FormItem, InputNumber, Select, Textarea, Typography, Upload } from 'ant-design-vue';
import { ArrowLeft, Paperclip } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    reasons: { value: string; label: string }[];
}>();

const { Title, Text } = Typography;

const { t } = useI18n();

const form = useForm({
    items: [] as { order_item_id: number; quantity: number }[],
    reason: props.reasons[0]?.value ?? 'other',
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

const MAX_PHOTOS = 5;
const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const photoError = ref<string | null>(null);

/**
 * `accept` is only a hint — the file dialog's "All files" option walks right
 * past it. Rejecting here (instead of silently truncating) is what turns a dead
 * form into an explained one. `Upload` is told never to upload: the files ride
 * along with the Inertia form post, so `beforeUpload` always returns false.
 */
function onFiles(file: File, fileList: File[]): boolean {
    const accepted = fileList.filter((candidate) => ALLOWED_PHOTO_TYPES.includes(candidate.type));

    photoError.value =
        accepted.length < fileList.length
            ? t('account.return_photos_type')
            : fileList.length > MAX_PHOTOS
              ? t('account.return_photos_max', { max: MAX_PHOTOS })
              : null;

    form.images = accepted.slice(0, MAX_PHOTOS);

    return false;
}

/** Laravel reports per-file failures as `images.0`, `images.1`, … */
const imageError = computed(() => photoError.value ?? Object.entries(form.errors).find(([key]) => key.startsWith('images'))?.[1]);

function submit() {
    form.post(route('account.returns.store', props.order.id));
}
</script>

<template>
    <Head :title="t('account.request_return')" />

    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex items-center gap-3">
                <Link :href="route('account.orders.show', order.id)">
                    <Button shape="circle" :aria-label="t('common.back')">
                        <template #icon><ArrowLeft class="h-4 w-4 rtl:rotate-180" /></template>
                    </Button>
                </Link>
                <Title :level="1" class="!mb-0 !text-xl !font-bold">{{ t('account.request_return') }} — {{ order.number }}</Title>
            </div>

            <Form layout="vertical" class="space-y-6" @submit.prevent="submit">
                <!-- Items -->
                <Card :title="t('account.items')">
                    <Alert v-if="form.errors.items" type="error" show-icon class="!mb-3" :message="form.errors.items" />

                    <div class="space-y-2">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="store-return-row"
                            :class="{
                                'store-return-row-on': isSelected(item.id),
                                'opacity-50': item.returnable === 0,
                            }"
                        >
                            <Checkbox :checked="isSelected(item.id)" :disabled="item.returnable === 0" @change="toggleItem(item)" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ item.name }}</p>
                                <Text class="!text-xs opacity-55">{{ t('account.returnable', { count: item.returnable }) }}</Text>
                            </div>
                            <InputNumber
                                v-if="isSelected(item.id)"
                                :value="form.items.find((entry) => entry.order_item_id === item.id)?.quantity ?? 1"
                                :min="1"
                                :max="item.returnable"
                                class="w-20"
                                @change="(value) => setQuantity(item.id, Number(value), item.returnable)"
                            />
                        </div>
                    </div>
                </Card>

                <!-- Reason -->
                <Card :title="t('account.return_reason')">
                    <FormItem :validate-status="form.errors.reason ? 'error' : undefined" :help="form.errors.reason">
                        <Select v-model:value="form.reason" :options="reasons" class="w-full" />
                    </FormItem>

                    <FormItem :label="t('account.return_details')">
                        <Textarea v-model:value="form.customer_note" :rows="3" />
                    </FormItem>

                    <FormItem :label="t('account.return_photos')" :validate-status="imageError ? 'error' : undefined" :help="imageError" class="!mb-0">
                        <Upload
                            accept="image/jpeg,image/png,image/webp"
                            list-type="picture"
                            multiple
                            :max-count="MAX_PHOTOS"
                            :before-upload="onFiles"
                        >
                            <Button>
                                <template #icon><Paperclip class="h-4 w-4" /></template>
                                {{ t('account.return_photos') }}
                            </Button>
                        </Upload>
                    </FormItem>
                </Card>

                <Button
                    type="primary"
                    size="large"
                    shape="round"
                    block
                    html-type="submit"
                    :loading="form.processing"
                    :disabled="form.items.length === 0"
                >
                    {{ t('account.submit_return') }}
                </Button>
            </Form>
        </div>
    </div>
</template>
