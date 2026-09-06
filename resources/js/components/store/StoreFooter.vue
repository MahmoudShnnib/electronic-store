<script setup lang="ts">
import { useMediaQuery } from '@/composables/useMediaQuery';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

// Structural clone of the reference site's gc-footer-v2: particle canvas,
// scanlines, grid overlay, glowing logo, accordion columns on mobile, a
// newsletter form with a glow button, payment badges and the decor bar.
// Content and links are this store's own.

const { t } = useI18n();
const page = usePage();
const appName = computed(() => (page.props.name as string) ?? 'Electronic Store');
const year = new Date().getFullYear();

const footerPages = [
    { key: 'footer.contact', page: 'contact' },
    { key: 'footer.about', page: 'about' },
    { key: 'footer.return_policy', page: 'return-policy' },
    { key: 'footer.privacy', page: 'privacy-policy' },
    { key: 'footer.terms', page: 'terms' },
];

// ponytail: placeholder hrefs until real profiles are configured — swap in one place.
const socials = [
    {
        key: 'facebook',
        label: 'Facebook',
        href: '#',
        viewBox: '0 0 320 512',
        d: 'M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z',
    },
    {
        key: 'instagram',
        label: 'Instagram',
        href: '#',
        viewBox: '0 0 448 512',
        d: 'M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z',
    },
    {
        key: 'youtube',
        label: 'YouTube',
        href: '#',
        viewBox: '0 0 576 512',
        d: 'M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z',
    },
    {
        key: 'tiktok',
        label: 'TikTok',
        href: '#',
        viewBox: '0 0 448 512',
        d: 'M448,209.91a210.06,210.06,0,0,1-122.77-39.25V349.38A162.55,162.55,0,1,1,185,188.31V278.2a74.62,74.62,0,1,0,52.23,71.18V0l88,0a121.18,121.18,0,0,0,1.86,22.17h0A122.18,122.18,0,0,0,381,102.39a121.43,121.43,0,0,0,67,20.14Z',
    },
];

/**
 * Mobile accordion state; desktop ignores it via CSS, which is why the trigger
 * only reports `aria-expanded` below lg — on desktop the columns are always open
 * and a collapsed state would be a lie to a screen reader.
 */
const isMobile = useMediaQuery('(max-width: 1023px)');
const openSections = ref<Record<string, boolean>>({ 'quick-links': true, 'customer-service': true });

function expandedState(key: string): boolean | undefined {
    return isMobile.value ? openSections.value[key] : undefined;
}

function toggleSection(key: string) {
    openSections.value[key] = !openSections.value[key];
}

const newsletter = useForm({ email: '' });

function subscribe() {
    newsletter.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => newsletter.reset(),
    });
}

// Particle field — green embers drifting up the footer, like the reference
// site's canvas. Skipped entirely under prefers-reduced-motion.
const particleCanvas = ref<HTMLCanvasElement>();
let raf = 0;
let onResize: (() => void) | null = null;

onMounted(() => {
    const canvas = particleCanvas.value;
    if (!canvas || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const context = canvas.getContext('2d');
    if (!context) return;

    interface Particle {
        x: number;
        y: number;
        radius: number;
        vx: number;
        vy: number;
        alpha: number;
        pulse: number;
    }

    let particles: Particle[] = [];

    const seed = () => {
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        const count = Math.min(70, Math.floor(canvas.width / 22));
        particles = Array.from({ length: count }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            radius: 0.6 + Math.random() * 1.8,
            vx: (Math.random() - 0.5) * 0.15,
            vy: -(0.08 + Math.random() * 0.28),
            alpha: 0.2 + Math.random() * 0.6,
            pulse: Math.random() * Math.PI * 2,
        }));
    };

    const tick = () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        for (const particle of particles) {
            particle.x += particle.vx;
            particle.y += particle.vy;
            particle.pulse += 0.02;
            if (particle.y < -4) particle.y = canvas.height + 4;
            if (particle.x < -4) particle.x = canvas.width + 4;
            if (particle.x > canvas.width + 4) particle.x = -4;

            const glow = particle.alpha * (0.65 + 0.35 * Math.sin(particle.pulse));
            context.beginPath();
            context.shadowBlur = 8;
            context.shadowColor = 'rgba(74, 222, 155, 0.9)';
            context.fillStyle = `rgba(74, 222, 155, ${glow})`;
            context.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
            context.fill();
        }
        raf = requestAnimationFrame(tick);
    };

    seed();
    tick();
    onResize = seed;
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(raf);
    if (onResize) window.removeEventListener('resize', onResize);
});
</script>

<template>
    <div class="gc-footer-v2">
        <!-- Animated Background -->
        <canvas ref="particleCanvas" id="gc-particles" aria-hidden="true"></canvas>
        <div class="gc-scanlines"></div>
        <div class="gc-grid-overlay"></div>

        <div class="gc-footer-container">
            <!-- Main Footer Content -->
            <div class="gc-footer-main">
                <div class="gc-footer-row">
                    <!-- Logo Column with Glitch Effect -->
                    <div class="gc-footer-col gc-col-logo">
                        <div class="gc-logo-wrapper">
                            <Link :href="route('home')" class="store-wordmark store-wordmark-lg gc-logo flex items-baseline gap-1">
                                <span class="store-wordmark-glyph">◆</span>
                                <span>{{ appName }}</span>
                            </Link>
                            <div class="gc-logo-glow"></div>
                        </div>
                        <p class="gc-brand-text">{{ t('footer.tagline') }}</p>
                        <div class="gc-social-icons">
                            <a
                                v-for="social in socials"
                                :key="social.key"
                                :href="social.href"
                                class="gc-social-link"
                                target="_blank"
                                rel="noopener"
                                :aria-label="social.label"
                                translate="no"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" :viewBox="social.viewBox" fill="currentColor" width="22" height="22" aria-hidden="true">
                                    <path :d="social.d" fill="currentColor"></path>
                                </svg>
                                <span class="gc-social-glow"></span>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="gc-footer-col gc-accordion-col">
                        <!-- The heading stays an <h4> for the outline; the control inside it is a
                             real <button>, so the accordion is reachable by keyboard and states
                             its own expanded state instead of being a click-only <h4>. -->
                        <h4 class="gc-footer-heading gc-accordion-heading" :class="{ active: openSections['quick-links'] }">
                            <button
                                type="button"
                                class="gc-accordion-trigger"
                                :aria-expanded="expandedState('quick-links')"
                                aria-controls="gc-footer-quick-links"
                                @click="toggleSection('quick-links')"
                            >
                                <span class="gc-heading-text">{{ t('footer.quick_links') }}</span>
                                <span class="gc-accordion-icon">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="16"
                                        height="16"
                                        fill="currentColor"
                                        viewBox="0 0 16 16"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"
                                        ></path>
                                    </svg>
                                </span>
                            </button>
                            <span class="gc-heading-line"></span>
                        </h4>
                        <ul id="gc-footer-quick-links" class="gc-footer-links gc-accordion-content" :class="{ open: openSections['quick-links'] }">
                            <li><Link :href="route('profile.edit')">{{ t('nav.account') }}</Link></li>
                            <li><Link :href="route('wishlist.index')">{{ t('nav.wishlist') }}</Link></li>
                            <li><Link :href="route('cart.show')">{{ t('nav.cart') }}</Link></li>
                            <li><Link :href="route('account.orders.index')">{{ t('nav.orders') }}</Link></li>
                        </ul>
                    </div>

                    <!-- Support -->
                    <div class="gc-footer-col gc-accordion-col">
                        <h4 class="gc-footer-heading gc-accordion-heading" :class="{ active: openSections['customer-service'] }">
                            <button
                                type="button"
                                class="gc-accordion-trigger"
                                :aria-expanded="expandedState('customer-service')"
                                aria-controls="gc-footer-customer-service"
                                @click="toggleSection('customer-service')"
                            >
                                <span class="gc-heading-text">{{ t('footer.customer_service') }}</span>
                                <span class="gc-accordion-icon">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="16"
                                        height="16"
                                        fill="currentColor"
                                        viewBox="0 0 16 16"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"
                                        ></path>
                                    </svg>
                                </span>
                            </button>
                            <span class="gc-heading-line"></span>
                        </h4>
                        <ul id="gc-footer-customer-service" class="gc-footer-links gc-accordion-content" :class="{ open: openSections['customer-service'] }">
                            <li v-for="item in footerPages" :key="item.page">
                                <Link :href="route('pages.show', item.page)">{{ t(item.key) }}</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Newsletter -->
                    <div class="gc-footer-col gc-col-newsletter">
                        <h4 class="gc-footer-heading">
                            <span class="gc-heading-text">{{ t('footer.newsletter') }}</span>
                            <span class="gc-heading-line"></span>
                        </h4>
                        <p class="gc-newsletter-text">{{ t('footer.newsletter_hint') }}</p>
                        <form class="gc-newsletter-form" @submit.prevent="subscribe">
                            <div class="gc-input-wrapper">
                                <input
                                    id="gc-newsletter-email"
                                    v-model="newsletter.email"
                                    type="email"
                                    name="email"
                                    autocomplete="email"
                                    inputmode="email"
                                    spellcheck="false"
                                    :placeholder="t('footer.email_placeholder')"
                                    required
                                    class="gc-email-input"
                                    :aria-label="t('footer.newsletter')"
                                    :aria-invalid="newsletter.errors.email ? 'true' : undefined"
                                    :aria-describedby="newsletter.errors.email ? 'gc-newsletter-error' : undefined"
                                />
                                <button type="submit" class="gc-submit-btn" :disabled="newsletter.processing">
                                    <span>{{ t('footer.subscribe') }}</span>
                                    <span class="gc-btn-glow"></span>
                                </button>
                            </div>
                        </form>
                        <!-- The live region itself is always mounted; only the message inside
                             it comes and goes, which is what makes it announce reliably. -->
                        <div aria-live="polite">
                            <p v-if="newsletter.errors.email" id="gc-newsletter-error" class="gc-newsletter-error">
                                {{ newsletter.errors.email }}
                            </p>
                        </div>
                        <div class="gc-payment-badges">
                            <div class="gc-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 21h18" /><path d="M3 10h18" /><path d="M5 6l7-3 7 3" /><path d="M4 10v11" /><path d="M20 10v11" /><path d="M8 14v3" /><path d="M12 14v3" /><path d="M16 14v3" />
                                </svg>
                                <span>{{ t('footer.bank_transfer') }}</span>
                            </div>
                            <div class="gc-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2" /><path d="M15 18H9" /><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14" /><circle cx="17" cy="18" r="2" /><circle cx="7" cy="18" r="2" />
                                </svg>
                                <span>{{ t('footer.cod') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Copyright Bar -->
            <div class="gc-footer-bottom">
                <div class="gc-copyright">
                    <span>© {{ year }} {{ appName }} — {{ t('footer.rights') }}</span>
                </div>
                <div class="gc-footer-decor">
                    <span class="gc-decor-line"></span>
                    <span class="gc-decor-dot"></span>
                    <span class="gc-decor-line"></span>
                </div>
            </div>
        </div>
    </div>
</template>
