import { onMounted, onUnmounted } from 'vue';

/**
 * Puts the currency on the reading-order side of the amount in the admin.
 *
 * `App\Support\Money::format` wraps "amount symbol" in one LTR isolate
 * ("⁦110.000 د.ل⁩"), so inside Arabic text the symbol always lands on the
 * right of the figure. The reference design reads "د.ل 110.000" in Arabic —
 * amount first, symbol after it in the paragraph's direction. Closing the
 * isolate after the amount instead ("⁦110.000⁩ د.ل") does exactly that and
 * changes nothing in English, where both orders render "110.000 LYD".
 *
 * Done on the rendered text rather than in `Money` so the storefront and the
 * printed receipt (`.receipt`, rasterised as-is) keep their current output.
 * Vue patches text nodes by comparing vnodes, not the DOM, so the moved
 * isolate survives re-renders of unchanged text and is re-applied to changed
 * text through the observer.
 */
const MONEY = /⁦([^⁦⁩]*\d)\s([^⁦⁩\d]+)⁩/g;

function fixText(node: Node): void {
    const text = node.nodeValue ?? '';

    if (!text.includes('⁦') || node.parentElement?.closest('.receipt')) {
        return;
    }

    const next = text.replace(MONEY, '⁦$1⁩ $2');

    if (next !== text) {
        node.nodeValue = next;
    }
}

function fixTree(root: Node): void {
    if (root.nodeType === Node.TEXT_NODE) {
        fixText(root);

        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);

    while (walker.nextNode()) {
        fixText(walker.currentNode);
    }
}

export function useAdminMoneyBidi(): void {
    let observer: MutationObserver | null = null;

    onMounted(() => {
        fixTree(document.body);

        observer = new MutationObserver((records) => {
            for (const record of records) {
                if (record.type === 'characterData') {
                    fixText(record.target);
                } else {
                    record.addedNodes.forEach(fixTree);
                }
            }
        });

        // Body, not the shell: AntD portals modals, dropdowns and tooltips there.
        observer.observe(document.body, { subtree: true, childList: true, characterData: true });
    });

    onUnmounted(() => observer?.disconnect());
}
