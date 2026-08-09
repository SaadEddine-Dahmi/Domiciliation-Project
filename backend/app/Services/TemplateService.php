<?php

namespace App\Services;

class TemplateService
{
    /**
     * Resolve every {{key}} token in $body against $data.
     *
     * Resolved values are HTML-escaped and wrapped in <strong> so they
     * stand out visually in the rendered contract. Unresolved tokens
     * (key not present in $data) are shown as the bare key name in
     * gold italic — visible to the domiciliataire as a hint they
     * mistyped a variable, rather than silently vanishing or leaking
     * the raw {{...}} syntax into the final document.
     */
    public function render(string $body, array $data): string
    {
        if ($body === '') {
            return '';
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function (array $matches) use ($data) {
                $key = $matches[1];

                if (array_key_exists($key, $data) && $data[$key] !== null) {
                    $escaped = htmlspecialchars((string) $data[$key], ENT_QUOTES, 'UTF-8');
                    return "<strong>{$escaped}</strong>";
                }

                return '<em style="color:#c8a96e;font-style:italic">' . $key . '</em>';
            },
            $body
        );
    }
}