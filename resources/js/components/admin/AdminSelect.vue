<script setup lang="ts">
/**
 * Replaces <select>. A native select's dropdown is painted by the OS, so it
 * can never be themed — it opens as a system menu (blue highlight, system
 * font, wrong direction in Arabic) on top of the dark dashboard. This renders
 * the list ourselves, so it matches the rest of the UI and follows the page
 * direction.
 *
 * Values are compared loosely on purpose: Inertia round-trips ids as numbers
 * while <option value> is always a string, and a strict compare would leave
 * saved rows showing the placeholder.
 */
import { Check, ChevronDown } from 'lucide-vue-next';
import { SelectContent, SelectItem, SelectItemIndicator, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectViewport } from 'radix-vue';
import { computed } from 'vue';

type OptionValue = string | number | null;

const props = withDefaults(
    defineProps<{
        modelValue: OptionValue;
        options: { value: OptionValue; label: string }[];
        placeholder?: string;
        disabled?: boolean;
        /** Shown as the first entry and selects the empty value. */
        emptyLabel?: string;
    }>(),
    { disabled: false },
);

const emit = defineEmits<{ (e: 'update:modelValue', value: OptionValue): void }>();

/** Radix works in strings; '' is the "nothing selected" sentinel. */
const EMPTY = '__empty__';

const entries = computed(() => [
    ...(props.emptyLabel !== undefined ? [{ value: EMPTY, label: props.emptyLabel }] : []),
    ...props.options.map((option) => ({ value: String(option.value ?? EMPTY), label: option.label })),
]);

const inner = computed({
    get: () => (props.modelValue === null || props.modelValue === '' ? EMPTY : String(props.modelValue)),
    set: (value: string) => {
        if (value === EMPTY) {
            emit('update:modelValue', '');

            return;
        }

        // Give back the option's original type so callers keep their numbers.
        const original = props.options.find((option) => String(option.value) === value);
        emit('update:modelValue', original ? original.value : value);
    },
});

const selectedLabel = computed(() => entries.value.find((entry) => entry.value === inner.value)?.label ?? '');
</script>

<template>
    <SelectRoot v-model="inner" :disabled="disabled">
        <SelectTrigger
            class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-start text-sm text-gray-900 shadow-sm transition-colors hover:border-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:shadow-none dark:hover:border-gray-600 dark:focus:ring-blue-500/40 dark:disabled:bg-gray-900/50"
        >
            <!-- Rendered directly rather than through SelectValue: a slot inside
                 SelectValue always wins over its placeholder, so an empty
                 selection would show a blank trigger instead of the hint. -->
            <span v-if="selectedLabel" class="truncate">{{ selectedLabel }}</span>
            <span v-else class="truncate text-gray-400 dark:text-gray-500">{{ placeholder ?? '' }}</span>
            <ChevronDown class="ms-auto h-4 w-4 shrink-0 text-gray-400" />
        </SelectTrigger>

        <SelectPortal>
            <SelectContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-72 min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
            >
                <SelectViewport class="p-1">
                    <SelectItem
                        v-for="entry in entries"
                        :key="entry.value"
                        :value="entry.value"
                        class="flex cursor-pointer select-none items-center justify-between gap-2 rounded px-2 py-1.5 text-sm text-gray-700 outline-none data-[highlighted]:bg-blue-50 data-[highlighted]:text-blue-700 dark:text-gray-200 dark:data-[highlighted]:bg-blue-950 dark:data-[highlighted]:text-blue-200"
                    >
                        <SelectItemText>{{ entry.label }}</SelectItemText>
                        <SelectItemIndicator>
                            <Check class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                        </SelectItemIndicator>
                    </SelectItem>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
