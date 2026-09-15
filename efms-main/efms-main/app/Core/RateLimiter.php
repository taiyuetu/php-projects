<?php

declare(strict_types=1);

namespace App\Core;

/**
 * RateLimiter
 *
 * Lightweight file-based throttle helper for sensitive actions (e.g. login).
 */
final class RateLimiter
{
    private static function storageDir(): string
    {
        $dir = __DIR__ . '/../../storage/cache/ratelimit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function filePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key);
        return self::storageDir() . '/' . md5($safeKey) . '.json';
    }

    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $file = self::filePath($key);
        if (!is_file($file)) {
            return false;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !isset($data['reset_at'], $data['attempts'])) {
            return false;
        }

        if (time() > (int) $data['reset_at']) {
            @unlink($file);
            return false;
        }

        return (int) $data['attempts'] >= $maxAttempts;
    }

    public static function hit(string $key, int $decaySeconds = 60): int
    {
        $file = self::filePath($key);
        $now = time();

        $data = ['attempts' => 0, 'reset_at' => $now + $decaySeconds];
        if (is_file($file)) {
            $existing = json_decode((string) file_get_contents($file), true);
            if (is_array($existing) && isset($existing['reset_at']) && (int) $existing['reset_at'] > $now) {
                $data = $existing;
            }
        }

        $data['attempts'] = ((int) ($data['attempts'] ?? 0)) + 1;
        file_put_contents($file, json_encode($data), LOCK_EX);

        return $data['attempts'];
    }

    public static function availableIn(string $key): int
    {
        $file = self::filePath($key);
        if (!is_file($file)) {
            return 0;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !isset($data['reset_at'])) {
            return 0;
        }

        return max(0, (int) $data['reset_at'] - time());
    }

    public static function clear(string $key): void
    {
        $file = self::filePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
