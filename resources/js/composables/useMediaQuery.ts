import { onBeforeUnmount, ref, type Ref } from 'vue';

/**
 * Reactive `matchMedia`, for the handful of places where a CSS breakpoint also
 * has to change behaviour rather than only appearance — an accordion that is
 * only an accordion on small screens, an `autofocus` that should not fire on a
 * phone keyboard.
 *
 * Seeded synchronously so the first render already agrees with the stylesheet.
 */
export function useMediaQuery(query: string): Ref<boolean> {
    const list = window.matchMedia(query);
    const matches = ref(list.matches);

    const onChange = (event: MediaQueryListEvent) => (matches.value = event.matches);
    list.addEventListener('change', onChange);

    onBeforeUnmount(() => list.removeEventListener('change', onChange));

    return matches;
}
