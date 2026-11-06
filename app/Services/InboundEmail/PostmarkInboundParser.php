<?php

namespace App\Services\InboundEmail;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Translates a Postmark inbound webhook payload into a ParsedEmail.
 *
 * @see https://postmarkapp.com/developer/webhooks/inbound-webhook
 */
class PostmarkInboundParser implements InboundEmailParser
{
    public const PROVIDER = 'postmark';

    public function provider(): string
    {
        return self::PROVIDER;
    }

    public function parse(array $payload): ParsedEmail
    {
        $providerId = trim((string) ($payload['MessageID'] ?? ''));
        $fromEmail = Str::lower(trim((string) ($payload['FromFull']['Email'] ?? $payload['From'] ?? '')));

        if ($providerId === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Payload must include MessageID and a valid sender address.');
        }

        $headers = [];
        foreach ((array) ($payload['Headers'] ?? []) as $header) {
            if (isset($header['Name'], $header['Value'])) {
                $headers[strtolower((string) $header['Name'])] = (string) $header['Value'];
            }
        }

        $recipients = [];
        foreach (['ToFull', 'CcFull', 'BccFull'] as $field) {
            foreach ((array) ($payload[$field] ?? []) as $recipient) {
                if (! empty($recipient['Email'])) {
                    $recipients[] = Str::lower(trim((string) $recipient['Email']));
                }
            }
        }
        if (! empty($payload['OriginalRecipient'])) {
            $recipients[] = Str::lower(trim((string) $payload['OriginalRecipient']));
        }

        $text = (string) ($payload['TextBody'] ?? '');
        if (trim($text) === '' && ! empty($payload['HtmlBody'])) {
            $text = EmailText::fromHtml((string) $payload['HtmlBody']);
        }

        $fromName = trim((string) ($payload['FromFull']['Name'] ?? $payload['FromName'] ?? ''));

        return new ParsedEmail(
            providerId: $providerId,
            messageId: self::messageIds($headers['message-id'] ?? '')[0] ?? null,
            fromEmail: $fromEmail,
            fromName: $fromName !== '' ? $fromName : null,
            recipients: array_values(array_unique($recipients)),
            subject: trim((string) ($payload['Subject'] ?? '')),
            textBody: $text,
            strippedReply: isset($payload['StrippedTextReply']) ? (string) $payload['StrippedTextReply'] : null,
            threadIds: array_values(array_unique([
                ...self::messageIds($headers['in-reply-to'] ?? ''),
                ...self::messageIds($headers['references'] ?? ''),
            ])),
            headers: $headers,
            attachments: array_map(fn ($attachment) => [
                'name' => (string) ($attachment['Name'] ?? 'attachment'),
                'contentType' => (string) ($attachment['ContentType'] ?? 'application/octet-stream'),
                'size' => (int) ($attachment['ContentLength'] ?? 0),
            ], array_values((array) ($payload['Attachments'] ?? []))),
        );
    }

    /**
     * Remove attachment bodies before the payload is persisted; they can be
     * many megabytes of base64 and are not stored as ticket files yet.
     */
    public function sanitizeForStorage(array $payload): array
    {
        if (isset($payload['Attachments']) && is_array($payload['Attachments'])) {
            $payload['Attachments'] = array_map(function ($attachment) {
                unset($attachment['Content']);

                return $attachment;
            }, $payload['Attachments']);
        }

        unset($payload['RawEmail']);

        return $payload;
    }

    /** @return list<string> */
    private static function messageIds(string $value): array
    {
        preg_match_all('/<([^<>\s]+)>/', $value, $matches);

        return $matches[1] ?? [];
    }
}
