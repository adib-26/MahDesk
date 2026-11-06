<?php

namespace App\Rules;

use App\Support\AttachmentLimits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates one uploaded attachment. See config/attachments.php for the policy.
 *
 * The browser-supplied Content-Type is ignored entirely: it is controlled by
 * the client. The type is detected from the file's bytes with finfo and must
 * match what is expected for the file's extension.
 */
class SafeAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a file.');

            return;
        }

        $name = $value->getClientOriginalName();

        if (! $value->isValid()) {
            $fail(match ($value->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "\"{$name}\" is larger than ".self::human(AttachmentLimits::maxFileKb()).'.',
                UPLOAD_ERR_PARTIAL => "\"{$name}\" was only partly uploaded. Please try again.",
                default => "\"{$name}\" could not be uploaded.",
            });

            return;
        }

        $sizeKb = $value->getSize() / 1024;

        if ($value->getSize() === 0) {
            $fail("\"{$name}\" is empty.");

            return;
        }

        if ($sizeKb > AttachmentLimits::maxFileKb()) {
            $fail("\"{$name}\" is larger than ".self::human(AttachmentLimits::maxFileKb()).'.');

            return;
        }

        if (mb_strlen($name) > 200 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $fail('The file name is too long or contains invalid characters.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $allowed = config('attachments.allowed');

        if (! array_key_exists($extension, $allowed)) {
            $fail("\"{$name}\" is not an allowed file type. Allowed: ".implode(', ', array_keys($allowed)).'.');

            return;
        }

        $detected = self::detectMimeType($value->getRealPath(), $extension);

        if (! in_array($detected, $allowed[$extension], true)) {
            $fail("\"{$name}\" does not look like a .{$extension} file.");

            return;
        }

        if (in_array($extension, config('attachments.images'), true) && @getimagesize($value->getRealPath()) === false) {
            $fail("\"{$name}\" is not a valid image.");
        }
    }

    /**
     * The file's real type, from its bytes (libmagic), never from the client.
     *
     * libmagic can't classify very short text (a 1-byte .txt reports
     * application/octet-stream). For text extensions only, that case counts as
     * text/plain if the bytes really are plain UTF-8 text. HTML and scripts are
     * still detected as their own types and rejected.
     */
    public static function detectMimeType(string $path, string $extension): string
    {
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $allowed = config("attachments.allowed.{$extension}", []);

        if ($detected === 'application/octet-stream'
            && array_intersect($allowed, ['text/plain', 'text/csv']) !== []
            && self::isPlainText($path)) {
            return 'text/plain';
        }

        return $detected;
    }

    /** Valid UTF-8 with no control characters other than tab, CR and LF. */
    private static function isPlainText(string $path): bool
    {
        $sample = (string) file_get_contents($path, false, null, 0, 8192);

        return mb_check_encoding($sample, 'UTF-8') && ! preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sample);
    }

    public static function human(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB' : "{$kb} KB";
    }
}
