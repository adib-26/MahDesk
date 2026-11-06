<?php

namespace App\Support;

/**
 * Effective attachment limits: the configured values, capped by PHP's upload
 * settings so the UI never promises more than the server will accept.
 */
final class AttachmentLimits
{
    public static function maxFileKb(): int
    {
        return min(
            (int) config('attachments.max_file_kb'),
            self::iniKb('upload_max_filesize'),
        );
    }

    public static function maxTotalKb(): int
    {
        return min(
            (int) config('attachments.max_total_kb'),
            self::iniKb('post_max_size'),
        );
    }

    public static function maxFiles(): int
    {
        return min((int) config('attachments.max_files'), (int) ini_get('max_file_uploads') ?: PHP_INT_MAX);
    }

    /** @return list<string> */
    public static function extensions(): array
    {
        return array_keys(config('attachments.allowed'));
    }

    /** @return array{max_file_kb: int, max_total_kb: int, max_files: int, extensions: list<string>} */
    public static function forClient(): array
    {
        return [
            'max_file_kb' => self::maxFileKb(),
            'max_total_kb' => self::maxTotalKb(),
            'max_files' => self::maxFiles(),
            'extensions' => self::extensions(),
        ];
    }

    /** Convert php.ini sizes such as "2M" or "512K" to kilobytes. 0 means unlimited. */
    private static function iniKb(string $key): int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '0' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => $number / 1024,
        };
    }
}
