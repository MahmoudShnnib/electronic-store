<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button, Empty } from 'ant-design-vue';

defineProps<{
    message: string;
    actionLabel?: string;
    actionHref?: string;
}>();
</script>

<template>
    <div class="store-empty">
        <Empty :image="Empty.PRESENTED_IMAGE_SIMPLE" :description="message">
            <Link v-if="actionLabel && actionHref" :href="actionHref">
                <Button type="primary" shape="round">{{ actionLabel }}</Button>
            </Link>
        </Empty>
    </div>
</template>
