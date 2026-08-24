<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    status: string;
    label?: string;
}>();

const palette: Record<string, string> = {
    // greens
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    delivered: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    received: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    confirmed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    resellable: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    // blues
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    shipped: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    in_transit: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    item_received: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    ready_for_shipping: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    // ambers
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    requested: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    under_review: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    partially_received: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    partially_paid: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    unpaid: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    out_of_stock: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    inactive: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    discontinued: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    // reds
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    damaged: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    returned: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    partially_refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
};

const classes = computed(() => palette[props.status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300');

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
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="classes">
        {{ text }}
    </span>
</template>
