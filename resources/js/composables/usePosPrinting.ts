import { ref } from 'vue';

/**
 * Rasterising a slip and getting it to the counter printer.
 *
 * Shared by the register (which prints the sale it just rang up, from an
 * off-screen copy of the paper), the receipt page (which reprints), and the
 * order page's delivery slip — one head, three kinds of paper. None should own
 * this: the rasterising is fiddly and getting it wrong is invisible until a
 * customer is handed a blank strip.
 */
export function usePosPrinting(dotWidth: () => number) {
    const sending = ref(false);

    /** Laravel's own cookie, which is why this can be a bare fetch and not axios. */
    function csrfToken(): string {
        const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));

        return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
    }

    async function rasterise(element: HTMLElement): Promise<Blob> {
        // Pulled in only here: it is 65 KB gzipped, and a shop printing through
        // the browser dialog should never pay for it.
        const { default: html2canvas } = await import('html2canvas-pro');

        // The head is 576 dots across 80mm where CSS calls the same 80mm ~302px,
        // so the canvas is scaled to the printer's dots rather than the screen's.
        // Any less and the 10px SKU line thresholds into mush.
        const canvas = await html2canvas(element, {
            backgroundColor: '#ffffff',
            scale: dotWidth() / element.offsetWidth,
            logging: false,
        });

        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('The receipt could not be rasterised.'))), 'image/png');
        });
    }

    /**
     * Never throws. Returns whether the slip reached the printer, so the caller
     * can decide what to offer instead — by the time this runs the money is in
     * the drawer and the sale is not in question.
     */
    async function printSlip(element: HTMLElement | null, orderId: number, routeName = 'admin.pos.receipt.print'): Promise<boolean> {
        if (element === null || sending.value) {
            return false;
        }

        sending.value = true;

        try {
            // Web fonts settle after mount; rasterising before they land prints
            // the whole slip in the fallback face.
            await document.fonts?.ready;

            const body = new FormData();
            body.append('image', await rasterise(element), `receipt-${orderId}.png`);

            const response = await fetch(route(routeName, orderId), {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrfToken() },
            });

            if (!response.ok) {
                throw new Error(`The printer answered ${response.status}.`);
            }

            return true;
        } catch (error) {
            console.error(error);

            return false;
        } finally {
            sending.value = false;
        }
    }

    return { sending, printSlip };
}
