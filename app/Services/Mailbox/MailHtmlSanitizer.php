<?php
namespace App\Services\Mailbox;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitizer for HTML of inbound e-mails (rendered inside the admin, so XSS = account takeover).
 * Unknown elements are unwrapped, dangerous ones removed with their content, every attribute not
 * listed is dropped. Remote images are blocked unless explicitly allowed (tracking pixels).
 */
final class MailHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'center', 'code', 'dd', 'del', 'div', 'dl', 'dt', 'em',
        'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'ol', 'p', 'pre', 'q', 's', 'small',
        'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template', 'head', 'title',
        'audio', 'video', 'source', 'track', 'canvas', 'dialog', 'portal',
    ];
    private const GLOBAL_ATTRS = ['title', 'dir', 'lang', 'align', 'valign', 'width', 'height', 'colspan', 'rowspan', 'color', 'bgcolor', 'size', 'face', 'border', 'cellpadding', 'cellspacing'];
    private const STYLE_PROPERTIES = [
        'color', 'background-color', 'font-family', 'font-size', 'font-style', 'font-weight', 'text-align', 'text-decoration',
        'line-height', 'vertical-align', 'white-space', 'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
        'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right', 'border', 'border-top', 'border-bottom',
        'border-left', 'border-right', 'border-color', 'border-style', 'border-width', 'border-collapse', 'width', 'max-width',
        'height', 'list-style-type',
    ];

    /**
     * @param array<string,string> $cidMap content-id (without <>) => URL of the stored attachment
     */
    public static function sanitize(string $html, array $cidMap = [], bool $allowRemoteImages = false): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // Hard cap: a hostile 50 MB document must not exhaust memory in DOM construction.
        if (strlen($html) > 2_000_000) {
            $html = substr($html, 0, 2_000_000);
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body instanceof DOMElement) {
            return '';
        }
        self::cleanChildren($body, $cidMap, $allowRemoteImages, 0);

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= (string) $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function cleanChildren(DOMNode $parent, array $cidMap, bool $allowRemote, int $depth): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                self::cleanElement($parent, $child, $cidMap, $allowRemote, $depth);
            } elseif ($child->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($child); // comments, CDATA, processing instructions
            }
        }
    }

    private static function cleanElement(DOMNode $parent, DOMElement $el, array $cidMap, bool $allowRemote, int $depth): void
    {
        $tag = strtolower($el->tagName);
        if ($depth > 60 || in_array($tag, self::DROP_WITH_CONTENT, true)) {
            $parent->removeChild($el);
            return;
        }
        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            // Unwrap: sanitize the children first, then hoist them.
            self::cleanChildren($el, $cidMap, $allowRemote, $depth + 1);
            while ($el->firstChild !== null) {
                $parent->insertBefore($el->firstChild, $el);
            }
            $parent->removeChild($el);
            return;
        }

        $href = null;
        $src = null;
        $style = null;
        $keep = [];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;
            if ($name === 'href' && $tag === 'a') {
                $href = $value;
            } elseif ($name === 'src' && $tag === 'img') {
                $src = $value;
            } elseif ($name === 'style') {
                $style = $value;
            } elseif ($name === 'alt' && $tag === 'img') {
                $keep['alt'] = $value;
            } elseif (in_array($name, self::GLOBAL_ATTRS, true) && !str_contains($value, '<')) {
                $keep[$name] = $value;
            }
        }
        while ($el->attributes->length > 0) {
            $el->removeAttributeNode($el->attributes->item(0));
        }
        foreach ($keep as $name => $value) {
            $el->setAttribute($name, $value);
        }
        if ($style !== null) {
            $clean = self::cleanStyle($style);
            if ($clean !== '') {
                $el->setAttribute('style', $clean);
            }
        }

        if ($tag === 'a') {
            $safe = $href !== null ? self::safeUrl($href, ['http', 'https', 'mailto', 'tel']) : null;
            if ($safe !== null) {
                $el->setAttribute('href', $safe);
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }

        if ($tag === 'img') {
            $resolved = null;
            $source = $src !== null ? trim($src) : '';
            if (stripos($source, 'cid:') === 0) {
                $resolved = $cidMap[trim(substr($source, 4), " <>")] ?? null;
            } elseif ($allowRemote) {
                $resolved = self::safeUrl($source, ['https', 'http']);
            }
            if ($resolved === null) {
                $placeholder = $el->ownerDocument->createElement('span', '[obrázek zablokován]');
                $placeholder->setAttribute('class', 'mail-blocked-img');
                $parent->replaceChild($placeholder, $el);
                return;
            }
            $el->setAttribute('src', $resolved);
            $el->setAttribute('loading', 'lazy');
            $el->setAttribute('referrerpolicy', 'no-referrer');
            return;
        }

        self::cleanChildren($el, $cidMap, $allowRemote, $depth + 1);
    }

    private static function safeUrl(string $url, array $schemes): ?string
    {
        $url = trim($url);
        // Browsers ignore tabs/newlines/control chars inside the scheme ("java\tscript:").
        $probe = preg_replace('/[\x00-\x20]+/', '', $url) ?? '';
        if ($probe === '' || strlen($url) > 2000) {
            return null;
        }
        if (!preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $m)) {
            return null; // relative URLs have no meaning inside a mail
        }
        if (!in_array(strtolower($m[1]), $schemes, true)) {
            return null;
        }
        return $url;
    }

    private static function cleanStyle(string $style): string
    {
        $out = [];
        foreach (explode(';', $style) as $declaration) {
            $colon = strpos($declaration, ':');
            if ($colon === false) {
                continue;
            }
            $property = strtolower(trim(substr($declaration, 0, $colon)));
            $value = trim(substr($declaration, $colon + 1));
            if (!in_array($property, self::STYLE_PROPERTIES, true) || $value === '' || strlen($value) > 200) {
                continue;
            }
            if (preg_match('/url\s*\(|expression|javascript|behaviou?r|@import|[\\\\<>{}]|\/\*|!important|position|var\s*\(/i', $value)) {
                continue;
            }
            $out[] = $property . ':' . $value;
        }
        return implode(';', $out);
    }
}
