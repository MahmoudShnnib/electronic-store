import { isDark } from '@/composables/useAppearance';
import { theme } from 'ant-design-vue';
import { computed } from 'vue';

/**
 * The storefront's Ant Design theme — the "dark tech" direction.
 *
 * The accent is an electric cyan, and it changes shade with the mode rather
 * than staying one hex: the neon `#22D3EE` that glows on the blue-black page
 * washes out on white, so light mode takes the deeper `#0891B2`. These must
 * match `--st-accent` in the storefront palette block of `app.css`, which is
 * what the bespoke chrome (header pill, filter chips, tab bar) paints with.
 *
 * A neon fill is too bright for a white label, so in dark mode primary buttons
 * carry dark ink. AntD v4 has no per-button token for that — the label reads
 * `colorTextLightSolid`, which tooltips and badges share — so it is a scoped
 * rule in app.css (`.ant-btn-primary` under the storefront palette block).
 *
 * `colorBgBase` pulls AntD's own surfaces (inputs, selects, dropdowns, modals)
 * onto the same blue-black family as the page; left to the dark algorithm they
 * come out a neutral grey that reads as a different product.
 *
 * Shared by `StoreLayout` and the auth screens, which sit outside it but are
 * still the same product to a customer signing in.
 */
export function useStoreTheme() {
    return computed(() => {
        const dark = isDark.value;
        const accent = dark ? '#22D3EE' : '#0891B2';

        return {
            algorithm: dark ? theme.darkAlgorithm : theme.defaultAlgorithm,
            token: {
                fontFamily: 'inherit',
                colorPrimary: accent,
                colorLink: accent,
                colorInfo: accent,
                borderRadius: 10,
                ...(dark ? { colorBgBase: '#070a10', colorBorder: '#1f2a3a', colorBorderSecondary: '#162030' } : {}),
            },
        };
    });
}
