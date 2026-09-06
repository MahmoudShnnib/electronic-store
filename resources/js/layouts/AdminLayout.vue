<script setup lang="ts">
import FlashMessages from '@/components/FlashMessages.vue';
import { useAntdTheme } from '@/composables/useAntdTheme';
import { useAppearance } from '@/composables/useAppearance';
import { switchLocale, type AppLocale } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    BarChart3,
    Boxes,
    ClipboardList,
    Cpu,
    CreditCard,
    FileClock,
    Home,
    Languages,
    LayoutDashboard,
    KeyRound,
    LogOut,
    Menu as MenuIcon,
    Moon,
    Package,
    Percent,
    Settings,
    UserCog,
    ShieldCheck,
    ShoppingCart,
    Star,
    Store,
    Sun,
    Tag,
    Truck,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-vue-next';
import { Avatar, Button, ConfigProvider, Drawer, Dropdown, Layout, Menu, MenuItem, MenuItemGroup, Tooltip, Typography } from 'ant-design-vue';
import arEG from 'ant-design-vue/es/locale/ar_EG';
import enGB from 'ant-design-vue/es/locale/en_GB';
import dayjs from 'dayjs';
import 'dayjs/locale/ar';
import 'dayjs/locale/en-gb';
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

const isSuperAdmin = computed(() => user.value?.roles.includes('super_admin') ?? false);

function can(permission: string | null): boolean {
    if (permission === null || isSuperAdmin.value) {
        return true;
    }

    return user.value?.permissions.includes(permission) ?? false;
}

interface NavLink {
    label: string;
    href: string;
    icon: unknown;
    permission: string | null;
}

interface NavSection {
    label: string | null;
    links: NavLink[];
}

const currentUrl = computed(() => page.url);

function pathOf(href: string): string {
    return href.replace(/^https?:\/\/[^/]+/, '');
}

function isActive(href: string): boolean {
    const path = pathOf(href);

    if (path === '/admin') {
        return currentUrl.value === '/admin';
    }

    return currentUrl.value.startsWith(path);
}

const sections = computed<NavSection[]>(() =>
    [
        {
            label: null,
            links: [{ label: t('admin.nav.dashboard'), href: route('admin.dashboard'), icon: LayoutDashboard, permission: null }],
        },
        {
            label: t('admin.nav.catalog'),
            links: [
                { label: t('admin.nav.products'), href: route('admin.products.index'), icon: Package, permission: 'products.view' },
                { label: t('admin.nav.categories'), href: route('admin.categories.index'), icon: Boxes, permission: 'categories.manage' },
                { label: t('admin.nav.brands'), href: route('admin.brands.index'), icon: Tag, permission: 'brands.manage' },
                { label: t('admin.nav.specifications'), href: route('admin.specifications.index'), icon: Cpu, permission: 'specifications.manage' },
            ],
        },
        {
            label: t('admin.nav.inventory'),
            links: [
                { label: t('admin.nav.stock'), href: route('admin.inventory.index'), icon: ClipboardList, permission: 'inventory.view' },
                { label: t('admin.nav.movements'), href: route('admin.inventory.movements'), icon: ArchiveRestore, permission: 'inventory.view' },
                { label: t('admin.nav.warehouses'), href: route('admin.warehouses.index'), icon: Warehouse, permission: 'warehouses.manage' },
            ],
        },
        {
            label: t('admin.nav.purchasing'),
            links: [
                { label: t('admin.nav.suppliers'), href: route('admin.suppliers.index'), icon: Truck, permission: 'suppliers.manage' },
                {
                    label: t('admin.nav.purchase_orders'),
                    href: route('admin.purchase-orders.index'),
                    icon: ClipboardList,
                    permission: 'purchase_orders.view',
                },
            ],
        },
        {
            label: t('admin.nav.sales'),
            links: [
                { label: t('admin.nav.orders'), href: route('admin.orders.index'), icon: ShoppingCart, permission: 'orders.view' },
                { label: t('admin.nav.refunds'), href: route('admin.refunds.index'), icon: CreditCard, permission: 'refunds.manage' },
                { label: t('admin.nav.treasury'), href: route('admin.treasury.index'), icon: Wallet, permission: 'treasury.view' },
            ],
        },
        {
            label: t('admin.nav.customers'),
            links: [
                { label: t('admin.nav.customers'), href: route('admin.customers.index'), icon: Users, permission: 'customers.view' },
                { label: t('admin.nav.reviews'), href: route('admin.reviews.index'), icon: Star, permission: 'reviews.moderate' },
                { label: t('admin.nav.coupons'), href: route('admin.coupons.index'), icon: Percent, permission: 'coupons.manage' },
            ],
        },
        {
            label: t('admin.nav.insights'),
            links: [{ label: t('admin.nav.reports'), href: route('admin.reports.sales'), icon: BarChart3, permission: 'reports.view' }],
        },
        {
            label: t('admin.nav.platform'),
            links: [
                { label: t('admin.nav.users'), href: route('admin.users.index'), icon: Users, permission: 'users.manage' },
                { label: t('admin.nav.roles'), href: route('admin.roles.index'), icon: ShieldCheck, permission: 'roles.manage' },
                { label: t('admin.nav.audit_logs'), href: route('admin.audit-logs.index'), icon: FileClock, permission: 'audit.view' },
                { label: t('admin.nav.settings'), href: route('admin.settings.index'), icon: Settings, permission: 'settings.manage' },
            ],
        },
    ]
        .map((section) => ({
            ...section,
            links: section.links.filter((link) => can(link.permission)),
        }))
        .filter((section) => section.links.length > 0),
);

/** AntD Menu keys are the route paths, so the active item falls out of the URL. */
const selectedKeys = computed(() => {
    const active = sections.value.flatMap((section) => section.links).filter((link) => isActive(link.href));

    // Longest match wins: /admin/inventory/movements must not also light up /admin/inventory.
    return active.length > 0 ? [pathOf(active.sort((a, b) => pathOf(b.href).length - pathOf(a.href).length)[0].href)] : [];
});

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

const antTheme = useAntdTheme({ colorPrimary: '#2563eb', borderRadius: 6 });

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
                class="admin-sider !fixed inset-y-0 start-0 z-50 hidden overflow-hidden border-e border-black/[0.06] lg:!block dark:border-white/[0.08]"
            >
                <div class="flex h-14 shrink-0 items-center gap-2 border-b border-black/[0.06] px-4 dark:border-white/[0.08]">
                    <Store class="h-5 w-5 shrink-0 text-blue-600" />
                    <Link :href="route('admin.dashboard')" class="truncate text-[0.9375rem] font-semibold tracking-tight">{{ appName }}</Link>
                </div>

                <div class="overlay-scroll min-h-0 flex-1 overflow-y-auto py-2">
                    <Menu mode="inline" :selected-keys="selectedKeys" class="!border-e-0">
                        <template v-for="section in sections" :key="section.label ?? 'root'">
                            <MenuItemGroup v-if="section.label" :title="section.label">
                                <MenuItem v-for="link in section.links" :key="pathOf(link.href)">
                                    <template #icon><component :is="link.icon" class="h-4 w-4" /></template>
                                    <Link :href="link.href" class="block">{{ link.label }}</Link>
                                </MenuItem>
                            </MenuItemGroup>
                            <MenuItem v-for="link in section.label ? [] : section.links" :key="pathOf(link.href)">
                                <template #icon><component :is="link.icon" class="h-4 w-4" /></template>
                                <Link :href="link.href" class="block">{{ link.label }}</Link>
                            </MenuItem>
                        </template>
                    </Menu>
                </div>
            </Sider>

            <!-- Mobile navigation -->
            <Drawer
                :open="sidebarOpen"
                :placement="locale === 'ar' ? 'right' : 'left'"
                :width="256"
                :body-style="{ padding: 0 }"
                :title="appName"
                @close="sidebarOpen = false"
            >
                <Menu mode="inline" :selected-keys="selectedKeys" class="!border-e-0" @click="sidebarOpen = false">
                    <template v-for="section in sections" :key="section.label ?? 'root'">
                        <MenuItemGroup v-if="section.label" :title="section.label">
                            <MenuItem v-for="link in section.links" :key="pathOf(link.href)">
                                <template #icon><component :is="link.icon" class="h-4 w-4" /></template>
                                <Link :href="link.href" class="block">{{ link.label }}</Link>
                            </MenuItem>
                        </MenuItemGroup>
                        <MenuItem v-for="link in section.label ? [] : section.links" :key="pathOf(link.href)">
                            <template #icon><component :is="link.icon" class="h-4 w-4" /></template>
                            <Link :href="link.href" class="block">{{ link.label }}</Link>
                        </MenuItem>
                    </template>
                </Menu>
            </Drawer>

            <Layout class="lg:ms-64">
                <Header
                    class="admin-header sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-black/[0.06] !px-4 !leading-none dark:border-white/[0.08]"
                >
                    <div class="flex items-center gap-3">
                        <Button type="text" class="lg:!hidden" :aria-label="t('admin.nav.open_menu')" @click="sidebarOpen = true">
                            <template #icon><MenuIcon class="h-5 w-5" /></template>
                        </Button>
                        <slot name="header" />
                    </div>

                    <div class="flex items-center gap-1">
                        <Link :href="route('home')" class="hidden sm:block">
                            <Button type="text">
                                <template #icon><Home class="h-4 w-4" /></template>
                                {{ t('admin.nav.storefront') }}
                            </Button>
                        </Link>

                        <Button type="text" :aria-label="`Switch language to ${otherLocaleLabel}`" @click="switchLocale(otherLocale)">
                            <template #icon><Languages class="h-4 w-4" /></template>
                            <span class="hidden sm:inline">{{ otherLocaleLabel }}</span>
                        </Button>

                        <Tooltip :title="t('admin.nav.toggle_dark')">
                            <Button type="text" :aria-label="t('admin.nav.toggle_dark')" @click="toggleDark">
                                <template #icon>
                                    <Moon v-if="appearance !== 'dark'" class="h-4 w-4" />
                                    <Sun v-else class="h-4 w-4" />
                                </template>
                            </Button>
                        </Tooltip>

                        <Dropdown trigger="click" placement="bottomRight">
                            <Button type="text" class="!px-2">
                                <Avatar :size="28" class="!bg-blue-600 !text-xs !font-semibold uppercase">
                                    {{ user?.name?.charAt(0) ?? '?' }}
                                </Avatar>
                                <span class="hidden max-w-32 truncate sm:block">{{ user?.name }}</span>
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
                    </div>
                </Header>

                <Content class="p-4 sm:p-6">
                    <slot />
                </Content>
            </Layout>
        </Layout>
    </ConfigProvider>
</template>
