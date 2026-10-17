<script setup lang="ts">
import FlashMessages from '@/components/FlashMessages.vue';
import MobileBottomNav from '@/components/store/MobileBottomNav.vue';
import SearchBar from '@/components/store/SearchBar.vue';
import StoreFooter from '@/components/store/StoreFooter.vue';
import BoxIcon from '@/components/store/icons/BoxIcon.vue';
import CartIcon from '@/components/store/icons/CartIcon.vue';
import HomeIcon from '@/components/store/icons/HomeIcon.vue';
import { isDark, useAppearance } from '@/composables/useAppearance';
import { useHideOnScroll } from '@/composables/useHideOnScroll';
import { useStoreTheme } from '@/composables/useStoreTheme';
import { switchLocale, type AppLocale } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Badge, Button, ConfigProvider, Divider, Drawer, Dropdown, Menu, MenuItem } from 'ant-design-vue';
import arEG from 'ant-design-vue/es/locale/ar_EG';
import enGB from 'ant-design-vue/es/locale/en_GB';
import dayjs from 'dayjs';
import 'dayjs/locale/ar';
import 'dayjs/locale/en-gb';
import {
    ChevronDown,
    Heart,
    Languages,
    LayoutDashboard,
    LayoutGrid,
    LogIn,
    LogOut,
    MapPin,
    Menu as MenuIcon,
    Moon,
    Package,
    Settings,
    Sun,
    User,
    UserPlus,
} from 'lucide-vue-next';
import { computed, ref, watchEffect, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const headerHidden = useHideOnScroll();

const { updateAppearance } = useAppearance();
const menuOpen = ref(false);

function toggleDark() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}

const locale = computed(() => (page.props.locale as AppLocale) ?? 'en');

const accountOpen = ref(false);

function setDark(dark: boolean) {
    updateAppearance(dark ? 'dark' : 'light');
}

/** Each language is written in itself, so it is findable by someone who can't read the other. */
const localeOptions: { value: AppLocale; label: string }[] = [
    { value: 'ar', label: 'العربية' },
    { value: 'en', label: 'English' },
];

function setLocale(value: AppLocale) {
    if (value === locale.value) return;
    accountOpen.value = false;
    switchLocale(value);
}

function toggleLocale() {
    menuOpen.value = false;
    switchLocale(locale.value === 'ar' ? 'en' : 'ar');
}

/** Persistent layouts keep the drawer mounted across navigations, so close it ourselves. */
function closeMenu() {
    menuOpen.value = false;
}

interface AuthUser {
    id: number;
    name: string;
    email: string;
    is_staff: boolean;
}

const user = computed(() => (page.props.auth as { user: AuthUser | null }).user);
const cartCount = computed(() => (page.props.cartCount as number) ?? 0);
const wishlistCount = computed(() => (page.props.wishlistCount as number) ?? 0);
const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');

const firstName = computed(() => user.value?.name.trim().split(/\s+/)[0] ?? '');
const userInitial = computed(() => firstName.value.charAt(0).toUpperCase());

function logout() {
    menuOpen.value = false;
    accountOpen.value = false;
    router.post(route('logout'));
}

const antTheme = useStoreTheme();

const antLocale = computed(() => (locale.value === 'ar' ? arEG : enGB));
watchEffect(() => dayjs.locale(locale.value === 'ar' ? 'ar' : 'en-gb'));

/** Path only — `page.url` carries the query string and every link would miss. */
const path = computed(() => page.url.split('?')[0]);

function isActive(prefixes: string[]): boolean {
    return prefixes.some((prefix) => (prefix === '/' ? path.value === '/' : path.value === prefix || path.value.startsWith(`${prefix}/`)));
}

interface NavLink {
    key: string;
    label: string;
    href: string;
    icon: Component;
    active: boolean;
    badge?: number;
}

/** The shop's own destinations — the same list feeds the desktop bar and the drawer. */
const shopLinks = computed<NavLink[]>(() => [
    { key: 'home', label: t('nav.home'), href: route('home'), icon: HomeIcon, active: isActive(['/']) },
    {
        key: 'catalog',
        label: t('nav.products'),
        href: route('catalog.index'),
        icon: BoxIcon,
        active: isActive(['/products', '/category', '/brand', '/search']),
    },
    {
        key: 'wishlist',
        label: t('nav.wishlist'),
        href: route('wishlist.index'),
        icon: Heart,
        active: isActive(['/wishlist']),
        badge: wishlistCount.value,
    },
]);

/** Signed-in destinations; empty for guests, who get login/register instead. */
const accountLinks = computed<NavLink[]>(() => {
    if (!user.value) return [];

    const links: NavLink[] = [];

    if (user.value.is_staff) {
        links.push({ key: 'admin', label: t('nav.admin'), href: route('admin.dashboard'), icon: LayoutDashboard, active: false });
    }

    links.push(
        { key: 'orders', label: t('nav.orders'), href: route('account.orders.index'), icon: Package, active: isActive(['/account/orders']) },
        {
            key: 'addresses',
            label: t('nav.addresses'),
            href: route('account.addresses.index'),
            icon: MapPin,
            active: isActive(['/account/addresses']),
        },
        { key: 'settings', label: t('nav.settings'), href: route('profile.edit'), icon: Settings, active: isActive(['/settings']) },
    );

    return links;
});

/** The account panel's links: the account pages with Wishlist slotted in after Orders. */
const panelLinks = computed<NavLink[]>(() => {
    const wishlist = shopLinks.value.find((link) => link.key === 'wishlist');
    const links = [...accountLinks.value];
    const afterOrders = links.findIndex((link) => link.key === 'orders') + 1;

    if (wishlist) links.splice(afterOrders, 0, wishlist);

    return links;
});

interface NavCategory {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    products_count: number;
}

/** Shared from AppServiceProvider — the same cached list the homepage rail uses. */
const navCategories = computed(() => (page.props.navCategories as NavCategory[] | undefined) ?? []);

function categoryLabel(category: NavCategory): string {
    return locale.value === 'ar' && category.name_ar ? category.name_ar : category.name;
}
</script>

<template>
    <ConfigProvider :theme="antTheme" :locale="antLocale" :direction="locale === 'ar' ? 'rtl' : 'ltr'">
        <div class="store-shell flex min-h-screen flex-col">
            <FlashMessages />

            <header
                class="store-header sticky top-0 z-40 transition-transform duration-300"
                :class="headerHidden ? '-translate-y-full' : 'translate-y-0'"
            >
                <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:gap-4">
                    <!-- Drawer trigger: the whole store, for viewports too narrow for the bar -->
                    <Button type="text" class="store-icon-btn lg:!hidden" :aria-label="t('nav.categories')" @click="menuOpen = true">
                        <template #icon><MenuIcon class="h-5 w-5" /></template>
                    </Button>

                    <Link :href="route('home')" class="store-wordmark flex shrink-0 items-baseline gap-1" :aria-label="appName">
                        <span class="store-wordmark-glyph">◆</span>
                        <span class="hidden sm:inline">{{ appName }}</span>
                    </Link>

                    <!-- All categories: the loudest header control. Desktop only; the
                         drawer covers smaller screens. -->
                    <div class="hidden lg:block">
                        <Dropdown v-if="navCategories.length > 0" :trigger="['click']">
                            <Button class="store-cats-btn">
                                <span class="flex items-center gap-2">
                                    <LayoutGrid class="h-4 w-4" />
                                    {{ t('nav.all_categories') }}
                                    <ChevronDown class="h-4 w-4 opacity-80" />
                                </span>
                            </Button>
                            <template #overlay>
                                <Menu class="store-account-menu">
                                    <MenuItem v-for="category in navCategories" :key="category.id">
                                        <Link :href="route('catalog.category', category.slug)" class="flex items-center justify-between gap-6">
                                            <span>{{ categoryLabel(category) }}</span>
                                            <span class="text-xs opacity-50">{{ category.products_count }}</span>
                                        </Link>
                                    </MenuItem>
                                    <Divider class="!my-1" />
                                    <MenuItem key="all">
                                        <Link :href="route('catalog.index')">{{ t('home.view_all') }}</Link>
                                    </MenuItem>
                                </Menu>
                            </template>
                        </Dropdown>
                    </div>

                    <!-- Search gets the room Home/Products used to take: the logo is
                         home, and "All categories" ends in a view-all link. -->
                    <div class="mx-auto hidden max-w-2xl flex-1 md:block">
                        <SearchBar shortcut />
                    </div>

                    <div class="ms-auto flex shrink-0 items-center gap-1.5 md:ms-0">
                        <!-- Below lg the bar keeps the two toggles; on desktop they live in
                             the account menu so the bar holds five things and no more. -->
                        <Button type="text" class="store-icon-btn lg:!hidden" :aria-label="t('common.language')" @click="toggleLocale">
                            <template #icon><Languages class="h-5 w-5" /></template>
                        </Button>
                        <Button type="text" class="store-icon-btn lg:!hidden" :aria-label="t('common.toggle_theme')" @click="toggleDark">
                            <template #icon>
                                <Sun v-if="isDark" class="h-5 w-5" />
                                <Moon v-else class="h-5 w-5" />
                            </template>
                        </Button>

                        <!-- Cart: a labelled chip with its count — the one destination every
                             visit is heading for. -->
                        <Link
                            :href="route('cart.show')"
                            :class="['store-cart-chip hidden lg:inline-flex', { 'store-cart-chip-full': cartCount > 0 }]"
                            :aria-label="`${t('nav.cart')} (${cartCount})`"
                        >
                            <CartIcon class="h-[1.125rem] w-[1.125rem]" />
                            <span>{{ t('nav.cart') }}</span>
                            <span v-if="cartCount > 0" class="store-cart-count">{{ cartCount }}</span>
                        </Link>

                        <!-- Account: one panel for everyone. It says "My account", not
                             "Log in" — it holds more than sign-in (wishlist, language,
                             appearance), and a sign-in label made guests think that was all. -->
                        <div class="hidden lg:block">
                            <Dropdown v-model:open="accountOpen" :trigger="['click']" placement="bottomRight">
                                <button type="button" class="store-account-chip" :aria-expanded="accountOpen" aria-haspopup="dialog">
                                    <span class="store-avatar" aria-hidden="true">
                                        <template v-if="user">{{ userInitial }}</template>
                                        <User v-else class="h-4 w-4" />
                                    </span>
                                    <span class="max-w-[8rem] truncate">{{ user ? firstName : t('nav.account') }}</span>
                                    <ChevronDown class="store-account-caret h-4 w-4 opacity-60" />
                                </button>

                                <template #overlay>
                                    <div class="store-account-panel" role="dialog" :aria-label="t('nav.account')">
                                        <!-- 1. Who you are, or why you'd sign in -->
                                        <div v-if="user" class="store-account-head flex items-center gap-3">
                                            <span class="store-avatar store-avatar-lg" aria-hidden="true">{{ userInitial }}</span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                                                <p class="truncate text-xs" style="color: var(--st-ink-faint)">{{ user.email }}</p>
                                            </div>
                                        </div>

                                        <div v-else class="store-account-head">
                                            <p class="text-base font-bold">{{ t('nav.welcome') }}</p>
                                            <p class="mt-1 text-xs leading-relaxed" style="color: var(--st-ink-soft)">{{ t('nav.guest_pitch') }}</p>
                                            <div class="mt-4 grid gap-2">
                                                <Link :href="route('login')" class="block" @click="accountOpen = false">
                                                    <Button type="primary" block size="large">
                                                        <span class="flex items-center justify-center gap-2">
                                                            <LogIn class="h-4 w-4" />
                                                            {{ t('nav.login') }}
                                                        </span>
                                                    </Button>
                                                </Link>
                                                <Link :href="route('register')" class="block" @click="accountOpen = false">
                                                    <Button block size="large">
                                                        <span class="flex items-center justify-center gap-2">
                                                            <UserPlus class="h-4 w-4" />
                                                            {{ t('nav.register') }}
                                                        </span>
                                                    </Button>
                                                </Link>
                                            </div>
                                        </div>

                                        <!-- 2. Where you can go -->
                                        <nav class="store-account-section">
                                            <Link
                                                v-for="link in panelLinks"
                                                :key="link.key"
                                                :href="link.href"
                                                :class="['store-account-row', { 'store-account-row-on': link.active }]"
                                                @click="accountOpen = false"
                                            >
                                                <component :is="link.icon" class="h-4 w-4" />
                                                <span class="flex-1">{{ link.label }}</span>
                                                <span v-if="link.badge" class="store-account-count">{{ link.badge }}</span>
                                            </Link>
                                        </nav>

                                        <!-- 3. How the shop looks to you: current state, not a command -->
                                        <div class="store-account-section store-account-prefs">
                                            <div class="store-pref">
                                                <span class="store-pref-label">
                                                    <Languages class="h-4 w-4" />
                                                    {{ t('nav.language') }}
                                                </span>
                                                <div class="store-seg" role="radiogroup" :aria-label="t('nav.language')">
                                                    <button
                                                        v-for="option in localeOptions"
                                                        :key="option.value"
                                                        type="button"
                                                        role="radio"
                                                        :aria-checked="locale === option.value"
                                                        :lang="option.value"
                                                        :class="['store-seg-btn', { 'store-seg-btn-on': locale === option.value }]"
                                                        @click="setLocale(option.value)"
                                                    >
                                                        {{ option.label }}
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="store-pref">
                                                <span class="store-pref-label">
                                                    <Moon v-if="isDark" class="h-4 w-4" />
                                                    <Sun v-else class="h-4 w-4" />
                                                    {{ t('nav.appearance') }}
                                                </span>
                                                <div class="store-seg" role="radiogroup" :aria-label="t('nav.appearance')">
                                                    <button
                                                        type="button"
                                                        role="radio"
                                                        :aria-checked="!isDark"
                                                        :class="['store-seg-btn', { 'store-seg-btn-on': !isDark }]"
                                                        @click="setDark(false)"
                                                    >
                                                        <Sun class="h-3.5 w-3.5" />
                                                        {{ t('nav.light') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        role="radio"
                                                        :aria-checked="isDark"
                                                        :class="['store-seg-btn', { 'store-seg-btn-on': isDark }]"
                                                        @click="setDark(true)"
                                                    >
                                                        <Moon class="h-3.5 w-3.5" />
                                                        {{ t('nav.dark') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <button v-if="user" type="button" class="store-account-row store-account-logout" @click="logout">
                                            <LogOut class="h-4 w-4" />
                                            <span class="flex-1 text-start">{{ t('nav.logout') }}</span>
                                        </button>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>

                <!-- Mobile search sits on its own row rather than squeezing the bar -->
                <div class="store-header-search px-4 pb-2.5 md:hidden">
                    <SearchBar />
                </div>
            </header>

            <Drawer
                :open="menuOpen"
                :placement="locale === 'ar' ? 'right' : 'left'"
                :width="292"
                :closable="false"
                :body-style="{ padding: '0' }"
                @close="closeMenu"
            >
                <div class="flex h-full flex-col">
                    <div class="store-drawer-brand flex h-16 shrink-0 items-center px-5">
                        <Link :href="route('home')" class="store-wordmark flex items-baseline gap-1" @click="closeMenu">
                            <span class="store-wordmark-glyph">◆</span>
                            <span>{{ appName }}</span>
                        </Link>
                    </div>

                    <nav class="overlay-scroll flex-1 overflow-y-auto px-3 py-3">
                        <Link
                            v-for="link in shopLinks"
                            :key="link.key"
                            :href="link.href"
                            :class="['store-drawer-row', { 'store-drawer-row-active': link.active }]"
                            @click="closeMenu"
                        >
                            <span class="flex items-center gap-2.5">
                                <component :is="link.icon" :filled="link.active" class="h-4 w-4" />
                                {{ link.label }}
                            </span>
                            <Badge v-if="link.badge" :count="link.badge" size="small" />
                        </Link>

                        <Link
                            :href="route('cart.show')"
                            :class="['store-drawer-row', { 'store-drawer-row-active': isActive(['/cart']) }]"
                            @click="closeMenu"
                        >
                            <span class="flex items-center gap-2.5">
                                <CartIcon :filled="isActive(['/cart'])" class="h-4 w-4" />
                                {{ t('nav.cart') }}
                            </span>
                            <Badge v-if="cartCount" :count="cartCount" size="small" />
                        </Link>

                        <template v-if="accountLinks.length > 0">
                            <Divider class="!my-3" />
                            <Link
                                v-for="link in accountLinks"
                                :key="link.key"
                                :href="link.href"
                                :class="['store-drawer-row', { 'store-drawer-row-active': link.active }]"
                                @click="closeMenu"
                            >
                                <span class="flex items-center gap-2.5">
                                    <component :is="link.icon" class="h-4 w-4" />
                                    {{ link.label }}
                                </span>
                            </Link>
                        </template>

                        <Divider class="!my-3" />

                        <button type="button" class="store-drawer-row w-full" @click="toggleLocale">
                            <span class="flex items-center gap-2.5">
                                <Languages class="h-4 w-4" />
                                {{ t('common.language') }}
                            </span>
                        </button>

                        <button type="button" class="store-drawer-row w-full" @click="toggleDark">
                            <span class="flex items-center gap-2.5">
                                <Sun v-if="isDark" class="h-4 w-4" />
                                <Moon v-else class="h-4 w-4" />
                                {{ isDark ? t('common.light_mode') : t('common.dark_mode') }}
                            </span>
                        </button>
                    </nav>

                    <div class="shrink-0 border-t border-black/[0.06] p-3 dark:border-white/[0.08]">
                        <template v-if="!user">
                            <Link :href="route('login')" class="block" @click="closeMenu">
                                <Button type="primary" block shape="round">
                                    <template #icon><LogIn class="h-4 w-4" /></template>
                                    {{ t('nav.login') }}
                                </Button>
                            </Link>
                            <Link :href="route('register')" class="mt-2 block" @click="closeMenu">
                                <Button block shape="round">
                                    <template #icon><UserPlus class="h-4 w-4" /></template>
                                    {{ t('nav.register') }}
                                </Button>
                            </Link>
                        </template>

                        <Button v-else block shape="round" @click="logout">
                            <template #icon><LogOut class="h-4 w-4" /></template>
                            {{ t('nav.logout') }}
                        </Button>
                    </div>
                </div>
            </Drawer>

            <main class="min-h-[calc(75vh-4rem)] w-full flex-1">
                <slot />
            </main>

            <footer class="mt-16">
                <StoreFooter />
            </footer>

            <MobileBottomNav />
        </div>
    </ConfigProvider>
</template>
