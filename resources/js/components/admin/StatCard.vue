<script setup lang="ts">
import { computed, type Component } from 'vue';
import HelpHint from './HelpHint.vue';

const props = defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    /** How the figure is derived, for the ones that are not self-explanatory. */
    help?: string;
    tone?: 'default' | 'success' | 'warning' | 'danger';
    /** Optional lucide icon shown in the box at the start (reference KPI style). */
    icon?: Component;
}>();

/** Zero reads as "nothing here" in the reference, so it steps back to the faint tone. */
const isZero = computed(() => {
    const digits = String(props.value).replace(/[^\d.-]/g, '');

    return digits !== '' && Number(digits) === 0;
});

const toneClass = computed(() => {
    if (isZero.value) {
        return 'is-zero';
    }

    return { success: 'is-money', warning: 'is-warning', danger: 'is-danger' }[props.tone ?? 'default'] ?? '';
});
</script>

<template>
    <section class="admin-kpi">
        <span v-if="icon" class="admin-kpi-icon" :class="{ 'is-money': tone === 'success' }" aria-hidden="true">
            <component :is="icon" />
        </span>
        <div>
            <h2 class="admin-kpi-label">
                {{ label }}
                <HelpHint v-if="help" :text="help" />
            </h2>
            <div class="admin-kpi-value" :class="toneClass">{{ value }}</div>
            <p v-if="hint" class="admin-kpi-hint">{{ hint }}</p>
        </div>
    </section>
</template>
