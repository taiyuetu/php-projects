<?php

/**
 * Global view helpers.
 * Loaded once by public/index.php — available in every view and controller.
 */

use App\Models\Setting;

if (!function_exists('setting')) {
    /**
     * Read a stored setting (falls back to its default when unset).
     * Never throws — safe to call from any view.
     */
    function setting(string $key, string $default = ''): string
    {
        try {
            return Setting::get($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('money')) {
    /**
     * Format an amount using the configurable currency symbol.
     * Replaces the previously hard-coded "$" prefixes in views.
     */
    function money($amount, int $decimals = 2): string
    {
        return setting('currency_symbol', '$') . number_format((float) $amount, $decimals);
    }
}
