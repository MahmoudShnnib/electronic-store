<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Banknote, Landmark, Mail, Phone } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The storefront footer: four link columns → legal bar.
 *
 * Rebuilt 2026-10-10 to replace the gc-footer-v2 clone (particle canvas,
 * scanlines, grid overlay, a glow under every heading). The user found it
 * noisy; this one follows the theme tokens like the rest of the shop and
 * keeps the tech look to a single lit hairline along its top edge.
 *
 * No accordion on phones: three short lists sit two-up and are quicker to
 * scan than to open one by one.
 */

const { t, locale } = useI18n();
const page = usePage();
const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');
const year = new Date().getFullYear();

const contact = computed(
    () => (page.props.storeContact as { phone: string | null; email: string | null } | undefined) ?? { phone: null, email: null },
);

interface NavCategory {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
}

/** The biggest departments — same cached list the header's "All categories" uses. */
const categories = computed(() => ((page.props.navCategories as NavCategory[] | undefined) ?? []).slice(0, 5));

function categoryLabel(category: NavCategory): string {
    return locale.value === 'ar' && category.name_ar ? category.name_ar : category.name;
}

const accountLinks = computed(() => [
    { label: t('nav.account'), href: route('profile.edit') },
    { label: t('nav.orders'), href: route('account.orders.index') },
    { label: t('nav.wishlist'), href: route('wishlist.index') },
    { label: t('nav.cart'), href: route('cart.show') },
]);

const helpPages = [
    { key: 'footer.contact', page: 'contact' },
    { key: 'footer.about', page: 'about' },
    { key: 'footer.return_policy', page: 'return-policy' },
    { key: 'footer.privacy', page: 'privacy-policy' },
    { key: 'footer.terms', page: 'terms' },
];

// Icon artwork per network. The links themselves come from the dashboard
// (Settings → Social media); a network without one is not shown at all.
const socialIcons = [
    {
        key: 'facebook',
        label: 'Facebook',
        viewBox: '0 0 320 512',
        d: 'M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z',
    },
    {
        key: 'instagram',
        label: 'Instagram',
        viewBox: '0 0 448 512',
        d: 'M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z',
    },
    {
        key: 'youtube',
        label: 'YouTube',
        viewBox: '0 0 576 512',
        d: 'M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z',
    },
    {
        key: 'tiktok',
        label: 'TikTok',
        viewBox: '0 0 448 512',
        d: 'M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z',
    },
];

const socialLinks = computed(() => (page.props.socialLinks as Record<string, string> | undefined) ?? {});
const socials = computed(() =>
    socialIcons.filter((icon) => socialLinks.value[icon.key]).map((icon) => ({ ...icon, href: socialLinks.value[icon.key] })),
);
</script>

<template>
    <div class="store-footer">
        <div class="mx-auto max-w-7xl px-4">
            <!-- Links -->
            <div class="store-footer-grid">
                <div class="store-footer-brand">
                    <Link :href="route('home')" class="store-wordmark store-wordmark-lg inline-flex items-baseline gap-1">
                        <span class="store-wordmark-glyph">◆</span>
                        <span>{{ appName }}</span>
                    </Link>
                    <p class="mt-3 max-w-xs text-sm leading-relaxed" style="color: var(--st-ink-soft)">{{ t('footer.tagline') }}</p>

                    <ul v-if="contact.phone || contact.email" class="mt-5 space-y-2" :aria-label="t('footer.get_in_touch')">
                        <li v-if="contact.phone">
                            <a :href="`tel:${contact.phone}`" class="store-footer-contact">
                                <Phone class="h-4 w-4" />
                                <span dir="ltr">{{ contact.phone }}</span>
                            </a>
                        </li>
                        <li v-if="contact.email">
                            <a :href="`mailto:${contact.email}`" class="store-footer-contact">
                                <Mail class="h-4 w-4" />
                                <span dir="ltr">{{ contact.email }}</span>
                            </a>
                        </li>
                    </ul>

                    <div v-if="socials.length > 0" class="mt-5 flex gap-2">
                        <a
                            v-for="social in socials"
                            :key="social.key"
                            :href="social.href"
                            class="store-footer-social"
                            target="_blank"
                            rel="noopener"
                            :aria-label="social.label"
                            translate="no"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                :viewBox="social.viewBox"
                                width="16"
                                height="16"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path :d="social.d" />
                            </svg>
                        </a>
                    </div>
                </div>

                <nav :aria-label="t('footer.shop')">
                    <h3 class="store-footer-heading">{{ t('footer.shop') }}</h3>
                    <ul class="store-footer-links">
                        <li v-for="category in categories" :key="category.id">
                            <Link :href="route('catalog.category', category.slug)">{{ categoryLabel(category) }}</Link>
                        </li>
                        <li>
                            <Link :href="route('catalog.index')" class="store-footer-more">{{ t('footer.all_products') }}</Link>
                        </li>
                    </ul>
                </nav>

                <nav :aria-label="t('footer.your_account')">
                    <h3 class="store-footer-heading">{{ t('footer.your_account') }}</h3>
                    <ul class="store-footer-links">
                        <li v-for="link in accountLinks" :key="link.href">
                            <Link :href="link.href">{{ link.label }}</Link>
                        </li>
                    </ul>
                </nav>

                <nav :aria-label="t('footer.help')">
                    <h3 class="store-footer-heading">{{ t('footer.help') }}</h3>
                    <ul class="store-footer-links">
                        <li v-for="item in helpPages" :key="item.page">
                            <Link :href="route('pages.show', item.page)">{{ t(item.key) }}</Link>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- Legal bar: who, and how you can pay -->
            <div class="store-footer-bottom">
                <p>© {{ year }} {{ appName }} — {{ t('footer.rights') }}</p>

                <ul class="flex flex-wrap items-center gap-2" :aria-label="t('footer.payment_methods')">
                    <li class="store-footer-pay">
                        <Banknote class="h-3.5 w-3.5" />
                        {{ t('footer.cod') }}
                    </li>
                    <li class="store-footer-pay">
                        <Landmark class="h-3.5 w-3.5" />
                        {{ t('footer.bank_transfer') }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
