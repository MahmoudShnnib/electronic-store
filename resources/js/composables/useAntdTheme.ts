import { isDark } from '@/composables/useAppearance';
import { theme } from 'ant-design-vue';
import { computed } from 'vue';

/**
 * Mirrors the app's theme onto an Ant Design theme config.
 *
 * AntD v4 styles components with CSS-in-JS, so they never see the `.dark` class
 * Tailwind toggles — the algorithm has to be swapped explicitly. It reads the
 * shared `isDark` ref rather than observing `documentElement`, which keeps the
 * token swap in the same tick as the class flip; going through the DOM meant
 * Tailwind repainted a frame or two before AntD caught up.
 *
 * `fontFamily: 'inherit'` is deliberate: AntD would otherwise impose its own
 * Latin stack and drop Tajawal, which is what makes Arabic and English render
 * as the same product.
 *
 * We do NOT import `ant-design-vue/dist/reset.css` anywhere — Tailwind's
 * preflight already normalizes, and AntD's reset re-declares `html`'s
 * font-family plus heading/paragraph margins, which fights it.
 */
export function useAntdTheme(token: Record<string, unknown> = {}) {
    return computed(() => ({
        algorithm: isDark.value ? theme.darkAlgorithm : theme.defaultAlgorithm,
        token: { fontFamily: 'inherit', ...token },
    }));
}
