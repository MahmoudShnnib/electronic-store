import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

/**
 * Hides chrome (header, tab bar) while the user scrolls down and reveals it
 * again on any upward scroll. Always visible near the top of the page; small
 * jitters below the given delta are ignored so the bar doesn't flicker during
 * momentum scrolling.
 */
export function useHideOnScroll(topThreshold = 80, delta = 8): Ref<boolean> {
    const hidden = ref(false);
    let lastY = 0;

    function onScroll() {
        const y = window.scrollY;

        if (y < topThreshold) {
            hidden.value = false;
        } else if (Math.abs(y - lastY) > delta) {
            hidden.value = y > lastY;
        }

        lastY = y;
    }

    onMounted(() => {
        lastY = window.scrollY;
        window.addEventListener('scroll', onScroll, { passive: true });
    });

    onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));

    return hidden;
}
