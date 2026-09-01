<script setup lang="ts">
/**
 * Ant Design select behind the original v-model contract.
 *
 * Values round-trip loosely on purpose: Inertia sends ids as numbers while the
 * options list may carry strings, and callers expect to get back whatever type
 * they put in. '' remains the "nothing selected" value.
 */
import { Select } from 'ant-design-vue';
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

/** AntD keys options by value; '' is a legal value so it needs no sentinel. */
const entries = computed(() => [
    ...(props.emptyLabel !== undefined ? [{ value: '', label: props.emptyLabel }] : []),
    ...props.options.map((option) => ({ value: String(option.value ?? ''), label: option.label })),
]);

/** Hoisted: an inline object type literal in a template attribute won't parse. */
function filterOption(input: string, option: { label: string }): boolean {
    return option.label.toLowerCase().includes(input.toLowerCase());
}

const inner = computed({
    get: () => (props.modelValue === null ? '' : String(props.modelValue)),
    set: (value: string) => {
        if (value === '' || value === undefined) {
            emit('update:modelValue', '');

            return;
        }

        // Give back the option's original type so callers keep their numbers.
        const original = props.options.find((option) => String(option.value) === value);
        emit('update:modelValue', original ? original.value : value);
    },
});
</script>

<template>
    <Select
        v-model:value="inner"
        :options="entries"
        :placeholder="placeholder"
        :disabled="disabled"
        class="w-full"
        show-search
        :filter-option="filterOption"
    />
</template>
