<script setup lang="ts">
import { useHideOnScroll } from '@/composables/useHideOnScroll';
import { Link, usePage } from '@inertiajs/vue3';
import { Home, LayoutGrid, ShoppingBag, User } from 'lucide-vue-next';
import { computed, type FunctionalComponent } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const hidden = useHideOnScroll();

const user = computed(() => (page.props.auth as { user: unknown | null }).user);
const cartCount = computed(() => (page.props.cartCount as number) ?? 0);

interface NavItem {
    key: string;
    label: string;
    href: string;
    icon: FunctionalComponent;
    active: boolean;
    badge?: number;
}

/**
 * page.url is a path with query string; matching is done on path prefixes so
 * /products, /category/... and /brand/... all light up the catalogue item.
 */
const path = computed(() => page.url.split('?')[0]);

function startsWithAny(prefixes: string[]): boolean {
    return prefixes.some((prefix) => path.value === prefix || path.value.startsWith(`${prefix}/`));
}

const items = computed<NavItem[]>(() => [
    {
        key: 'home',
        label: t('nav.home'),
        href: route('home'),
        icon: Home,
        active: path.value === '/',
    },
    {
        key: 'products',
        label: t('nav.products'),
        href: route('catalog.index'),
        icon: LayoutGrid,
        active: startsWithAny(['/products', '/category', '/brand', '/search']),
    },
    {
        key: 'cart',
        label: t('nav.cart'),
        href: route('cart.show'),
        icon: ShoppingBag,
        active: startsWithAny(['/cart', '/checkout']),
        badge: cartCount.value,
    },
    {
        key: 'account',
        label: t('nav.account'),
        href: user.value ? route('account.orders.index') : route('login'),
        icon: User,
        active: startsWithAny(['/account', '/settings', '/login', '/register', '/wishlist']),
    },
]);
</script>

<template>
    <nav
        class="fixed inset-x-4 bottom-4 z-50 mx-auto flex h-16 max-w-md items-center justify-between gap-1.5 rounded-full bg-white p-2 shadow-2xl shadow-black/30 ring-1 ring-black/10 transition-transform duration-300 dark:bg-[#111111] dark:shadow-black/60 dark:ring-white/15 lg:hidden"
        :class="hidden ? 'translate-y-24' : 'translate-y-0'"
        :aria-label="t('nav.home')"
    >
        <Link
            v-for="item in items"
            :key="item.key"
            :href="item.href"
            class="relative flex h-12 items-center justify-center rounded-full transition-all duration-200"
            :class="
                item.active
                    ? 'flex-1 gap-2 bg-[#111111] px-4 text-white dark:bg-white dark:text-black'
                    : 'w-12 shrink-0 bg-[#EDEDED] text-black dark:bg-[#2E2D2D] dark:text-white'
            "
        >
            <component :is="item.icon" class="h-5 w-5 shrink-0" />
            <span v-if="item.active" class="truncate text-sm font-semibold">{{ item.label }}</span>
            <span
                v-if="item.badge && item.badge > 0 && !item.active"
                class="absolute -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#111111] px-1 text-[10px] font-bold text-white dark:bg-white dark:text-black ltr:-right-0.5 rtl:-left-0.5"
            >
                {{ item.badge }}
            </span>
        </Link>
    </nav>
</template>
