<script setup lang="ts">
import FlashMessages from '@/components/FlashMessages.vue';
import { useAppearance } from '@/composables/useAppearance';
import { switchLocale, type AppLocale } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    BarChart3,
    Boxes,
    ChevronDown,
    ClipboardList,
    Cpu,
    CreditCard,
    FileClock,
    Home,
    Languages,
    LayoutDashboard,
    LogOut,
    Menu,
    Moon,
    Package,
    Percent,
    Settings,
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
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
    active?: boolean;
}

interface NavSection {
    label: string | null;
    links: NavLink[];
}

const currentUrl = computed(() => page.url);

function isActive(href: string): boolean {
    const path = href.replace(/^https?:\/\/[^/]+/, '');

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

const sidebarOpen = ref(false);
const userMenuOpen = ref(false);

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
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-950">
        <FlashMessages />

        <!-- Mobile sidebar backdrop -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 start-0 z-50 flex w-64 transform flex-col border-e border-gray-200 bg-white transition-transform dark:border-gray-800 dark:bg-gray-900 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full lg:rtl:translate-x-0'"
        >
            <div class="flex h-14 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-800">
                <Link :href="route('admin.dashboard')" class="flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100">
                    <Store class="h-6 w-6 text-blue-600" />
                    <span>{{ appName }}</span>
                </Link>
                <button type="button" class="p-1 text-gray-400 lg:hidden" :aria-label="t('admin.nav.close_menu')" @click="sidebarOpen = false">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-4">
                <div v-for="section in sections" :key="section.label ?? 'root'">
                    <p v-if="section.label" class="mb-1 px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        {{ section.label }}
                    </p>
                    <ul class="space-y-0.5">
                        <li v-for="link in section.links" :key="link.label">
                            <Link
                                :href="link.href"
                                class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm font-medium"
                                :class="
                                    isActive(link.href)
                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'
                                "
                                @click="sidebarOpen = false"
                            >
                                <component :is="link.icon" class="h-4 w-4 shrink-0" />
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Main -->
        <div class="lg:ps-64">
            <header
                class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-gray-200 bg-white/90 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90"
            >
                <div class="flex items-center gap-3">
                    <button type="button" class="p-1.5 text-gray-500 lg:hidden" :aria-label="t('admin.nav.open_menu')" @click="sidebarOpen = true">
                        <Menu class="h-5 w-5" />
                    </button>
                    <slot name="header" />
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('home')"
                        class="hidden items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 sm:flex"
                    >
                        <Home class="h-4 w-4" />
                        {{ t('admin.nav.storefront') }}
                    </Link>

                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                        :aria-label="`Switch language to ${otherLocaleLabel}`"
                        @click="switchLocale(otherLocale)"
                    >
                        <Languages class="h-4 w-4" />
                        <span class="hidden sm:block">{{ otherLocaleLabel }}</span>
                    </button>

                    <button
                        type="button"
                        class="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                        :aria-label="t('admin.nav.toggle_dark')"
                        @click="toggleDark"
                    >
                        <Moon v-if="appearance !== 'dark'" class="h-4 w-4" />
                        <Sun v-else class="h-4 w-4" />
                    </button>

                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <span
                                class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold uppercase text-white"
                            >
                                {{ user?.name?.charAt(0) ?? '?' }}
                            </span>
                            <span class="hidden max-w-32 truncate sm:block">{{ user?.name }}</span>
                            <ChevronDown class="h-3.5 w-3.5 text-gray-400" />
                        </button>

                        <div v-if="userMenuOpen" class="fixed inset-0 z-10" @click="userMenuOpen = false" />
                        <div
                            v-if="userMenuOpen"
                            class="absolute end-0 z-20 mt-1 w-48 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                        >
                            <p class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                {{ user?.email }}
                            </p>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-start text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800"
                                @click="logout"
                            >
                                <LogOut class="h-4 w-4" />
                                {{ t('admin.nav.log_out') }}
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
