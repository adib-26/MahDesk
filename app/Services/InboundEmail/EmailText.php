<?php

namespace App\Services\InboundEmail;

final class EmailText
{
    /** Convert an HTML body to readable plain text for providers that send no text part. */
    public static function fromHtml(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>|</(p|div|li|tr|h[1-6])>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * Drop the quoted history most clients append to replies, keeping only the
     * customer's new text. Used when the provider has not already done so.
     */
    public static function stripQuotedReply(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (
                preg_match('/^On .+wrote:$/i', $trimmed)
                || preg_match('/^-{2,}\s*Original Message\s*-{2,}$/i', $trimmed)
                || (preg_match('/^From: .+/i', $trimmed) && $kept !== [])
                || str_starts_with($trimmed, '>')
            ) {
                break;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }
}
