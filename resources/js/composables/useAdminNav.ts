import { router, usePage } from '@inertiajs/vue3';
import {
    ArchiveRestore,
    BarChart3,
    Bike,
    Boxes,
    ClipboardList,
    ClipboardPlus,
    Cpu,
    CreditCard,
    FileClock,
    FileSpreadsheet,
    FileText,
    Globe,
    LayoutDashboard,
    LayoutTemplate,
    Package,
    PackagePlus,
    Percent,
    ReceiptText,
    ScanBarcode,
    ShieldCheck,
    ShoppingCart,
    SlidersHorizontal,
    Star,
    Tag,
    Truck,
    UserCog,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The dashboard's navigation as data: the sidebar, the mobile drawer and the
 * command palette all render this one permission-filtered list, so a page that
 * is reachable from one is reachable from all three.
 *
 * Every `permission` is the one the page's controller checks. `super_admin`
 * passes everything, as it does server-side through Gate::before.
 */

interface AuthUser {
    name: string;
    roles: string[];
    permissions: string[];
}

export interface NavLink {
    key: string;
    /** i18n key, kept so the palette can match the label in both languages. */
    labelKey: string;
    label: string;
    href: string;
    icon: Component;
    permission: string | null;
    /** An `AdminAttention` item key whose count is shown beside the link. */
    badge?: string;
}

export interface NavGroup {
    key: string;
    labelKey: string;
    label: string;
    icon: Component;
    links: NavLink[];
}

export type NavItem = NavLink | NavGroup;

export interface QuickAction {
    key: string;
    labelKey: string;
    label: string;
    href: string;
    icon: Component;
}

export const isGroup = (item: NavItem): item is NavGroup => 'links' in item;

export const pathOf = (href: string) => href.replace(/^https?:\/\/[^/]+/, '').replace(/[?#].*$/, '');

export function useAdminPermissions() {
    const page = usePage();
    const user = computed(() => (page.props.auth as { user: AuthUser | null }).user);
    const isSuperAdmin = computed(() => user.value?.roles.includes('super_admin') ?? false);

    function can(permission: string | null): boolean {
        if (permission === null || isSuperAdmin.value) {
            return true;
        }

        return user.value?.permissions.includes(permission) ?? false;
    }

    return { user, can };
}

type RawLink = Omit<NavLink, 'label'>;
type RawGroup = Omit<NavGroup, 'label' | 'links'> & { links: RawLink[] };

export function useAdminNav() {
    const { t } = useI18n();
    const page = usePage();
    const { can } = useAdminPermissions();

    const link = (key: string, labelKey: string, routeName: string, icon: Component, permission: string | null, badge?: string): RawLink => ({
        key,
        labelKey,
        href: route(routeName),
        icon,
        permission,
        badge,
    });

    /**
     * Three blocks: the dashboard, the working groups, and the team/settings
     * block pinned to the bottom of the sidebar. Groups are named for the job
     * they hold, and each page lives in exactly one of them.
     */
    const blocks = computed<NavItem[][]>(() => {
        const raw: (RawLink | RawGroup)[][] = [
            [link('dashboard', 'admin.nav.dashboard', 'admin.dashboard', LayoutDashboard, null)],
            [
                {
                    key: 'sales',
                    labelKey: 'admin.nav.sales',
                    icon: ShoppingCart,
                    links: [
                        link('orders', 'admin.nav.orders', 'admin.orders.index', ShoppingCart, 'orders.view', 'orders_to_fulfil'),
                        link('drivers', 'admin.nav.drivers', 'admin.drivers.index', Bike, 'orders.manage'),
                        link('pos_sales', 'admin.nav.pos_sales', 'admin.pos.sales.index', ScanBarcode, 'pos.shifts.view'),
                        link('pos_shifts', 'admin.nav.pos_shifts', 'admin.pos.shifts.index', Wallet, 'pos.shifts.view', 'open_shifts'),
                        link('refunds', 'admin.nav.refunds', 'admin.refunds.index', CreditCard, 'refunds.manage'),
                        link('customers', 'admin.nav.customers', 'admin.customers.index', Users, 'customers.view'),
                    ],
                },
                {
                    key: 'catalog',
                    labelKey: 'admin.nav.products',
                    icon: Package,
                    links: [
                        link('products', 'admin.nav.all_products', 'admin.products.index', Package, 'products.view'),
                        link('categories', 'admin.nav.categories', 'admin.categories.index', Boxes, 'categories.manage'),
                        link('brands', 'admin.nav.brands', 'admin.brands.index', Tag, 'brands.manage'),
                        link('specifications', 'admin.nav.specifications', 'admin.specifications.index', Cpu, 'specifications.manage'),
                    ],
                },
                {
                    key: 'stock',
                    labelKey: 'admin.nav.stock_purchasing',
                    icon: Warehouse,
                    links: [
                        link('stock', 'admin.nav.stock', 'admin.inventory.index', ClipboardList, 'inventory.view', 'low_stock'),
                        link('movements', 'admin.nav.movements', 'admin.inventory.movements', ArchiveRestore, 'inventory.view'),
                        link('warehouses', 'admin.nav.warehouses', 'admin.warehouses.index', Warehouse, 'warehouses.manage'),
                        link(
                            'purchase_orders',
                            'admin.nav.purchase_orders',
                            'admin.purchase-orders.index',
                            ClipboardList,
                            'purchase_orders.view',
                            'purchase_orders_open',
                        ),
                        link('suppliers', 'admin.nav.suppliers', 'admin.suppliers.index', Truck, 'suppliers.manage'),
                    ],
                },
                {
                    // Everything that changes what shoppers see, in one place.
                    key: 'store',
                    labelKey: 'admin.nav.online_store',
                    icon: Globe,
                    links: [
                        link('pages', 'admin.nav.pages', 'admin.pages.index', FileText, 'settings.manage'),
                        link('homepage', 'admin.nav.homepage_current', 'admin.homepage.index', LayoutTemplate, 'settings.manage'),
                        link('coupons', 'admin.nav.coupons', 'admin.coupons.index', Percent, 'coupons.manage'),
                        link('reviews', 'admin.nav.reviews', 'admin.reviews.index', Star, 'reviews.moderate', 'pending_reviews'),
                    ],
                },
                {
                    key: 'finance',
                    labelKey: 'admin.nav.finance_reports',
                    icon: BarChart3,
                    links: [
                        link('treasury', 'admin.nav.treasury', 'admin.treasury.index', Wallet, 'treasury.view'),
                        link('reports_sales', 'admin.nav.sales_report', 'admin.reports.sales', BarChart3, 'reports.view'),
                        link('reports_invoices', 'admin.nav.invoices_report', 'admin.reports.invoices', ReceiptText, 'reports.view'),
                        link('reports_inventory', 'admin.nav.inventory_report', 'admin.reports.inventory', FileSpreadsheet, 'reports.view'),
                    ],
                },
            ],
            [
                {
                    key: 'team',
                    labelKey: 'admin.nav.team_access',
                    icon: ShieldCheck,
                    links: [
                        link('users', 'admin.nav.users', 'admin.users.index', UserCog, 'users.manage'),
                        link('roles', 'admin.nav.roles', 'admin.roles.index', ShieldCheck, 'roles.manage'),
                        link('audit_logs', 'admin.nav.audit_logs', 'admin.audit-logs.index', FileClock, 'audit.view'),
                    ],
                },
                link('settings', 'admin.nav.settings', 'admin.settings.index', SlidersHorizontal, 'settings.manage'),
            ],
        ];

        const label = <T extends { labelKey: string }>(item: T) => ({ ...item, label: t(item.labelKey) });

        return raw.map((block) =>
            block.flatMap((item): NavItem[] => {
                if (!('links' in item)) {
                    return can(item.permission) ? [label(item)] : [];
                }

                const links = item.links.filter((l) => can(l.permission)).map(label);

                // A group holding a single page for this user is just that page.
                if (links.length <= 1) {
                    return links;
                }

                return [{ ...label(item), links }];
            }),
        );
    });

    /** Every reachable page, with the group it sits in (for the palette's subtitle). */
    const allLinks = computed(() =>
        blocks.value.flat().flatMap((item) => (isGroup(item) ? item.links.map((l) => ({ ...l, group: item.label })) : [{ ...item, group: '' }])),
    );

    /** Longest match wins: /admin/inventory/movements must not also light up /admin/inventory. */
    const activePath = computed(() => {
        const url = pathOf(page.url);
        const matches = allLinks.value
            .map((l) => pathOf(l.href))
            .filter((path) => (path === '/admin' ? url === '/admin' : url === path || url.startsWith(`${path}/`)));

        return matches.sort((a, b) => b.length - a.length)[0] ?? null;
    });

    const isActive = (l: NavLink) => pathOf(l.href) === activePath.value;

    /** The header's "Create" menu: the create screens that exist as pages of their own. */
    const quickActions = computed<QuickAction[]>(() =>
        [
            {
                key: 'new_product',
                labelKey: 'admin.nav.new_product',
                routeName: 'admin.products.create',
                icon: PackagePlus,
                permission: 'products.create',
            },
            { key: 'new_order', labelKey: 'admin.nav.new_order', routeName: 'admin.orders.create', icon: ShoppingCart, permission: 'orders.manage' },
            {
                key: 'new_purchase_order',
                labelKey: 'admin.nav.new_purchase_order',
                routeName: 'admin.purchase-orders.create',
                icon: ClipboardPlus,
                permission: 'purchase_orders.manage',
            },
        ]
            .filter((a) => can(a.permission))
            .map(({ routeName, permission: _permission, ...a }) => ({ ...a, label: t(a.labelKey), href: route(routeName) })),
    );

    return { blocks, allLinks, activePath, isActive, quickActions };
}

/*
 * Attention counts for the sidebar badges. Module-level so the sidebar and the
 * drawer (two AdminNav instances) share one fetch; the layout stays mounted
 * across Inertia visits, so this runs once and then at most once a minute.
 */
interface AttentionItem {
    key: string;
    count: number;
    href: string;
    tone: string;
}

const attentionCounts = ref<Record<string, number>>({});
let attentionFetchedAt = 0;
let attentionListening = false;

/** How often the counts refresh while a dashboard tab is visible — a new order shouldn't wait for a click. */
const ATTENTION_POLL_MS = 60_000;

type NewOrdersListener = (added: number) => void;
let newOrdersListener: NewOrdersListener | null = null;

/** The layout listens here to alert staff when orders arrive; null to stop. */
export function onNewOrders(listener: NewOrdersListener | null): void {
    newOrdersListener = listener;
}

export function seedAttention(items: AttentionItem[]): void {
    const before = attentionCounts.value.orders_to_fulfil;
    attentionCounts.value = Object.fromEntries(items.map((i) => [i.key, i.count]));
    attentionFetchedAt = Date.now();

    // Only a rise from a known count is news: the first load just sets the baseline.
    const after = attentionCounts.value.orders_to_fulfil;
    if (before !== undefined && after !== undefined && after > before) {
        newOrdersListener?.(after - before);
    }
}

async function refreshAttention(force = false): Promise<void> {
    if (!force && Date.now() - attentionFetchedAt < 60_000) {
        return;
    }

    attentionFetchedAt = Date.now();

    try {
        const response = await fetch(route('admin.attention'), { headers: { Accept: 'application/json' } });

        if (response.ok) {
            seedAttention(((await response.json()) as { items: AttentionItem[] }).items);
        }
    } catch {
        // Offline or mid-navigation: keep the last counts.
    }
}

export function useAttentionCounts() {
    if (!attentionListening) {
        attentionListening = true;
        void refreshAttention(true);
        // Only while still inside the dashboard: a visit to the shop does not need it.
        const inAdmin = () => window.location.pathname.startsWith('/admin');
        router.on('navigate', () => inAdmin() && void refreshAttention());
        // And on a timer while the tab is in view, so an operator who stays on one
        // screen still sees new orders; a hidden tab waits until it is looked at.
        window.setInterval(() => document.visibilityState === 'visible' && inAdmin() && void refreshAttention(true), ATTENTION_POLL_MS);
        document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && inAdmin() && void refreshAttention());
    }

    return attentionCounts;
}
