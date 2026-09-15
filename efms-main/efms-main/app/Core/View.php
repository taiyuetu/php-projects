<?php

declare(strict_types=1);

namespace App\Core;

/**
 * View
 *
 * Renders plain PHP templates from app/Views with automatic escaping
 * helper (e::) and a simple layout mechanism (view()->extends('layouts/app')).
 * No compiler/cache step — keeps the framework easy to read end to end.
 */
final class View
{
    private static string $basePath = __DIR__ . '/../Views';

    public static function render(string $template, array $data = []): string
    {
        require_once __DIR__ . '/../helpers.php';

        $file = self::$basePath . '/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = ob_get_clean();

        // Support ['layout' => 'layouts/app'] to wrap content in a layout.
        // The layout template can reference the rendered child via $content.
        if (!empty($data['layout'])) {
            $layoutFile = self::$basePath . '/' . str_replace('.', '/', $data['layout']) . '.php';
            if (is_file($layoutFile)) {
                extract($data, EXTR_SKIP);
                $slot = $content; // preserve inner content before layout's own output buffer
                ob_start();
                require $layoutFile;
                $content = ob_get_clean();
            }
        }

        return $content;
    }

    /** HTML-escape helper for use inside templates: <?= e($name) ?> */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
