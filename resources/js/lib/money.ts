/**
 * The client-side twin of `App\Support\Money::format`.
 *
 * Server-rendered prices always arrive pre-formatted, so this exists only for
 * the handful of figures the browser computes itself — a purchase order's
 * running total while its lines are still being edited. Same shape as the PHP
 * helper: amount first, three fraction digits, symbol last, wrapped in an LTR
 * isolate so it keeps that order inside Arabic text.
 *
 * Grouping and digits come from `Intl.NumberFormat`; only the symbol placement
 * is ours, for the same reason it is in PHP — CLDR puts the code in front for
 * `en`, which is not how prices are read here.
 */
const SYMBOLS: Record<string, string> = { ar: 'د.ل' };

export function formatMoney(amount: number, locale: string, currency = 'LYD'): string {
    const figure = new Intl.NumberFormat(locale === 'ar' ? 'ar-LY-u-nu-latn' : 'en-US', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(Number.isFinite(amount) ? amount : 0);

    return `⁦${figure} ${SYMBOLS[locale] ?? currency}⁩`;
}
