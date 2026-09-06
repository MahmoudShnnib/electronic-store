<script setup lang="ts">
import { Typography } from 'ant-design-vue';

const { Title, Text } = Typography;

defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <Title :level="2" class="!mb-0 !text-2xl">{{ title }}</Title>
            <Text v-if="subtitle" type="secondary" class="!text-sm">{{ subtitle }}</Text>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
