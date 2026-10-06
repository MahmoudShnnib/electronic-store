import { isDark } from '@/composables/useAppearance';
import { adminFontHref, adminPalette, adminScale } from '@/theme/adminTokens';
import { theme } from 'ant-design-vue';
import { computed, watch } from 'vue';

const kebab = (key: string) => key.replace(/[A-Z0-9]/g, (c) => `-${c.toLowerCase()}`);

function writeVars(vars: Record<string, string>): void {
    for (const [key, value] of Object.entries(vars)) {
        document.documentElement.style.setProperty(`--ad-${kebab(key)}`, value);
    }
}

/**
 * The admin theme, driven by `theme/adminTokens.ts`.
 *
 * Returns the AntD `ConfigProvider` theme and, as a side effect, mirrors every
 * token onto `<html>` as `--ad-*`. `<html>` rather than the shell element, so
 * AntD's portalled modals, drawers and dropdowns see them too. They are left in
 * place when the admin unmounts: only admin CSS reads `--ad-*`, so a visit to
 * the storefront afterwards is unaffected, and nothing has to count mounts
 * across the dashboard ⇄ register layout swap.
 *
 * Follows the same `isDark` ref `useAntdTheme` does, synchronously, so the
 * variables flip in the same tick as the `.dark` class and AntD's algorithm.
 */
export function useAdminTheme() {
    const colors = computed(() => (isDark.value ? adminPalette.dark : adminPalette.light));

    watch(
        colors,
        (c) =>
            writeVars({
                ...c,
                font: adminScale.font,
                radiusSm: `${adminScale.radiusSm}px`,
                radius: `${adminScale.radius}px`,
                radiusLg: `${adminScale.radiusLg}px`,
                sidebarWidth: `${adminScale.sidebarWidth}px`,
                topbarHeight: `${adminScale.topbarHeight}px`,
                pagePadding: `${adminScale.pagePadding}px`,
                pagePaddingMobile: `${adminScale.pagePaddingMobile}px`,
                gap: `${adminScale.gap}px`,
            }),
        { immediate: true, flush: 'sync' },
    );

    if (!document.getElementById('admin-font')) {
        const link = Object.assign(document.createElement('link'), { id: 'admin-font', rel: 'stylesheet', href: adminFontHref });
        document.head.appendChild(link);
    }

    return computed(() => {
        const c = colors.value;

        return {
            algorithm: isDark.value ? theme.darkAlgorithm : theme.defaultAlgorithm,
            token: {
                fontFamily: 'inherit',
                fontSize: adminScale.fontSize,
                borderRadius: adminScale.radius,
                borderRadiusLG: adminScale.radiusLg,
                borderRadiusSM: adminScale.radiusSm,
                colorPrimary: c.primary,
                colorInfo: c.primary,
                colorSuccess: c.success,
                colorWarning: c.warning,
                colorError: c.danger,
                colorLink: c.accent,
                colorLinkHover: c.accentHover,
                colorBgLayout: c.bg,
                colorBgContainer: c.card,
                colorBgElevated: c.elevated,
                colorBorder: c.line,
                colorBorderSecondary: c.line,
                colorSplit: c.lineSoft,
                colorText: c.text,
                colorTextHeading: c.text,
                colorTextSecondary: c.muted,
                colorTextDescription: c.muted,
                colorTextTertiary: c.label,
                colorTextPlaceholder: c.label,
                colorTextQuaternary: c.faint,
                colorFillAlter: c.head,
                controlItemBgHover: c.hover,
                controlItemBgActive: c.activeBg,
                boxShadow: c.shadowPop,
                boxShadowSecondary: c.shadowPop,
            },
            components: {
                Layout: { colorBgHeader: 'transparent', colorBgBody: c.bg },
            },
        };
    });
}

/** The current mode's palette, for code that needs raw colours (Chart.js datasets). */
export function useAdminColors() {
    return computed(() => (isDark.value ? adminPalette.dark : adminPalette.light));
}
