<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import StoreLayout from '@/layouts/StoreLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, Typography } from 'ant-design-vue';
import { useI18n } from 'vue-i18n';

defineOptions({ layout: StoreLayout });

const { Title, Text } = Typography;

const { t } = useI18n();
</script>

<template>
    <Head :title="t('settings.appearance_title')" />

    <SettingsLayout>
        <Card>
            <Title :level="2" class="!mb-1 !text-base !font-semibold">{{ t('settings.appearance_title') }}</Title>
            <Text class="!mb-5 !block !text-sm opacity-60">{{ t('settings.appearance_description') }}</Text>

            <AppearanceTabs />
        </Card>
    </SettingsLayout>
</template>
