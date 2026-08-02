import { router } from '@inertiajs/vue3';
import { createI18n } from 'vue-i18n';
import ar from './ar.json';
import en from './en.json';

export type AppLocale = 'en' | 'ar';

export function createAppI18n(locale: string) {
    return createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'en',
        messages: { en, ar },
        missingWarn: false,
        fallbackWarn: false,
    });
}

/** Keep <html lang/dir> in sync so Tailwind's rtl: variants and native RTL work. */
export function applyDocumentLocale(locale: string): void {
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
}

/** Persist the choice server-side, then reload shared props. */
export function switchLocale(locale: AppLocale): void {
    router.post(
        '/locale',
        { locale },
        {
            preserveScroll: true,
            onSuccess: () => {
                applyDocumentLocale(locale);
                window.location.reload();
            },
        },
    );
}
