<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, MenuItem, Typography } from 'ant-design-vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { Title, Text } = Typography;

const { t } = useI18n();
const page = usePage();

/**
 * Read the current section from Inertia's `page.url` rather than
 * `window.location`, which the old version sampled once at module scope and so
 * never updated as the customer moved between the three tabs.
 */
const current = computed(() => page.url.split('?')[0]);

const items = computed(() => [
    { key: '/settings/profile', label: t('settings.profile') },
    { key: '/settings/password', label: t('settings.password') },
    { key: '/settings/appearance', label: t('settings.appearance') },
]);
</script>

<template>
    <div class="mx-auto w-full max-w-7xl px-4 py-8">
        <div class="mb-6">
            <Title :level="1" class="!mb-0 !text-2xl !font-bold">{{ t('settings.title') }}</Title>
            <Text class="!text-sm opacity-60">{{ t('settings.description') }}</Text>
        </div>

        <div class="flex flex-col gap-8 lg:flex-row">
            <aside class="shrink-0 lg:w-56">
                <Menu :selected-keys="[current]" mode="inline" class="store-settings-nav">
                    <MenuItem v-for="item in items" :key="item.key">
                        <Link :href="item.key">{{ item.label }}</Link>
                    </MenuItem>
                </Menu>
            </aside>

            <div class="min-w-0 flex-1 space-y-6 lg:max-w-2xl">
                <slot />
            </div>
        </div>
    </div>
</template>
