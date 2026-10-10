import { nextTick, onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

/**
 * Single source of truth for the resolved theme.
 *
 * `useAntdTheme` reads this ref directly rather than watching the `.dark` class,
 * so AntD's token swap is scheduled in the same tick the class flips instead of
 * a DOM round-trip later. `updateTheme` is the only writer — anything that wants
 * to change the theme goes through it, not through `classList` by hand.
 */
// Seeded from the class the blade bootstrap sets before Vue mounts, so the very
// first AntD render already has the right algorithm instead of flashing light.
export const isDark = ref(document.documentElement.classList.contains('dark'));

export function updateTheme(value: Appearance) {
    const dark = value === 'system' ? window.matchMedia('(prefers-color-scheme: dark)').matches : value === 'dark';

    document.documentElement.classList.toggle('dark', dark);
    isDark.value = dark;

    // Keep the browser chrome on the same ground colour the page paints
    // (--st-ground); the blade bootstrap sets the same pair before first paint.
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#05070c' : '#eef2f7');
}

const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

const handleSystemThemeChange = () => {
    const currentAppearance = localStorage.getItem('appearance') as Appearance | null;
    updateTheme(currentAppearance || 'dark');
};

export function initializeTheme() {
    // Initialize theme from saved preference; the store defaults to dark.
    const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
    updateTheme(savedAppearance || 'dark');

    // Set up system theme change listener...
    mediaQuery.addEventListener('change', handleSystemThemeChange);
}

/** Not in every browser's lib.dom yet, and we only need it if it is there. */
type ViewTransition = { ready?: Promise<unknown>; finished?: Promise<unknown>; updateCallbackDone?: Promise<unknown> };
type WithViewTransition = { startViewTransition?: (callback: () => unknown) => ViewTransition };

export function useAppearance() {
    const appearance = ref<Appearance>('dark');

    onMounted(() => {
        initializeTheme();

        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);

        const startViewTransition = (document as unknown as WithViewTransition).startViewTransition;

        // Tailwind's `.dark` class takes effect on the next frame; AntD has to
        // regenerate its CSS-in-JS from the new tokens first, which lands a
        // couple of frames later. Repainting between the two leaves the page
        // half-light and half-dark — the "block by block" swap. A view
        // transition holds the old frame until both systems have caught up and
        // then cross-fades once, so none of that is ever on screen.
        if (!startViewTransition || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            updateTheme(value);

            return;
        }

        // `nextTick` only — rendering is paused for the duration of the
        // callback, so awaiting a frame here deadlocks until the transition
        // times out. Vue's flush is where AntD re-renders and injects its
        // styles, which is exactly what has to land before the new snapshot.
        const transition = startViewTransition.call(document, async () => {
            updateTheme(value);
            await nextTick();
        });

        // Toggling again before the first fade ends skips it, which rejects all
        // three promises. That is the expected outcome, not an error, so it is
        // swallowed rather than left to surface as an unhandled rejection.
        transition?.ready?.catch(() => {});
        transition?.finished?.catch(() => {});
        transition?.updateCallbackDone?.catch(() => {});
    }

    return {
        appearance,
        updateAppearance,
    };
}
