<script setup lang="ts">
import { Card, Statistic } from 'ant-design-vue';
import { computed } from 'vue';
import HelpHint from './HelpHint.vue';

const props = defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    /** How the figure is derived, for the ones that are not self-explanatory. */
    help?: string;
    tone?: 'default' | 'success' | 'warning' | 'danger';
}>();

/** AntD's Statistic takes a colour rather than a class, so map the tone to one. */
const toneColors: Record<string, string | undefined> = {
    default: undefined,
    success: '#059669',
    warning: '#d97706',
    danger: '#dc2626',
};

const valueStyle = computed(() => ({
    fontSize: '1.5rem',
    fontWeight: '600',
    color: toneColors[props.tone ?? 'default'],
}));
</script>

<template>
    <Card size="small" class="h-full" :body-style="{ padding: '16px' }">
        <Statistic :value="value" :value-style="valueStyle">
            <template #title>
                {{ label }}
                <HelpHint v-if="help" :text="help" class="ms-1" />
            </template>
        </Statistic>
        <p v-if="hint" class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ hint }}</p>
    </Card>
</template>
