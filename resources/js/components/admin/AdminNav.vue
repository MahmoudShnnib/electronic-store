<script setup lang="ts">
/**
 * The dashboard's navigation, shared by the desktop sidebar and the mobile
 * drawer. Every link and permission check is the one the old AntD Menu had;
 * only the grouping follows the reference design: a few top-level links, then
 * collapsible groups, with Platform and Settings pinned to the bottom.
 *
 * Group open/closed state persists per browser; the group holding the current
 * page always opens so the active link is never hidden.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    BarChart3,
    Boxes,
    ChevronDown,
    ClipboardList,
    Cpu,
    CreditCard,
    FileClock,
    FileText,
    LayoutDashboard,
    LayoutTemplate,
    Megaphone,
    Package,
    Percent,
    ReceiptText,
    ScanBarcode,
    ShieldCheck,
    ShoppingCart,
    SlidersHorizontal,
    Star,
    Tag,
    Truck,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-vue-next';
import { computed, reactive, watch, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const emit = defineEmits<{ (e: 'navigate'): void }>();

const { t } = useI18n();
const page = usePage();

interface AuthUser {
    roles: string[];
    permissions: string[];
}

interface NavLink {
    label: string;
    href: string;
    icon?: Component;
    permission: string | null;
}

interface NavGroup {
    key: string;
    label: string;
    icon: Component;
    links: NavLink[];
}

type NavItem = NavLink | NavGroup;

const user = computed(() => (page.props.auth as { user: AuthUser | null }).user);
const isSuperAdmin = computed(() => user.value?.roles.includes('super_admin') ?? false);

function can(permission: string | null): boolean {
    if (permission === null || isSuperAdmin.value) {
        return true;
    }

    return user.value?.permissions.includes(permission) ?? false;
}

const isGroup = (item: NavItem): item is NavGroup => 'links' in item;

const pathOf = (href: string) => href.replace(/^https?:\/\/[^/]+/, '');

const blocks = computed<NavItem[][]>(() => {
    const raw: NavItem[][] = [
        [
            { label: t('admin.nav.dashboard'), href: route('admin.dashboard'), icon: LayoutDashboard, permission: null },
            { label: t('admin.nav.orders'), href: route('admin.orders.index'), icon: ShoppingCart, permission: 'orders.view' },
            { label: t('admin.nav.products'), href: route('admin.products.index'), icon: Package, permission: 'products.view' },
            { label: t('admin.nav.customers'), href: route('admin.customers.index'), icon: Users, permission: 'customers.view' },
        ],
        [
            {
                key: 'sales',
                label: t('admin.nav.sales_cash'),
                icon: ReceiptText,
                links: [
                    { label: t('admin.nav.pos_sales'), href: route('admin.pos.sales.index'), icon: ScanBarcode, permission: 'pos.shifts.view' },
                    { label: t('admin.nav.refunds'), href: route('admin.refunds.index'), icon: CreditCard, permission: 'refunds.manage' },
                    { label: t('admin.nav.pos_shifts'), href: route('admin.pos.shifts.index'), icon: Wallet, permission: 'pos.shifts.view' },
                    { label: t('admin.nav.treasury'), href: route('admin.treasury.index'), icon: Wallet, permission: 'treasury.view' },
                ],
            },
            {
                key: 'inventory',
                label: t('admin.nav.inventory'),
                icon: Warehouse,
                links: [
                    { label: t('admin.nav.stock'), href: route('admin.inventory.index'), icon: ClipboardList, permission: 'inventory.view' },
                    { label: t('admin.nav.movements'), href: route('admin.inventory.movements'), icon: ArchiveRestore, permission: 'inventory.view' },
                    { label: t('admin.nav.warehouses'), href: route('admin.warehouses.index'), icon: Warehouse, permission: 'warehouses.manage' },
                ],
            },
            {
                key: 'purchasing',
                label: t('admin.nav.purchasing'),
                icon: Truck,
                links: [
                    {
                        label: t('admin.nav.purchase_orders'),
                        href: route('admin.purchase-orders.index'),
                        icon: ClipboardList,
                        permission: 'purchase_orders.view',
                    },
                    { label: t('admin.nav.suppliers'), href: route('admin.suppliers.index'), icon: Truck, permission: 'suppliers.manage' },
                ],
            },
            { label: t('admin.nav.reports'), href: route('admin.reports.sales'), icon: BarChart3, permission: 'reports.view' },
        ],
        [
            {
                key: 'catalog',
                label: t('admin.nav.catalog_setup'),
                icon: Tag,
                links: [
                    { label: t('admin.nav.categories'), href: route('admin.categories.index'), icon: Boxes, permission: 'categories.manage' },
                    { label: t('admin.nav.brands'), href: route('admin.brands.index'), icon: Tag, permission: 'brands.manage' },
                    {
                        label: t('admin.nav.specifications'),
                        href: route('admin.specifications.index'),
                        icon: Cpu,
                        permission: 'specifications.manage',
                    },
                ],
            },
            {
                key: 'marketing',
                label: t('admin.nav.marketing'),
                icon: Megaphone,
                links: [
                    { label: t('admin.nav.homepage'), href: route('admin.homepage.index'), icon: LayoutTemplate, permission: 'settings.manage' },
                    { label: t('admin.nav.pages'), href: route('admin.pages.index'), icon: FileText, permission: 'settings.manage' },
                    { label: t('admin.nav.coupons'), href: route('admin.coupons.index'), icon: Percent, permission: 'coupons.manage' },
                    { label: t('admin.nav.reviews'), href: route('admin.reviews.index'), icon: Star, permission: 'reviews.moderate' },
                ],
            },
        ],
        // Pinned to the bottom of the sidebar.
        [
            {
                key: 'platform',
                label: t('admin.nav.platform'),
                icon: ShieldCheck,
                links: [
                    { label: t('admin.nav.users'), href: route('admin.users.index'), icon: Users, permission: 'users.manage' },
                    { label: t('admin.nav.roles'), href: route('admin.roles.index'), icon: ShieldCheck, permission: 'roles.manage' },
                    { label: t('admin.nav.audit_logs'), href: route('admin.audit-logs.index'), icon: FileClock, permission: 'audit.view' },
                ],
            },
            { label: t('admin.nav.settings'), href: route('admin.settings.index'), icon: SlidersHorizontal, permission: 'settings.manage' },
        ],
    ];

    return raw.map((block) =>
        block
            .map((item) => (isGroup(item) ? { ...item, links: item.links.filter((link) => can(link.permission)) } : item))
            .filter((item) => (isGroup(item) ? item.links.length > 0 : can(item.permission))),
    );
});

const allLinks = computed(() => blocks.value.flat().flatMap((item) => (isGroup(item) ? item.links : [item])));

/** Longest match wins: /admin/inventory/movements must not also light up /admin/inventory. */
const activePath = computed(() => {
    const url = page.url;
    const matches = allLinks.value.map((link) => pathOf(link.href)).filter((path) => (path === '/admin' ? url === '/admin' : url.startsWith(path)));

    return matches.sort((a, b) => b.length - a.length)[0] ?? null;
});

const isActive = (link: NavLink) => pathOf(link.href) === activePath.value;

const STORAGE_KEY = 'adminNavGroups';

function readStored(): Record<string, boolean> {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') ?? {};
    } catch {
        return {};
    }
}

const open = reactive<Record<string, boolean>>(readStored());

function toggle(key: string): void {
    open[key] = !open[key];

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(open));
    } catch {
        // Private mode or blocked storage: the toggle still works for this page.
    }
}

watch(
    activePath,
    () => {
        for (const item of blocks.value.flat()) {
            if (isGroup(item) && item.links.some(isActive)) {
                open[item.key] = true;
            }
        }
    },
    { immediate: true },
);

const lastIndex = computed(() => blocks.value.length - 1);
</script>

<template>
    <nav class="admin-nav" :aria-label="t('admin.nav.dashboard')">
        <template v-for="(block, index) in blocks" :key="index">
            <div v-if="index > 0 && index < lastIndex && block.length > 0" class="admin-nav-sep" />

            <div v-if="block.length > 0" class="admin-nav-list" :class="{ 'admin-nav-bottom': index === lastIndex }">
                <template v-for="item in block" :key="isGroup(item) ? item.key : item.href">
                    <template v-if="isGroup(item)">
                        <button
                            type="button"
                            class="admin-nav-group"
                            :aria-expanded="open[item.key] ? 'true' : 'false'"
                            :aria-controls="`admin-nav-${item.key}`"
                            @click="toggle(item.key)"
                        >
                            <component :is="item.icon" aria-hidden="true" />
                            <span>{{ item.label }}</span>
                            <ChevronDown class="admin-nav-chev" aria-hidden="true" />
                        </button>
                        <div v-show="open[item.key]" :id="`admin-nav-${item.key}`" class="admin-nav-sub">
                            <Link
                                v-for="link in item.links"
                                :key="link.href"
                                :href="link.href"
                                class="admin-nav-link"
                                :aria-current="isActive(link) ? 'page' : undefined"
                                @click="emit('navigate')"
                            >
                                {{ link.label }}
                            </Link>
                        </div>
                    </template>

                    <Link
                        v-else
                        :href="item.href"
                        class="admin-nav-link"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        @click="emit('navigate')"
                    >
                        <component :is="item.icon" aria-hidden="true" />
                        <span>{{ item.label }}</span>
                    </Link>
                </template>
            </div>
        </template>
    </nav>
</template>
