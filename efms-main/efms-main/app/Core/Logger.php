<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Logger
 *
 * Minimal file logger. Financial systems need an audit-friendly log
 * trail; swap this out for Monolog by re-implementing the same four
 * static methods if the app grows.
 */
final class Logger
{
    private static function path(): string
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/app-' . date('Y-m-d') . '.log';
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            '[%s] %s: %s %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );

        file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * Audit trail entries for financial actions (posting entries,
     * voiding invoices, changing account balances, etc). Kept
     * separate from general app logs for compliance / review.
     */
    public static function audit(string $action, ?int $userId, array $context = []): void
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $line = sprintf(
            '[%s] user:%s action:%s %s%s',
            date('Y-m-d H:i:s'),
            $userId ?? 'system',
            $action,
            json_encode($context, JSON_UNESCAPED_SLASHES),
            PHP_EOL
        );

        file_put_contents($dir . '/audit-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
