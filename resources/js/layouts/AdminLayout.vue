<script setup lang="ts">
import AdminNav from '@/components/admin/AdminNav.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { useAdminMoneyBidi } from '@/composables/useAdminMoneyBidi';
import { useAdminTheme } from '@/composables/useAdminTheme';
import { useAppearance } from '@/composables/useAppearance';
import { switchLocale, type AppLocale } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Avatar, Button, ConfigProvider, Drawer, Dropdown, Layout, Menu, MenuItem, Tooltip, Typography } from 'ant-design-vue';
import arEG from 'ant-design-vue/es/locale/ar_EG';
import enGB from 'ant-design-vue/es/locale/en_GB';
import dayjs from 'dayjs';
import 'dayjs/locale/ar';
import 'dayjs/locale/en-gb';
import { Home, KeyRound, Languages, LogOut, Menu as MenuIcon, Moon, ScanLine, Store, Sun, UserCog } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const { Header, Sider, Content } = Layout;
const { Text } = Typography;

interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
}

const page = usePage();

const user = computed(() => (page.props.auth as { user: AuthUser | null }).user);

/** Shared from HandleInertiaRequests, so the sidebar follows the configured store name. */
const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');

/** The register moved from the nav to the top bar (as in the reference); same permission. */
const canUsePos = computed(() => (user.value?.roles.includes('super_admin') || user.value?.permissions.includes('pos.use')) ?? false);

const sidebarOpen = ref(false);

const { appearance, updateAppearance } = useAppearance();

function toggleDark(): void {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}

const locale = computed(() => (page.props.locale as AppLocale) ?? 'en');

/** Label names the language you switch *to*, so it reads correctly in either direction. */
const otherLocale = computed<AppLocale>(() => (locale.value === 'ar' ? 'en' : 'ar'));
const otherLocaleLabel = computed(() => (locale.value === 'ar' ? 'English' : 'العربية'));

function logout(): void {
    router.post(route('logout'));
}

const antTheme = useAdminTheme();
useAdminMoneyBidi();

/** AntD's own strings (calendar, table, pagination) plus the dayjs calendar itself. */
const antLocale = computed(() => (locale.value === 'ar' ? arEG : enGB));
watchEffect(() => dayjs.locale(locale.value === 'ar' ? 'ar' : 'en-gb'));
</script>

<template>
    <ConfigProvider :theme="antTheme" :locale="antLocale" :direction="locale === 'ar' ? 'rtl' : 'ltr'">
        <Layout class="admin-shell min-h-screen">
            <FlashMessages />

            <!-- Desktop sidebar. AntD Sider handles its own fixed positioning;
                 below lg it's swapped for a Drawer rather than shrunk. -->
            <Sider
                :width="256"
                theme="light"
                breakpoint="lg"
                collapsed-width="0"
                :trigger="null"
                class="admin-sider !fixed inset-y-0 start-0 z-50 hidden overflow-hidden lg:!block"
            >
                <Link :href="route('admin.dashboard')" class="admin-brand">
                    <Store class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ appName }}</span>
                </Link>

                <div class="overlay-scroll min-h-0 flex-1 overflow-y-auto">
                    <AdminNav />
                </div>
            </Sider>

            <!-- Mobile navigation -->
            <Drawer
                :open="sidebarOpen"
                :placement="locale === 'ar' ? 'right' : 'left'"
                :width="256"
                :body-style="{ padding: 0 }"
                :title="appName"
                root-class-name="admin-drawer"
                @close="sidebarOpen = false"
            >
                <AdminNav @navigate="sidebarOpen = false" />
            </Drawer>

            <Layout class="lg:ms-64">
                <Header class="admin-header sticky top-0 z-30 flex flex-wrap items-center gap-x-[18px] gap-y-2">
                    <Button type="text" class="admin-top-link lg:!hidden" :aria-label="t('admin.nav.open_menu')" @click="sidebarOpen = true">
                        <template #icon><MenuIcon class="h-5 w-5" /></template>
                    </Button>

                    <Link v-if="canUsePos" :href="route('admin.pos.register')">
                        <Button type="primary" class="admin-pos-btn" :aria-label="t('admin.nav.pos')">
                            <template #icon><ScanLine class="h-[18px] w-[18px]" /></template>
                            <span class="!hidden sm:!inline">{{ t('admin.nav.pos') }}</span>
                        </Button>
                    </Link>

                    <slot name="header" />

                    <div class="flex-1" />

                    <Link :href="route('home')" class="hidden sm:block">
                        <Button type="text" class="admin-top-link">
                            <template #icon><Home class="h-[18px] w-[18px]" /></template>
                            {{ t('admin.nav.storefront') }}
                        </Button>
                    </Link>

                    <Button
                        type="text"
                        class="admin-top-link"
                        :aria-label="`Switch language to ${otherLocaleLabel}`"
                        @click="switchLocale(otherLocale)"
                    >
                        <template #icon><Languages class="h-[18px] w-[18px]" /></template>
                        <span class="!hidden sm:!inline">{{ otherLocaleLabel }}</span>
                    </Button>

                    <Tooltip :title="t('admin.nav.toggle_dark')">
                        <Button type="text" class="admin-top-link" :aria-label="t('admin.nav.toggle_dark')" @click="toggleDark">
                            <template #icon>
                                <Moon v-if="appearance !== 'dark'" class="h-[18px] w-[18px]" />
                                <Sun v-else class="h-[18px] w-[18px]" />
                            </template>
                        </Button>
                    </Tooltip>

                    <Dropdown trigger="click" placement="bottomRight">
                        <Button type="text" class="admin-top-link">
                            <Avatar :size="30" class="admin-avatar uppercase">
                                {{ user?.name?.charAt(0) ?? '?' }}
                            </Avatar>
                            <span class="!hidden max-w-32 truncate sm:!block">{{ user?.name }}</span>
                        </Button>
                        <template #overlay>
                            <Menu>
                                <MenuItem key="email" disabled>
                                    <Text type="secondary" class="!text-xs">{{ user?.email }}</Text>
                                </MenuItem>
                                <MenuItem key="account">
                                    <template #icon><UserCog class="h-4 w-4" /></template>
                                    <Link :href="route('profile.edit')" class="block">{{ t('admin.nav.account_settings') }}</Link>
                                </MenuItem>
                                <MenuItem key="password">
                                    <template #icon><KeyRound class="h-4 w-4" /></template>
                                    <Link :href="route('password.edit')" class="block">{{ t('admin.nav.change_password') }}</Link>
                                </MenuItem>
                                <MenuItem key="logout" @click="logout">
                                    <template #icon><LogOut class="h-4 w-4" /></template>
                                    {{ t('admin.nav.log_out') }}
                                </MenuItem>
                            </Menu>
                        </template>
                    </Dropdown>
                </Header>

                <Content class="admin-content">
                    <slot />
                </Content>
            </Layout>
        </Layout>
    </ConfigProvider>
</template>
