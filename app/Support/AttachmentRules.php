<?php

namespace App\Support;

use App\Rules\SafeAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Validation rules for an "attachments" array field, shared by the desk, the
 * customer portal and the API.
 */
final class AttachmentRules
{
    /** @return array<string, mixed> */
    public static function rules(string $field = 'attachments'): array
    {
        return [
            $field => ['sometimes', 'array', 'max:'.AttachmentLimits::maxFiles()],
            "{$field}.*" => ['required', new SafeAttachment],
        ];
    }

    /** @return array<string, string> */
    public static function messages(string $field = 'attachments'): array
    {
        return [
            "{$field}.max" => 'You can attach up to '.AttachmentLimits::maxFiles().' files per message.',
            "{$field}.array" => 'Attachments must be sent as a list of files.',
            // Laravel rejects files PHP refused (e.g. over upload_max_filesize) before custom rules run.
            "{$field}.*.uploaded" => 'A file is larger than '.SafeAttachment::human(AttachmentLimits::maxFileKb()).' or did not finish uploading.',
        ];
    }

    /** Add the combined-size check to a validator. */
    public static function checkTotalSize(Validator $validator, mixed $files, string $field = 'attachments'): void
    {
        $totalBytes = collect(is_array($files) ? $files : [])
            ->filter(fn ($file) => $file instanceof UploadedFile && $file->isValid())
            ->sum(fn (UploadedFile $file) => $file->getSize());

        if ($totalBytes / 1024 > AttachmentLimits::maxTotalKb()) {
            $validator->errors()->add($field, 'Attachments together must be under '.SafeAttachment::human(AttachmentLimits::maxTotalKb()).'.');
        }
    }
}
