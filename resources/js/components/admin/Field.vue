<script setup lang="ts">
import { Form } from 'ant-design-vue';
import HelpHint from './HelpHint.vue';

const FormItem = Form.Item;

defineProps<{
    label: string;
    error?: string;
    required?: boolean;
    /** Always-visible text under the field. */
    hint?: string;
    /** Explanation behind a hint icon on the label, for fields whose name does
     *  not give away what the value actually does. */
    help?: string;
}>();
</script>

<template>
    <!-- Validation is server-side (Inertia hands back `errors`), so this stays a
         presentational FormItem: status and message come from the prop, not
         from an AntD validation rule. -->
    <FormItem
        :required="required"
        :validate-status="error ? 'error' : undefined"
        :help="error ?? hint"
        :label-col="{ span: 24 }"
        :wrapper-col="{ span: 24 }"
        class="!mb-4"
    >
        <template #label>
            {{ label }}
            <HelpHint v-if="help" :text="help" class="ms-1" />
        </template>

        <slot />
    </FormItem>
</template>
