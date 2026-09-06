<script setup lang="ts">
import { useStoreTheme } from '@/composables/useStoreTheme';
import { type AppLocale } from '@/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { ConfigProvider, Typography } from 'ant-design-vue';
import arEG from 'ant-design-vue/es/locale/ar_EG';
import enGB from 'ant-design-vue/es/locale/en_GB';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const { Title, Text } = Typography;

const page = usePage();
const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');
const locale = computed(() => (page.props.locale as AppLocale) ?? 'en');
const antLocale = computed(() => (locale.value === 'ar' ? arEG : enGB));

/**
 * The auth screens sit outside `StoreLayout`, so they carry their own
 * `ConfigProvider` — without it every AntD control here would fall back to the
 * default blue and LTR while the rest of the shop is green and possibly RTL.
 * `.store-shell` is on the root for the same reason: it is what the storefront
 * CSS keys off.
 */
const antTheme = useStoreTheme();
</script>

<template>
    <ConfigProvider :theme="antTheme" :locale="antLocale" :direction="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="store-shell flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="w-full max-w-sm">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col items-center gap-4">
                        <Link :href="route('home')" class="store-wordmark store-wordmark-lg flex items-baseline gap-1">
                            <span class="store-wordmark-glyph">◆</span>
                            <span>{{ appName }}</span>
                        </Link>
                        <div class="space-y-1 text-center">
                            <Title :level="1" class="!mb-0 !text-xl !font-semibold">{{ title }}</Title>
                            <Text class="!text-sm opacity-60">{{ description }}</Text>
                        </div>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </ConfigProvider>
</template>
