<?php

namespace App\Services\InboundEmail;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Translates a testmail.app email object (GraphQL `Email` type) into a
 * ParsedEmail. Field list: https://api.testmail.app/api/graphql (introspection).
 */
class TestmailInboundParser implements InboundEmailParser
{
    public const PROVIDER = 'testmail';

    public function provider(): string
    {
        return self::PROVIDER;
    }

    public function parse(array $payload): ParsedEmail
    {
        $providerId = trim((string) ($payload['id'] ?? ''));
        $from = $payload['from_parsed'][0] ?? [];
        $fromEmail = Str::lower(trim((string) ($from['address'] ?? '')));

        if ($providerId === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('testmail.app email must include an id and a valid sender address.');
        }

        $headers = [];
        foreach ((array) ($payload['headers'] ?? []) as $header) {
            $key = strtolower((string) ($header['key'] ?? ''));
            $line = (string) ($header['line'] ?? '');
            if ($key !== '' && str_contains($line, ':')) {
                $headers[$key] = trim(Str::after($line, ':'));
            }
        }

        $recipients = [];
        foreach (['to_parsed', 'cc_parsed'] as $field) {
            foreach ((array) ($payload[$field] ?? []) as $recipient) {
                if (! empty($recipient['address'])) {
                    $recipients[] = Str::lower(trim((string) $recipient['address']));
                }
            }
        }
        // The envelope recipient is the testmail.app inbox that actually
        // received the message, even when To: still shows support@company.com.
        foreach (preg_split('/[\s,]+/', (string) ($payload['envelope_to'] ?? '')) ?: [] as $address) {
            if ($address !== '') {
                $recipients[] = Str::lower(trim($address, '<>'));
            }
        }

        $text = (string) ($payload['text'] ?? '');
        if (trim($text) === '' && ! empty($payload['html'])) {
            $text = EmailText::fromHtml((string) $payload['html']);
        }

        $references = array_map(fn ($id) => self::stripBrackets((string) $id), (array) ($payload['references'] ?? []));
        preg_match_all('/<([^<>\s]+)>/', $headers['in-reply-to'] ?? '', $inReplyTo);

        $messageId = self::stripBrackets((string) ($payload['messageId'] ?? ''));
        $fromName = trim((string) ($from['name'] ?? ''));

        return new ParsedEmail(
            providerId: $providerId,
            messageId: $messageId !== '' ? $messageId : null,
            fromEmail: $fromEmail,
            fromName: $fromName !== '' ? $fromName : null,
            recipients: array_values(array_unique($recipients)),
            subject: trim((string) ($payload['subject'] ?? '')),
            textBody: $text,
            strippedReply: null,
            threadIds: array_values(array_unique(array_filter([...$inReplyTo[1], ...$references]))),
            headers: $headers,
            attachments: array_map(fn ($attachment) => [
                'name' => (string) ($attachment['filename'] ?? 'attachment'),
                'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                'size' => (int) ($attachment['size'] ?? 0),
            ], array_values((array) ($payload['attachments'] ?? []))),
        );
    }

    public function sanitizeForStorage(array $payload): array
    {
        unset($payload['spam_report']);

        return $payload;
    }

    private static function stripBrackets(string $id): string
    {
        return trim($id, " \t<>");
    }
}
