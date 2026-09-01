import { useAntdTheme } from '@/composables/useAntdTheme';

/**
 * The storefront's Ant Design theme, distinct from the dashboard's blue.
 *
 * Green is already the site's commerce colour — sale flags, "buy now", the
 * discount line on a product card — so making it the seed puts every primary
 * button, focus ring, active tab and checked control on the same accent instead
 * of leaving each call site to hardcode one. The larger radius is the other
 * half of the difference: the dashboard is 6px and businesslike, the shop is
 * 10px and softer.
 *
 * Shared by `StoreLayout` and the auth screens, which sit outside it but are
 * still the same product to a customer signing in.
 */
export function useStoreTheme() {
    return useAntdTheme({
        colorPrimary: '#0F9D58',
        colorLink: '#0F9D58',
        borderRadius: 10,
    });
}
