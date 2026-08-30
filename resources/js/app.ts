import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ConfigProvider } from 'radix-vue';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import { applyDocumentLocale, createAppI18n } from './i18n';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Electronic Store';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const locale = ((props.initialPage.props as Record<string, unknown>).locale as string) ?? 'en';
        const i18n = createAppI18n(locale);

        applyDocumentLocale(locale);

        // Radix primitives default to `dir: 'ltr'` and ignore <html dir>, so in
        // Arabic every select, dropdown and popover laid itself out left-to-right
        // inside an otherwise right-to-left page. ConfigProvider is the one place
        // that fixes all of them. Locale changes hard-reload, so this is static.
        const dir = locale === 'ar' ? 'rtl' : 'ltr';

        createApp({ render: () => h(ConfigProvider, { dir }, () => h(App, props)) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
