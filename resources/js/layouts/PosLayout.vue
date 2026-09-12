<script setup lang="ts">
import FlashMessages from '@/components/FlashMessages.vue';
import { useAntdTheme } from '@/composables/useAntdTheme';
import { isDark, useAppearance } from '@/composables/useAppearance';
import { Link, usePage } from '@inertiajs/vue3';
import { Button, ConfigProvider, Tooltip, Typography } from 'ant-design-vue';
import arEG from 'ant-design-vue/es/locale/ar_EG';
import enGB from 'ant-design-vue/es/locale/en_GB';
import dayjs from 'dayjs';
import 'dayjs/locale/ar';
import 'dayjs/locale/en-gb';
import { LayoutDashboard, Moon, Sun } from 'lucide-vue-next';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The register runs full-screen with no sidebar on purpose. A cashier serving a
 * queue needs the whole viewport for the basket and the keypad, and every nav
 * link is a way to lose a half-rung-up sale — the only way out is the one
 * button in the top bar.
 */
const { t } = useI18n();
const { Text } = Typography;

const page = usePage();
const { appearance, updateAppearance } = useAppearance();

const themeConfig = useAntdTheme({ colorPrimary: '#2563eb', borderRadius: 6 });

const locale = computed(() => (page.props.locale as string) ?? 'en');
const direction = computed<'rtl' | 'ltr'>(() => (locale.value === 'ar' ? 'rtl' : 'ltr'));
const antdLocale = computed(() => (locale.value === 'ar' ? arEG : enGB));

watchEffect(() => dayjs.locale(locale.value === 'ar' ? 'ar' : 'en-gb'));

const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');

/** Mirrors AdminLayout: the stored preference decides, `isDark` only paints. */
function toggleDark(): void {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <ConfigProvider :theme="themeConfig" :locale="antdLocale" :direction="direction">
        <div class="flex h-screen flex-col overflow-hidden bg-gray-50 dark:bg-neutral-950">
            <header
                class="flex shrink-0 items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 py-2.5 dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span class="truncate text-[0.9375rem] font-semibold tracking-tight">{{ appName }}</span>
                    <Text type="secondary" class="!hidden !text-xs sm:!inline">{{ t('admin.pos.title') }}</Text>
                </div>

                <div class="flex items-center gap-1">
                    <Tooltip :title="t('admin.nav.toggle_dark')">
                        <Button type="text" :aria-label="t('admin.nav.toggle_dark')" @click="toggleDark">
                            <component :is="isDark ? Sun : Moon" class="size-4" />
                        </Button>
                    </Tooltip>

                    <Tooltip :title="t('admin.pos.exit')">
                        <Link :href="route('admin.dashboard')">
                            <Button type="text" :aria-label="t('admin.pos.exit')">
                                <LayoutDashboard class="size-4" />
                            </Button>
                        </Link>
                    </Tooltip>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-hidden">
                <slot />
            </main>

            <FlashMessages />
        </div>
    </ConfigProvider>
</template>
