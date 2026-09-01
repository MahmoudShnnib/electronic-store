<script setup lang="ts">
import { Tag } from 'ant-design-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    status: string;
    label?: string;
}>();

/** AntD preset tag colours, keyed by status slug. Unknown statuses stay neutral. */
const palette: Record<string, string> = {
    // greens
    active: 'success',
    completed: 'success',
    delivered: 'success',
    paid: 'success',
    approved: 'success',
    received: 'success',
    confirmed: 'success',
    resellable: 'success',
    // blues
    processing: 'processing',
    shipped: 'processing',
    sent: 'processing',
    in_transit: 'processing',
    item_received: 'processing',
    ready_for_shipping: 'geekblue',
    // ambers
    pending: 'warning',
    requested: 'warning',
    under_review: 'warning',
    partially_received: 'warning',
    partially_paid: 'warning',
    unpaid: 'warning',
    out_of_stock: 'warning',
    // neutrals
    draft: 'default',
    inactive: 'default',
    closed: 'default',
    discontinued: 'default',
    // reds
    deleted: 'error',
    cancelled: 'error',
    rejected: 'error',
    failed: 'error',
    damaged: 'error',
    // purples
    returned: 'purple',
    refunded: 'purple',
    partially_refunded: 'purple',
};

const color = computed(() => palette[props.status] ?? 'default');

const { t, te } = useI18n();

/**
 * Most call sites pass the server's translated `*_label`. The ones that don't
 * (is_active flags, review status, item condition) used to render the raw
 * English slug in the middle of an otherwise Arabic page — fall back to the
 * shared status keys before falling back to the slug.
 */
const text = computed(() => {
    if (props.label) {
        return props.label;
    }

    const key = `admin.common.${props.status}`;

    return te(key) ? t(key) : props.status.replaceAll('_', ' ');
});
</script>

<template>
    <Tag :color="color" class="!capitalize">{{ text }}</Tag>
</template>
