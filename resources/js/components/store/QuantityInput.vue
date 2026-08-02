<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        modelValue: number;
        min?: number;
        max?: number;
    }>(),
    { min: 1, max: 10 },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: number): void }>();

function set(value: number) {
    emit('update:modelValue', Math.min(props.max, Math.max(props.min, value)));
}
</script>

<template>
    <div class="inline-flex items-center rounded-lg border border-border">
        <button
            type="button"
            class="px-3 py-2 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
            :disabled="modelValue <= min"
            aria-label="Decrease quantity"
            @click="set(modelValue - 1)"
        >
            <Minus class="h-4 w-4" />
        </button>
        <input
            :value="modelValue"
            type="number"
            :min="min"
            :max="max"
            class="w-12 border-x border-border bg-transparent py-2 text-center text-sm [appearance:textfield] focus:outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            @change="set(Number(($event.target as HTMLInputElement).value) || min)"
        />
        <button
            type="button"
            class="px-3 py-2 text-muted-foreground transition hover:text-foreground disabled:opacity-40"
            :disabled="modelValue >= max"
            aria-label="Increase quantity"
            @click="set(modelValue + 1)"
        >
            <Plus class="h-4 w-4" />
        </button>
    </div>
</template>
