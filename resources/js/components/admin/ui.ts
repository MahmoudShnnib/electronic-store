/** Shared Tailwind class strings for the admin (keeps templates terse and consistent). */

/**
 * Everything a form control needs except horizontal padding, so variants that
 * need their own (a search field with a leading icon) can compose instead of
 * re-declaring the whole box and drifting out of sync.
 *
 * Native widget chrome — number spinners, date pickers, the select arrow —
 * is themed globally in app.css. `focus:ring-2` matters: a ring colour with
 * no ring width renders nothing at all.
 */
const controlBase =
    'w-full rounded-md border border-gray-300 bg-white py-2 text-sm text-gray-900 shadow-sm transition-colors ' +
    'placeholder:text-gray-400 hover:border-gray-400 ' +
    'focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30 ' +
    'disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400 ' +
    'dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:shadow-none ' +
    'dark:placeholder:text-gray-500 dark:hover:border-gray-600 ' +
    'dark:focus:border-blue-500 dark:focus:ring-blue-500/40 ' +
    'dark:disabled:bg-gray-900/50 dark:disabled:text-gray-600';

/** Text inputs, textareas and selects. */
export const inputClass = `${controlBase} px-3`;

/** Search field with room for the leading magnifier icon. */
export const searchInputClass = `${controlBase} ps-9 pe-3`;

export const btnPrimary =
    'inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors ' +
    'hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 ' +
    'disabled:cursor-not-allowed disabled:opacity-50';

export const btnSecondary =
    'inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors ' +
    'hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40 ' +
    'disabled:cursor-not-allowed disabled:opacity-50 ' +
    'dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800';

export const btnDanger =
    'inline-flex items-center gap-1.5 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors ' +
    'hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ' +
    'disabled:cursor-not-allowed disabled:opacity-50';

export const btnGhost =
    'inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm font-medium text-gray-600 transition-colors ' +
    'hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40 ' +
    'dark:text-gray-300 dark:hover:bg-gray-800';

/** Checkboxes and radios; the tick colour comes from accent-color in app.css. */
export const checkboxClass =
    'h-4 w-4 rounded border-gray-300 transition-colors focus:ring-2 focus:ring-blue-500/30 dark:border-gray-600 dark:bg-gray-800';
