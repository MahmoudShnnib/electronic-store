<script setup lang="ts">
import FlashMessages from '@/components/FlashMessages.vue';
import MobileBottomNav from '@/components/store/MobileBottomNav.vue';
import StoreFooter from '@/components/store/StoreFooter.vue';
import SearchBar from '@/components/store/SearchBar.vue';
import { isDark, useAppearance } from '@/composables/useAppearance';
import { useStoreTheme } from '@/composables/useStoreTheme';
import { useHideOnScroll } from '@/composables/useHideOnScroll';
import { switchLocale, type AppLocale } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Badge, Button, ConfigProvider, Divider, Drawer, Dropdown, Menu, MenuItem, Tooltip } from 'ant-design-vue';
import arEG from 'ant-design-vue/es/locale/ar_EG';
import enGB from 'ant-design-vue/es/locale/en_GB';
import dayjs from 'dayjs';
import 'dayjs/locale/ar';
import 'dayjs/locale/en-gb';
import {
    ChevronDown,
    Heart,
    Home,
    Languages,
    LayoutDashboard,
    LayoutGrid,
    LogIn,
    LogOut,
    MapPin,
    Menu as MenuIcon,
    Moon,
    Package,
    RotateCcw,
    Settings,
    ShoppingBag,
    Sun,
    User,
    UserPlus,
} from 'lucide-vue-next';
import { computed, ref, watchEffect, type FunctionalComponent } from 'vue';
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

function logout() {
    menuOpen.value = false;
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
    icon: FunctionalComponent;
    active: boolean;
    badge?: number;
}

/** The shop's own destinations — the same list feeds the desktop bar and the drawer. */
const shopLinks = computed<NavLink[]>(() => [
    { key: 'home', label: t('nav.home'), href: route('home'), icon: Home, active: isActive(['/']) },
    {
        key: 'catalog',
        label: t('nav.products'),
        href: route('catalog.index'),
        icon: LayoutGrid,
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
        { key: 'returns', label: t('nav.returns'), href: route('account.returns.index'), icon: RotateCcw, active: isActive(['/account/returns']) },
        { key: 'settings', label: t('nav.settings'), href: route('profile.edit'), icon: Settings, active: isActive(['/settings']) },
    );

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
                <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:gap-5">
                    <!-- Drawer trigger: the whole store, for viewports too narrow for the bar -->
                    <Button type="text" class="store-icon-btn lg:!hidden" :aria-label="t('nav.categories')" @click="menuOpen = true">
                        <template #icon><MenuIcon class="h-5 w-5" /></template>
                    </Button>

                    <Link :href="route('home')" class="store-wordmark flex shrink-0 items-baseline gap-1" :aria-label="appName">
                        <span class="store-wordmark-glyph">◆</span>
                        <span class="hidden sm:inline">{{ appName }}</span>
                    </Link>

                    <!-- All categories: the storefront's loudest header control, same as the
                         reference site's "كل الأقسام" pill. Desktop only; the drawer covers mobile. -->
                    <Dropdown v-if="navCategories.length > 0" :trigger="['click']" class="hidden lg:inline-flex">
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

                    <!-- Primary destinations, desktop only; the drawer carries them below `lg` -->
                    <nav class="hidden shrink-0 items-center gap-1 lg:flex" :aria-label="t('nav.categories')">
                        <Link v-for="link in shopLinks.slice(0, 2)" :key="link.key" :href="link.href">
                            <Button type="text" :class="['store-nav-btn', { 'store-nav-btn-active': link.active }]">{{ link.label }}</Button>
                        </Link>
                    </nav>

                    <div class="mx-auto hidden max-w-xl flex-1 md:block">
                        <SearchBar />
                    </div>

                    <div class="ms-auto flex shrink-0 items-center gap-1 md:ms-0">
                        <Tooltip :title="t('common.language')">
                            <Button type="text" class="store-icon-btn" :aria-label="t('common.language')" @click="toggleLocale">
                                <template #icon><Languages class="h-5 w-5" /></template>
                            </Button>
                        </Tooltip>

                        <Tooltip :title="isDark ? t('common.light_mode') : t('common.dark_mode')">
                            <Button type="text" class="store-icon-btn" :aria-label="t('common.toggle_theme')" @click="toggleDark">
                                <template #icon>
                                    <Sun v-if="isDark" class="h-5 w-5" />
                                    <Moon v-else class="h-5 w-5" />
                                </template>
                            </Button>
                        </Tooltip>

                        <Link :href="route('wishlist.index')" class="hidden lg:block">
                            <Tooltip :title="t('nav.wishlist')">
                                <Badge :count="wishlistCount" size="small" :offset="[locale === 'ar' ? 4 : -4, 4]">
                                    <Button type="text" class="store-icon-btn" :aria-label="t('nav.wishlist')">
                                        <template #icon><Heart class="h-5 w-5" /></template>
                                    </Button>
                                </Badge>
                            </Tooltip>
                        </Link>

                        <Link :href="route('cart.show')" class="hidden lg:block">
                            <Tooltip :title="t('nav.cart')">
                                <Badge :count="cartCount" size="small" :offset="[locale === 'ar' ? 4 : -4, 4]">
                                    <Button type="text" class="store-icon-btn" :aria-label="t('nav.cart')">
                                        <template #icon><ShoppingBag class="h-5 w-5" /></template>
                                    </Button>
                                </Badge>
                            </Tooltip>
                        </Link>

                        <!-- Account: a menu when signed in, a straight link when not -->
                        <Dropdown v-if="user" :trigger="['click']" placement="bottomRight" class="hidden lg:inline-flex">
                            <Button type="text" class="store-icon-btn" :aria-label="t('nav.account')">
                                <template #icon><User class="h-5 w-5" /></template>
                            </Button>
                            <template #overlay>
                                <Menu class="store-account-menu">
                                    <div class="px-4 py-2">
                                        <p class="truncate text-sm font-semibold">{{ user.name }}</p>
                                        <p class="truncate text-xs opacity-60">{{ user.email }}</p>
                                    </div>
                                    <Divider class="!my-1" />
                                    <MenuItem v-for="link in accountLinks" :key="link.key">
                                        <Link :href="link.href" class="flex items-center gap-2.5">
                                            <component :is="link.icon" class="h-4 w-4" />
                                            {{ link.label }}
                                        </Link>
                                    </MenuItem>
                                    <Divider class="!my-1" />
                                    <MenuItem key="logout" @click="logout">
                                        <span class="flex items-center gap-2.5">
                                            <LogOut class="h-4 w-4" />
                                            {{ t('nav.logout') }}
                                        </span>
                                    </MenuItem>
                                </Menu>
                            </template>
                        </Dropdown>

                        <Link v-else :href="route('login')" class="hidden lg:block">
                            <Button type="primary" shape="round">{{ t('nav.login') }}</Button>
                        </Link>
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
                                <component :is="link.icon" class="h-4 w-4" />
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
                                <ShoppingBag class="h-4 w-4" />
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
