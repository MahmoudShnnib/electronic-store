<script setup lang="ts">
import FlashMessages from '@/components/FlashMessages.vue';
import MobileBottomNav from '@/components/store/MobileBottomNav.vue';
import SearchBar from '@/components/store/SearchBar.vue';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { useAppearance } from '@/composables/useAppearance';
import { useHideOnScroll } from '@/composables/useHideOnScroll';
import { switchLocale, type AppLocale } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Heart,
    Home,
    Languages,
    LayoutDashboard,
    LayoutGrid,
    LogIn,
    LogOut,
    MapPin,
    Menu,
    Moon,
    Package,
    RotateCcw,
    Settings,
    ShoppingBag,
    Sun,
    UserPlus,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const headerHidden = useHideOnScroll();

const { updateAppearance } = useAppearance();
const isDark = ref(true);
const menuOpen = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggleDark() {
    isDark.value = !isDark.value;
    updateAppearance(isDark.value ? 'dark' : 'light');
}

const locale = computed(() => page.props.locale as AppLocale);

function toggleLocale() {
    menuOpen.value = false;
    switchLocale(locale.value === 'ar' ? 'en' : 'ar');
}

/** Persistent layouts keep the sheet mounted across navigations, so close it ourselves. */
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

const menuRow = 'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium hover:bg-[#F5F5F5] dark:hover:bg-neutral-900';
const menuBadge =
    'flex h-5 min-w-5 items-center justify-center rounded-full bg-black px-1.5 text-[11px] font-bold text-white dark:bg-white dark:text-black';

const footerPages = [
    { key: 'footer.about', page: 'about' },
    { key: 'footer.contact', page: 'contact' },
    { key: 'footer.privacy', page: 'privacy-policy' },
    { key: 'footer.terms', page: 'terms' },
    { key: 'footer.return_policy', page: 'return-policy' },
];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white text-black dark:bg-neutral-950 dark:text-white">
        <FlashMessages />

        <header
            class="sticky top-0 z-40 border-b border-[#DBDBDB] bg-white/95 backdrop-blur transition-transform duration-300 dark:border-neutral-800 dark:bg-neutral-950/95"
            :class="headerHidden ? '-translate-y-full' : 'translate-y-0'"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:gap-8">
                <!-- Menu: the whole store lives in here -->
                <Sheet :open="menuOpen" @update:open="menuOpen = $event">
                    <SheetTrigger class="rounded-md p-2 hover:bg-[#F5F5F5] dark:hover:bg-neutral-900" :aria-label="t('nav.categories')">
                        <Menu class="h-5 w-5" />
                    </SheetTrigger>
                    <SheetContent :side="locale === 'ar' ? 'right' : 'left'" class="w-72 overflow-y-auto bg-white dark:bg-neutral-950">
                        <nav class="mt-8 flex flex-col gap-1">
                            <Link :href="route('home')" :class="menuRow" @click="closeMenu">
                                <Home class="h-4 w-4" />
                                {{ t('nav.home') }}
                            </Link>

                            <Link :href="route('catalog.index')" :class="menuRow" @click="closeMenu">
                                <LayoutGrid class="h-4 w-4" />
                                {{ t('nav.products') }}
                            </Link>

                            <Link :href="route('wishlist.index')" :class="[menuRow, 'justify-between']" @click="closeMenu">
                                <span class="flex items-center gap-2.5">
                                    <Heart class="h-4 w-4" />
                                    {{ t('nav.wishlist') }}
                                </span>
                                <span v-if="wishlistCount > 0" :class="menuBadge">{{ wishlistCount }}</span>
                            </Link>

                            <Link :href="route('cart.show')" :class="[menuRow, 'justify-between']" @click="closeMenu">
                                <span class="flex items-center gap-2.5">
                                    <ShoppingBag class="h-4 w-4" />
                                    {{ t('nav.cart') }}
                                </span>
                                <span v-if="cartCount > 0" :class="menuBadge">{{ cartCount }}</span>
                            </Link>

                            <template v-if="user">
                                <div class="my-2 border-t border-[#EDEDED] dark:border-neutral-800" />

                                <Link v-if="user.is_staff" :href="route('admin.dashboard')" :class="menuRow" @click="closeMenu">
                                    <LayoutDashboard class="h-4 w-4" />
                                    {{ t('nav.admin') }}
                                </Link>
                                <Link :href="route('account.orders.index')" :class="menuRow" @click="closeMenu">
                                    <Package class="h-4 w-4" />
                                    {{ t('nav.orders') }}
                                </Link>
                                <Link :href="route('account.addresses.index')" :class="menuRow" @click="closeMenu">
                                    <MapPin class="h-4 w-4" />
                                    {{ t('nav.addresses') }}
                                </Link>
                                <Link :href="route('account.returns.index')" :class="menuRow" @click="closeMenu">
                                    <RotateCcw class="h-4 w-4" />
                                    {{ t('nav.returns') }}
                                </Link>
                                <Link :href="route('profile.edit')" :class="menuRow" @click="closeMenu">
                                    <Settings class="h-4 w-4" />
                                    {{ t('nav.settings') }}
                                </Link>
                            </template>

                            <div class="my-2 border-t border-[#EDEDED] dark:border-neutral-800" />

                            <button type="button" :class="[menuRow, 'text-start']" @click="toggleLocale">
                                <Languages class="h-4 w-4" />
                                {{ t('common.language') }}
                            </button>

                            <button type="button" :class="[menuRow, 'text-start']" @click="toggleDark">
                                <Sun v-if="isDark" class="h-4 w-4" />
                                <Moon v-else class="h-4 w-4" />
                                {{ isDark ? t('common.light_mode') : t('common.dark_mode') }}
                            </button>

                            <div class="my-2 border-t border-[#EDEDED] dark:border-neutral-800" />

                            <template v-if="!user">
                                <Link :href="route('login')" :class="menuRow" @click="closeMenu">
                                    <LogIn class="h-4 w-4" />
                                    {{ t('nav.login') }}
                                </Link>
                                <Link :href="route('register')" :class="menuRow" @click="closeMenu">
                                    <UserPlus class="h-4 w-4" />
                                    {{ t('nav.register') }}
                                </Link>
                            </template>

                            <button v-else type="button" :class="[menuRow, 'text-start']" @click="logout">
                                <LogOut class="h-4 w-4" />
                                {{ t('nav.logout') }}
                            </button>
                        </nav>
                    </SheetContent>
                </Sheet>

                <!-- Search -->
                <div class="mx-auto hidden max-w-md flex-1 md:block">
                    <SearchBar />
                </div>

                <!-- Wordmark on the opposite side, facing the hamburger -->
                <Link
                    :href="route('home')"
                    class="ms-auto flex shrink-0 items-baseline gap-0.5 text-xl font-bold lowercase tracking-tight md:ms-0"
                >
                    <span class="text-2xl leading-none">◆</span>
                    <span>{{ appName }}</span>
                </Link>
            </div>

            <!-- Mobile search -->
            <div class="border-t border-[#DBDBDB] px-4 py-2 dark:border-neutral-800 md:hidden">
                <SearchBar />
            </div>
        </header>

        <main class="w-full flex-1">
            <slot />
        </main>

        <footer class="mt-16 bg-[#181818] text-white">
            <div class="mx-auto max-w-7xl px-4 pb-28 pt-14 lg:py-14">
                <div class="grid gap-10 md:grid-cols-3">
                    <div>
                        <Link :href="route('home')" class="flex items-baseline gap-0.5 text-2xl font-bold lowercase tracking-tight">
                            <span>◆</span> {{ appName }}
                        </Link>
                        <p class="mt-4 max-w-sm text-sm leading-6 text-[#CFCFCF]">
                            {{ t('footer.tagline') }}
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <nav class="flex flex-wrap gap-x-8 gap-y-3 text-sm text-[#CFCFCF]">
                            <Link
                                v-for="item in footerPages"
                                :key="item.page"
                                :href="route('pages.show', item.page)"
                                class="transition hover:text-white"
                            >
                                {{ t(item.key) }}
                            </Link>
                        </nav>
                    </div>
                </div>
                <p class="mt-12 text-center text-xs text-[#909090]">© {{ new Date().getFullYear() }} {{ appName }} — {{ t('footer.rights') }}</p>
            </div>
        </footer>

        <MobileBottomNav />
    </div>
</template>
