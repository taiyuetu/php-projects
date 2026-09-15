<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Config
 *
 * Loads every PHP file in /config into a flat namespaced array
 * (filename => contents) and exposes dot-notation access, e.g.
 * Config::get('database.host') or Config::get('app.debug').
 */
final class Config
{
    private static array $items = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        foreach (glob(rtrim($path, '/') . '/*.php') as $file) {
            $key = basename($file, '.php');
            self::$items[$key] = require $file;
        }
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load(__DIR__ . '/../../config');
        }

        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
