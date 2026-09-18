<?php

namespace App\Models;

use App\Core\Model;

/**
 * Setting — application settings stored as a key/value table.
 *
 * Settings are managed on the "系统设置" page (admin only) and cover:
 *  - 应用信息  (app_name)
 *  - 公司信息  (company_name / phone / email / address)
 *  - 货币符号  (currency_symbol — used by the money() view helper)
 *  - 单据备注  (invoice_note)
 *
 * Any key missing from the table falls back to DEFAULTS, so the app
 * works out of the box before the settings page is ever used.
 */
class Setting extends Model
{
    protected static string $table = 'settings';
    protected static array $fillable = ['skey', 'svalue'];
    protected static bool $logChanges = false; // key/value store — skip change-log noise

    /** Built-in settings with their defaults. Only these keys are accepted by set(). */
    public const DEFAULTS = [
        'app_name'        => 'PSI 系统',
        'currency_symbol' => '$',
        'company_name'    => '',
        'company_phone'   => '',
        'company_email'   => '',
        'company_address' => '',
        'invoice_note'    => '',
    ];

    /** Per-request cache so views can call setting() freely without extra queries. */
    private static ?array $cache = null;

    /** All settings merged over their defaults: key => value. */
    public static function allSettings(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (self::db()->query('SELECT skey, svalue FROM settings')->fetchAll() as $row) {
                    self::$cache[$row['skey']] = $row['svalue'];
                }
            } catch (\Throwable $e) {
                // Table not created yet — defaults are enough.
            }
        }
        return self::$cache;
    }

    /** Get one setting, falling back to the default (or $default). */
    public static function get(string $key, string $default = ''): string
    {
        $all = self::allSettings();
        return $all[$key] ?? (self::DEFAULTS[$key] ?? $default);
    }

    /** Persist a map of key => value (unknown keys are ignored). Portable upsert. */
    public static function set(array $values): void
    {
        $update = self::db()->prepare('UPDATE settings SET svalue = ?, updated_at = CURRENT_TIMESTAMP WHERE skey = ?');
        $insert = self::db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) continue; // whitelist
            $update->execute([(string) $value, $key]);
            if ($update->rowCount() === 0) {
                $insert->execute([$key, (string) $value]);
            }
        }

        self::$cache = null; // invalidate cache
    }
}
