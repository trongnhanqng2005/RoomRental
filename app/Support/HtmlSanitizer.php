<?php

namespace App\Support;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><s><strike><sub><sup><h1><h2><h3><h4><h5><h6><ul><ol><li><span><div><blockquote><hr><a><font>';

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Strip malicious executable / interactive tags and their internal content
        $html = preg_replace('/<(script|style|iframe|object|embed|applet|form|button|input|textarea|select)[^>]*>.*?<\/\1>/is', '', $html);

        // Strip disallowed tags
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        // Sanitize attributes to prevent XSS (remove on* handlers, javascript: protocols)
        $clean = preg_replace_callback('/<([a-z1-6]+)([^>]*?)>/i', function ($matches) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];

            // Remove on* event attributes (onclick, onload, onerror, onmouseover, etc.)
            $attrs = preg_replace('/\s*on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $attrs);

            // Remove javascript:, data:, vbscript: URIs
            $attrs = preg_replace('/\s*(href|src)\s*=\s*(["\']?\s*(javascript|data|vbscript):[^"\'>\s]*["\']?)/i', '', $attrs);

            return "<{$tag}{$attrs}>";
        }, $clean);

        return $clean;
    }
}
