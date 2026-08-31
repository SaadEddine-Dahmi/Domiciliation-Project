<?php
// app/Services/TemplateService.php
// Renders template placeholders for contract previews.

namespace App\Services;

class TemplateService
{
    public function render(string $body, array $data): string
    {
        if ($body === '') {
            return '';
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($data) {
            $key = $matches[1];

            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $escaped = htmlspecialchars((string) $data[$key], ENT_QUOTES, 'UTF-8');

                return "<strong>{$escaped}</strong>";
            }

            return '<em style="color:#c8a96e;font-style:italic">' . $key . '</em>';
        }, $body);
    }
}
