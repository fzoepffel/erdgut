<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

/**
 * Allowlist sanitizer for the WYSIWYG mercurial body. The editor is admin-only
 * and produces a small, known tag set, but we still strip scripts, styles,
 * event handlers and unsafe URLs so a paste can never inject active content
 * into the email HTML.
 */
final class MercurialHtml
{
    /** Formatting tags kept as-is (attributes still filtered). */
    private const ALLOWED = [
        'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'del', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'pre', 'code',
    ];

    /** Tags removed together with their content. */
    private const FORBIDDEN = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'link', 'meta', 'base', 'svg', 'math',
    ];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__mercurial_root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__mercurial_root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function clean(DOMElement $node): void
    {
        $child = $node->firstChild;
        while ($child !== null) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, self::FORBIDDEN, true)) {
                    $node->removeChild($child);
                } elseif (! in_array($tag, self::ALLOWED, true)) {
                    // Unknown but not dangerous: unwrap, keep the text/children
                    // and continue cleaning them from where they landed.
                    $first = $child->firstChild;
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    $next = $first ?? $next;
                } else {
                    self::stripAttributes($child, $tag);
                    self::clean($child);
                }
            }

            $child = $next;
        }
    }

    private static function stripAttributes(DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $keep = $tag === 'a'
                && in_array($name, ['href', 'title'], true)
                && ($name !== 'href' || self::safeUrl($attr->value));

            if (! $keep) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function safeUrl(string $url): bool
    {
        $u = trim($url);
        if ($u === '') {
            return false;
        }
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $u)) {
            return false;
        }

        // http(s), mailto, tel, anchor, absolute or relative path — but reject
        // any other explicit scheme.
        if (preg_match('#^(https?:|mailto:|tel:|/|\#|\.)#i', $u)) {
            return true;
        }

        return ! preg_match('#^[a-z][a-z0-9+.\-]*:#i', $u);
    }
}
