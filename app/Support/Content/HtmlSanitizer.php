<?php

declare(strict_types=1);

namespace App\Support\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Cleans the HTML a rich-text section stores. The SPA renders `body` as HTML,
 * so only a small set of formatting tags survives, every attribute is dropped
 * except a link's `href`, and that only for http(s), mailto, tel or relative
 * targets. Unknown tags are unwrapped (their text kept); script-like tags go
 * with their content.
 */
final class HtmlSanitizer
{
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a', 'hr'];

    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'form', 'textarea', 'select', 'head', 'title'];

    private const SAFE_HREF = '~^(https?://|mailto:|tel:|/|#)~i';

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if ($root === null) {
            return null;
        }

        self::walk($root);

        $clean = '';

        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        $clean = trim($clean);

        return $clean === '' ? null : $clean;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comments, processing instructions, CDATA.
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! in_array($tag, self::ALLOWED, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';

            while ($child->attributes->length > 0) {
                $child->removeAttribute($child->attributes->item(0)->nodeName);
            }

            if ($href !== '' && preg_match(self::SAFE_HREF, $href) === 1) {
                $child->setAttribute('href', $href);

                if (preg_match('~^https?://~i', $href) === 1) {
                    $child->setAttribute('rel', 'noopener nofollow');
                }
            }
        }
    }
}
