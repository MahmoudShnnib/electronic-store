<script setup lang="ts">
import { Tag } from 'ant-design-vue';
import { computed } from 'vue';

const props = defineProps<{
    /** Raw status slug from the API — `shipped`, `under_review`, … */
    status: string;
    /** Server-rendered, already-translated label. */
    label: string;
}>();

/**
 * One slug → colour map for every status the storefront shows: order statuses,
 * payment statuses and return statuses share it, because a customer reading
 * "refunded" on an order and on a return should see the same chip both times.
 * Colours are AntD presets; the palette behind them is the shared one in
 * `app.css`, not AntD's own washed-out defaults.
 */
const palette: Record<string, string> = {
    // Waiting on someone
    pending: 'warning',
    requested: 'warning',
    under_review: 'processing',
    unpaid: 'warning',

    // Moving
    confirmed: 'processing',
    processing: 'processing',
    approved: 'processing',
    ready_for_shipping: 'geekblue',
    shipped: 'geekblue',
    item_received: 'geekblue',

    // Done well
    delivered: 'success',
    completed: 'success',
    paid: 'success',

    // Money went back
    refunded: 'purple',
    returned: 'purple',

    // Stopped
    cancelled: 'error',
    rejected: 'error',
    failed: 'error',
    closed: 'default',
};

const color = computed(() => palette[props.status] ?? 'default');
</script>

<template>
    <Tag :color="color" class="!m-0">{{ label }}</Tag>
</template>
