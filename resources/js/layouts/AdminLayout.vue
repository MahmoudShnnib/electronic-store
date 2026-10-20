<script setup lang="ts">
import AdminNav from '@/components/admin/AdminNav.vue';
import CommandPalette, { type PaletteCommand } from '@/components/admin/CommandPalette.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import { useAdminMoneyBidi } from '@/composables/useAdminMoneyBidi';
import { onNewOrders, useAdminNav, useAdminPermissions } from '@/composables/useAdminNav';
import { useAdminTheme } from '@/composables/useAdminTheme';
import { useAppearance } from '@/composables/useAppearance';
import { useMediaQuery } from '@/composables/useMediaQuery';
import { switchLocale, type AppLocale } from '@/i18n';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Avatar,
    Button,
    ConfigProvider,
    Drawer,
    Dropdown,
    Layout,
    Menu,
    MenuDivider,
    MenuItem,
    notification,
    Tooltip,
    Typography,
} from 'ant-design-vue';
import arEG from 'ant-design-vue/es/locale/ar_EG';
import enGB from 'ant-design-vue/es/locale/en_GB';
import dayjs from 'dayjs';
import 'dayjs/locale/ar';
import 'dayjs/locale/en-gb';
import {
    Home,
    KeyRound,
    Languages,
    LogOut,
    Menu as MenuIcon,
    Moon,
    PanelLeftClose,
    PanelLeftOpen,
    PanelRightClose,
    PanelRightOpen,
    Plus,
    ScanLine,
    Search,
    Store,
    Sun,
    UserCog,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const { Header, Sider, Content } = Layout;
const { Text } = Typography;

const page = usePage();

const { user, can } = useAdminPermissions();
const { quickActions } = useAdminNav();

/** Shared from HandleInertiaRequests, so the sidebar follows the configured store name. */
const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');

/** The register lives in the top bar, not the nav; same permission as before. */
const canUsePos = computed(() => can('pos.use'));

/* --- Sidebar: full width or an icon rail on desktop, a drawer below lg ---- */

/** Tailwind's lg and md, so behaviour switches exactly where the classes do. */
const isDesktop = useMediaQuery('(min-width: 1024px)');
const isMd = useMediaQuery('(min-width: 768px)');
const drawerOpen = ref(false);

const RAIL_KEY = 'adminSidebarRail';

function readRail(): boolean {
    try {
        return localStorage.getItem(RAIL_KEY) === '1';
    } catch {
        return false;
    }
}

const rail = ref(readRail());

function toggleSidebar(): void {
    if (!isDesktop.value) {
        drawerOpen.value = true;

        return;
    }

    rail.value = !rail.value;

    try {
        localStorage.setItem(RAIL_KEY, rail.value ? '1' : '0');
    } catch {
        // Blocked storage: the toggle still works until the next reload.
    }
}

const { appearance, updateAppearance } = useAppearance();

function toggleDark(): void {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}

const locale = computed(() => (page.props.locale as AppLocale) ?? 'en');
const isRtl = computed(() => locale.value === 'ar');

/** "Collapse" points at the sidebar's own edge, which RTL puts on the right. */
const sidebarIcon = computed(() => {
    if (!isDesktop.value) {
        return MenuIcon;
    }

    if (isRtl.value) {
        return rail.value ? PanelRightOpen : PanelRightClose;
    }

    return rail.value ? PanelLeftOpen : PanelLeftClose;
});

const sidebarLabel = computed(() => {
    if (!isDesktop.value) {
        return t('admin.nav.open_menu');
    }

    return rail.value ? t('admin.nav.expand_sidebar') : t('admin.nav.collapse_sidebar');
});

/** Label names the language you switch *to*, so it reads correctly in either direction. */
const otherLocale = computed<AppLocale>(() => (locale.value === 'ar' ? 'en' : 'ar'));
const otherLocaleLabel = computed(() => (locale.value === 'ar' ? 'English' : 'العربية'));

function logout(): void {
    router.post(route('logout'));
}

/* --- Command palette -------------------------------------------------------- */

const paletteOpen = ref(false);

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
const shortcutLabel = isMac ? '⌘K' : 'Ctrl K';

/** The palette's non-page commands: the same things the top bar does. */
const paletteCommands = computed<PaletteCommand[]>(() => [
    ...(canUsePos.value
        ? [
              {
                  key: 'pos',
                  label: t('admin.nav.pos'),
                  keywords: 'pos register cashier كاشير',
                  icon: ScanLine,
                  run: () => router.visit(route('admin.pos.register')),
              },
          ]
        : []),
    { key: 'store', label: t('admin.nav.storefront'), keywords: 'shop store website متجر', icon: Home, run: () => router.visit(route('home')) },
    {
        key: 'locale',
        label: t('admin.palette.switch_language', { lang: otherLocaleLabel.value }),
        keywords: 'language arabic english لغة',
        icon: Languages,
        run: () => switchLocale(otherLocale.value),
    },
    {
        key: 'theme',
        label: t('admin.nav.toggle_dark'),
        keywords: 'dark light theme mode داكن فاتح',
        icon: appearance.value === 'dark' ? Sun : Moon,
        run: toggleDark,
    },
    {
        key: 'account',
        label: t('admin.nav.account_settings'),
        keywords: 'profile حساب',
        icon: UserCog,
        run: () => router.visit(route('profile.edit')),
    },
]);

function isTyping(target: EventTarget | null): boolean {
    const el = target as HTMLElement | null;

    return !!el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
}

function onGlobalKeydown(event: KeyboardEvent): void {
    // `code` too: with an Arabic layout the K key reports `key` as "ن", and "/" as "ظ".
    if ((event.metaKey || event.ctrlKey) && !event.altKey && (event.key.toLowerCase() === 'k' || event.code === 'KeyK')) {
        event.preventDefault();
        paletteOpen.value = !paletteOpen.value;

        return;
    }

    // "/" opens it too, the way search works on most sites — but never while typing.
    if ((event.key === '/' || event.code === 'Slash') && !event.metaKey && !event.ctrlKey && !paletteOpen.value && !isTyping(event.target)) {
        event.preventDefault();
        paletteOpen.value = true;
    }
}

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onGlobalKeydown));

/**
 * A short two-note chime, made on the fly (no audio file to ship). Browsers
 * only allow sound after the page has been interacted with, which a working
 * dashboard always has; if not, the notice still shows.
 */
function chime(): void {
    try {
        const audio = new AudioContext();
        [880, 1320].forEach((frequency, i) => {
            const tone = audio.createOscillator();
            const gain = audio.createGain();
            tone.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, audio.currentTime + i * 0.18);
            gain.gain.exponentialRampToValueAtTime(0.25, audio.currentTime + i * 0.18 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + i * 0.18 + 0.3);
            tone.connect(gain).connect(audio.destination);
            tone.start(audio.currentTime + i * 0.18);
            tone.stop(audio.currentTime + i * 0.18 + 0.32);
        });
        window.setTimeout(() => void audio.close(), 1000);
    } catch {
        // No audio available: the notice below is enough.
    }
}

onMounted(() =>
    onNewOrders((added) => {
        chime();
        notification.info({
            key: 'new-orders',
            message: t('admin.alerts.new_orders', { count: added }),
            description: t('admin.alerts.new_orders_hint'),
            placement: isRtl.value ? 'bottomLeft' : 'bottomRight',
            duration: 0,
            onClick: () => {
                notification.close('new-orders');
                router.visit(route('admin.orders.index', { status: 'in_progress' }));
            },
            style: { cursor: 'pointer' },
        });
    }),
);
onBeforeUnmount(() => onNewOrders(null));

const antTheme = useAdminTheme();
useAdminMoneyBidi();

/** AntD's own strings (calendar, table, pagination) plus the dayjs calendar itself. */
const antLocale = computed(() => (locale.value === 'ar' ? arEG : enGB));
watchEffect(() => dayjs.locale(locale.value === 'ar' ? 'ar' : 'en-gb'));
</script>

<template>
    <ConfigProvider :theme="antTheme" :locale="antLocale" :direction="isRtl ? 'rtl' : 'ltr'">
        <Layout class="admin-shell min-h-screen" :class="{ 'is-rail': rail }">
            <FlashMessages />

            <!-- Desktop sidebar: full, or an icon rail. Below lg it's a Drawer instead. -->
            <Sider
                :width="rail ? 72 : 256"
                theme="light"
                :trigger="null"
                class="admin-sider !fixed inset-y-0 start-0 z-50 hidden overflow-hidden lg:!block"
                :class="{ 'is-rail': rail }"
            >
                <Link :href="route('admin.dashboard')" class="admin-brand" :aria-label="appName">
                    <Store class="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
                    <span v-if="!rail" class="truncate">{{ appName }}</span>
                </Link>

                <div class="overlay-scroll min-h-0 flex-1 overflow-y-auto">
                    <AdminNav :collapsed="rail" />
                </div>
            </Sider>

            <!-- Mobile navigation -->
            <Drawer
                :open="drawerOpen"
                :placement="isRtl ? 'right' : 'left'"
                :width="280"
                :body-style="{ padding: 0 }"
                :title="appName"
                root-class-name="admin-drawer"
                @close="drawerOpen = false"
            >
                <AdminNav @navigate="drawerOpen = false" />
            </Drawer>

            <Layout class="admin-main" :class="rail ? 'lg:ms-[72px]' : 'lg:ms-64'">
                <Header class="admin-header sticky top-0 z-30 flex items-center gap-2 sm:gap-3">
                    <Tooltip :title="isDesktop ? sidebarLabel : undefined">
                        <Button type="text" class="admin-top-link admin-icon-btn" :aria-label="sidebarLabel" @click="toggleSidebar">
                            <template #icon><component :is="sidebarIcon" class="h-5 w-5" /></template>
                        </Button>
                    </Tooltip>

                    <!-- Looks like a field, opens the palette: one place to find anything. -->
                    <button type="button" class="admin-search-trigger" :aria-label="t('admin.nav.search')" @click="paletteOpen = true">
                        <Search class="h-4 w-4 shrink-0" aria-hidden="true" />
                        <span class="admin-search-trigger-text">{{ t('admin.nav.search') }}</span>
                        <kbd class="admin-kbd admin-search-trigger-kbd">{{ shortcutLabel }}</kbd>
                    </button>

                    <slot name="header" />

                    <div class="flex-1" />

                    <Dropdown v-if="quickActions.length > 0" trigger="click" :placement="isRtl ? 'bottomLeft' : 'bottomRight'">
                        <Button class="admin-create-btn" :aria-label="t('admin.nav.quick_create')">
                            <template #icon><Plus class="h-[18px] w-[18px]" /></template>
                            <!-- Labels from xl only: at lg the sidebar leaves ~768px for the bar. -->
                            <span class="!hidden xl:!inline">{{ t('admin.nav.quick_create') }}</span>
                        </Button>
                        <template #overlay>
                            <Menu>
                                <!-- The whole row navigates (icon, padding, Enter), not just the text. -->
                                <MenuItem v-for="action in quickActions" :key="action.key" @click="router.visit(action.href)">
                                    <template #icon><component :is="action.icon" class="h-4 w-4" /></template>
                                    {{ action.label }}
                                </MenuItem>
                            </Menu>
                        </template>
                    </Dropdown>

                    <Link v-if="canUsePos" :href="route('admin.pos.register')">
                        <Button type="primary" class="admin-pos-btn" :aria-label="t('admin.nav.pos')">
                            <template #icon><ScanLine class="h-[18px] w-[18px]" /></template>
                            <span class="!hidden xl:!inline">{{ t('admin.nav.pos') }}</span>
                        </Button>
                    </Link>

                    <!-- Secondary controls: in the bar from md up, in the account menu below. -->
                    <div class="hidden items-center gap-1 md:flex">
                        <Tooltip :title="t('admin.nav.storefront')">
                            <Link :href="route('home')">
                                <Button type="text" class="admin-top-link admin-icon-btn" :aria-label="t('admin.nav.storefront')">
                                    <template #icon><Home class="h-[18px] w-[18px]" /></template>
                                </Button>
                            </Link>
                        </Tooltip>

                        <Tooltip :title="t('admin.nav.switch_language')">
                            <Button
                                type="text"
                                class="admin-top-link"
                                :aria-label="t('admin.palette.switch_language', { lang: otherLocaleLabel })"
                                @click="switchLocale(otherLocale)"
                            >
                                <template #icon><Languages class="h-[18px] w-[18px]" /></template>
                                <span class="!hidden xl:!inline">{{ otherLocaleLabel }}</span>
                            </Button>
                        </Tooltip>

                        <Tooltip :title="t('admin.nav.toggle_dark')">
                            <Button type="text" class="admin-top-link admin-icon-btn" :aria-label="t('admin.nav.toggle_dark')" @click="toggleDark">
                                <template #icon>
                                    <Moon v-if="appearance !== 'dark'" class="h-[18px] w-[18px]" />
                                    <Sun v-else class="h-[18px] w-[18px]" />
                                </template>
                            </Button>
                        </Tooltip>
                    </div>

                    <Dropdown trigger="click" :placement="isRtl ? 'bottomLeft' : 'bottomRight'">
                        <Button type="text" class="admin-top-link" :aria-label="user?.name">
                            <Avatar :size="30" class="admin-avatar uppercase">
                                {{ user?.name?.charAt(0) ?? '?' }}
                            </Avatar>
                            <span class="!hidden max-w-32 truncate xl:!block">{{ user?.name }}</span>
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
                                <template v-if="!isMd">
                                    <MenuDivider />
                                    <MenuItem key="store">
                                        <template #icon><Home class="h-4 w-4" /></template>
                                        <Link :href="route('home')" class="block">{{ t('admin.nav.storefront') }}</Link>
                                    </MenuItem>
                                    <MenuItem key="locale" @click="switchLocale(otherLocale)">
                                        <template #icon><Languages class="h-4 w-4" /></template>
                                        {{ otherLocaleLabel }}
                                    </MenuItem>
                                    <MenuItem key="theme" @click="toggleDark">
                                        <template #icon>
                                            <Moon v-if="appearance !== 'dark'" class="h-4 w-4" />
                                            <Sun v-else class="h-4 w-4" />
                                        </template>
                                        {{ t('admin.nav.toggle_dark') }}
                                    </MenuItem>
                                </template>
                                <MenuDivider />
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

            <CommandPalette v-model:open="paletteOpen" :commands="paletteCommands" />
        </Layout>
    </ConfigProvider>
</template>
