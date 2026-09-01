<script setup lang="ts">
import { Tooltip } from 'ant-design-vue';
import { CircleHelp } from 'lucide-vue-next';

defineProps<{
    /** One or two sentences. Anything longer belongs on the page, not in a tooltip. */
    text: string;
}>();
</script>

<template>
    <Tooltip :title="text">
        <!-- A real <button>, not a bare icon: AntD's tooltip opens on focus as
             well as hover, so the explanation is reachable from the keyboard and
             announced by screen readers instead of being hover-only trivia.
             It never submits or navigates — it exists to be focused. -->
        <button type="button" class="admin-hint" :aria-label="text" @click.prevent.stop>
            <CircleHelp class="h-3.5 w-3.5" aria-hidden="true" />
        </button>
    </Tooltip>
</template>
