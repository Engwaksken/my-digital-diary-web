<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class LegalContentFormatter
{
    private const ALLOWED_TAGS = [
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'strong',
        'em',
        'ul',
        'ol',
        'li',
    ];

    private const REMOVE_WITH_CONTENT = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
        'template',
    ];

    /**
     * Retain only the structural formatting supported by public legal pages.
     */
    public function sanitize(?string $content): string
    {
        $content = trim((string) $content);

        if ($content === '') {
            return '';
        }

        $document = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<div>' . $content . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $container = $document->getElementsByTagName('div')->item(0);

        if (! $container instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($container);

        $html = '';

        foreach ($container->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    /**
     * Return safe HTML while retaining readable line breaks for existing text.
     */
    public function render(?string $content): string
    {
        $content = $this->sanitize($content);

        if ($content === '') {
            return '';
        }

        if (! preg_match('/<(?:h[2-6]|strong|em|ul|ol|li)>/i', $content)) {
            return nl2br($content, false);
        }

        return $content;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $nextNode = $node->nextSibling;

            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);
            } elseif ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);

                if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
                    $parent->removeChild($node);
                } else {
                    $this->sanitizeChildren($node);

                    while ($node->attributes->length > 0) {
                        $node->removeAttributeNode($node->attributes->item(0));
                    }

                    if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                        while ($node->firstChild !== null) {
                            $parent->insertBefore($node->firstChild, $node);
                        }

                        $parent->removeChild($node);
                    }
                }
            }

            $node = $nextNode;
        }
    }
}
