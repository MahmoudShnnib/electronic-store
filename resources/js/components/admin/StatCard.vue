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
    /** Small muted text right after the figure ("14 active"), as in the reference. */
    suffix?: string;
}>();

/**
 * Money arrives pre-formatted by App\Support\Money ("⁦2,309.000 LYD⁩"). The
 * reference sets the currency small and muted after a large amount, so the
 * string is split for display only — the figure itself is never re-formatted.
 * The amount keeps its own LTR isolate; under RTL the currency then sits on its
 * left, which is how the reference renders it in Arabic.
 */
const money = computed(() => {
    const match = String(props.value)
        .replace(/[\u2066-\u2069]/g, '')
        .match(/^([-\u2212]?[\d.,]+)\s+(\D+)$/);

    return match ? { amount: match[1], currency: match[2] } : null;
});

/** Zero reads as "nothing here" in the reference, so it steps back to the faint tone. */
const isZero = computed(() => {
    // The amount only: "د.ل" carries a dot of its own that would spoil the number.
    const digits = (money.value?.amount ?? String(props.value)).replace(/[^\d.-]/g, '');

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
        <div class="admin-kpi-body" :class="{ 'has-icon': icon }">
            <span v-if="icon" class="admin-kpi-icon" :class="{ 'is-money': tone === 'success' }" aria-hidden="true">
                <component :is="icon" />
            </span>
            <h2 class="admin-kpi-label">
                {{ label }}
                <HelpHint v-if="help" :text="help" />
            </h2>
            <div class="admin-kpi-value">
                <template v-if="money">
                    <bdi dir="ltr" :class="toneClass">{{ money.amount }}</bdi>
                    <span class="admin-kpi-cur">{{ money.currency }}</span>
                </template>
                <span v-else :class="toneClass">{{ value }}</span>
                <span v-if="suffix" class="admin-kpi-cur">{{ suffix }}</span>
            </div>
            <p v-if="hint" class="admin-kpi-hint">{{ hint }}</p>
        </div>
    </section>
</template>
