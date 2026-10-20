<script setup lang="ts">
/**
 * The dashboard's navigation, shared by the desktop sidebar and the mobile
 * drawer. The links, their grouping and their permissions live in
 * `useAdminNav` (the command palette reads the same list).
 *
 * Two shapes:
 * - expanded: the dashboard link, collapsible groups, and the team/settings
 *   block pinned to the bottom. Group open/closed state persists per browser;
 *   the group holding the current page always opens so the active link is
 *   never hidden.
 * - rail (`collapsed`): icons only. A group's links open in a flyout beside it,
 *   on hover or click, placed on the inline-end side in either direction.
 */
import { isGroup, useAdminNav, useAttentionCounts, type NavGroup, type NavItem, type NavLink } from '@/composables/useAdminNav';
import { Link, usePage } from '@inertiajs/vue3';
import { Popover, Tooltip } from 'ant-design-vue';
import { ChevronDown } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ collapsed?: boolean }>();

const emit = defineEmits<{ (e: 'navigate'): void }>();

const { t } = useI18n();
const page = usePage();

const { blocks, activePath, isActive } = useAdminNav();
const counts = useAttentionCounts();

const badgeOf = (link: NavLink) => (link.badge ? (counts.value[link.badge] ?? 0) : 0);
const groupBadge = (group: NavGroup) => group.links.reduce((sum, link) => sum + badgeOf(link), 0);
const groupActive = (group: NavGroup) => group.links.some(isActive);

/** Large counts would push the label; the exact figure is on the dashboard. */
const short = (n: number) => (n > 99 ? '99+' : String(n));

/** The flyout opens towards the content, so it follows the document direction. */
const flyoutPlacement = computed(() => ((page.props.locale as string) === 'ar' ? 'leftTop' : 'rightTop'));
const tooltipPlacement = computed(() => ((page.props.locale as string) === 'ar' ? 'left' : 'right'));

const STORAGE_KEY = 'adminNavGroups.v2';

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
            if (isGroup(item) && groupActive(item)) {
                open[item.key] = true;
            }
        }
    },
    { immediate: true },
);

const lastIndex = computed(() => blocks.value.length - 1);

const itemKey = (item: NavItem) => item.key;
</script>

<template>
    <nav class="admin-nav" :class="{ 'is-rail': props.collapsed }" :aria-label="t('admin.nav.main')">
        <template v-for="(block, index) in blocks" :key="index">
            <div v-if="index > 0 && index < lastIndex && block.length > 0" class="admin-nav-sep" />

            <div v-if="block.length > 0" class="admin-nav-list" :class="{ 'admin-nav-bottom': index === lastIndex }">
                <template v-for="item in block" :key="itemKey(item)">
                    <!-- Rail: icon-only, groups open as a flyout -->
                    <template v-if="props.collapsed">
                        <Popover
                            v-if="isGroup(item)"
                            :placement="flyoutPlacement"
                            :trigger="['hover', 'click']"
                            :mouse-enter-delay="0.05"
                            overlay-class-name="admin-nav-flyout"
                            :arrow="false"
                        >
                            <template #content>
                                <p class="admin-nav-flyout-title">{{ item.label }}</p>
                                <Link
                                    v-for="link in item.links"
                                    :key="link.key"
                                    :href="link.href"
                                    class="admin-nav-link"
                                    :aria-current="isActive(link) ? 'page' : undefined"
                                    @click="emit('navigate')"
                                >
                                    <component :is="link.icon" aria-hidden="true" />
                                    <span class="admin-nav-label">{{ link.label }}</span>
                                    <span v-if="badgeOf(link) > 0" class="admin-badge">{{ short(badgeOf(link)) }}</span>
                                </Link>
                            </template>
                            <button
                                type="button"
                                class="admin-rail-item"
                                :class="{ 'is-active': groupActive(item) }"
                                :aria-label="item.label"
                                aria-haspopup="true"
                            >
                                <component :is="item.icon" aria-hidden="true" />
                                <span v-if="groupBadge(item) > 0" class="admin-rail-dot" aria-hidden="true" />
                            </button>
                        </Popover>

                        <Tooltip v-else :title="item.label" :placement="tooltipPlacement">
                            <Link
                                :href="item.href"
                                class="admin-rail-item"
                                :class="{ 'is-active': isActive(item) }"
                                :aria-label="item.label"
                                :aria-current="isActive(item) ? 'page' : undefined"
                                @click="emit('navigate')"
                            >
                                <component :is="item.icon" aria-hidden="true" />
                                <span v-if="badgeOf(item) > 0" class="admin-rail-dot" aria-hidden="true" />
                            </Link>
                        </Tooltip>
                    </template>

                    <!-- Expanded -->
                    <template v-else-if="isGroup(item)">
                        <button
                            type="button"
                            class="admin-nav-group"
                            :aria-expanded="open[item.key] ? 'true' : 'false'"
                            :aria-controls="`admin-nav-${item.key}`"
                            @click="toggle(item.key)"
                        >
                            <component :is="item.icon" aria-hidden="true" />
                            <span class="admin-nav-label">{{ item.label }}</span>
                            <!-- A closed group still says something inside it is waiting. -->
                            <span v-if="!open[item.key] && groupBadge(item) > 0" class="admin-badge">{{ short(groupBadge(item)) }}</span>
                            <ChevronDown class="admin-nav-chev" aria-hidden="true" />
                        </button>
                        <div v-show="open[item.key]" :id="`admin-nav-${item.key}`" class="admin-nav-sub">
                            <Link
                                v-for="link in item.links"
                                :key="link.key"
                                :href="link.href"
                                class="admin-nav-link"
                                :aria-current="isActive(link) ? 'page' : undefined"
                                @click="emit('navigate')"
                            >
                                <span class="admin-nav-label">{{ link.label }}</span>
                                <span v-if="badgeOf(link) > 0" class="admin-badge">{{ short(badgeOf(link)) }}</span>
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
                        <span class="admin-nav-label">{{ item.label }}</span>
                        <span v-if="badgeOf(item) > 0" class="admin-badge">{{ short(badgeOf(item)) }}</span>
                    </Link>
                </template>
            </div>
        </template>
    </nav>
</template>
