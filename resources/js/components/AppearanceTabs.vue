<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Radio, RadioGroup } from 'ant-design-vue';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const tabs = computed(() => [
    { value: 'light', icon: Sun, label: t('settings.theme_light') },
    { value: 'dark', icon: Moon, label: t('settings.theme_dark') },
    { value: 'system', icon: Monitor, label: t('settings.theme_system') },
]);
</script>

<template>
    <RadioGroup
        :value="appearance"
        button-style="solid"
        class="store-variants"
        @update:value="(value) => updateAppearance(value as 'light' | 'dark' | 'system')"
    >
        <Radio.Button v-for="tab in tabs" :key="tab.value" :value="tab.value">
            <span class="flex items-center gap-1.5">
                <component :is="tab.icon" class="h-4 w-4" />
                {{ tab.label }}
            </span>
        </Radio.Button>
    </RadioGroup>
</template>
