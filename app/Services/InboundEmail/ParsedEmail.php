<?php

namespace App\Services\InboundEmail;

/**
 * Provider-neutral view of an inbound email. Provider adapters (currently
 * Postmark) translate their webhook payloads into this shape so the ticket
 * pipeline never depends on a specific vendor format.
 */
final readonly class ParsedEmail
{
    /**
     * @param  list<string>  $recipients  every To/Cc/Bcc/original-recipient address, lowercased
     * @param  list<string>  $threadIds  In-Reply-To and References Message-IDs, without angle brackets
     * @param  array<string, string>  $headers  header values keyed by lowercased header name
     * @param  list<array{name: string, contentType: string, size: int}>  $attachments
     */
    public function __construct(
        public string $providerId,
        public ?string $messageId,
        public string $fromEmail,
        public ?string $fromName,
        public array $recipients,
        public string $subject,
        public string $textBody,
        public ?string $strippedReply,
        public array $threadIds,
        public array $headers,
        public array $attachments,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Auto-responders, bounces and mailing-list traffic must never create or
     * reopen tickets, otherwise our own acknowledgement emails can loop.
     */
    public function isAutomated(): bool
    {
        $autoSubmitted = strtolower((string) $this->header('Auto-Submitted'));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        if (in_array(strtolower((string) $this->header('Precedence')), ['bulk', 'junk', 'list', 'auto_reply'], true)) {
            return true;
        }

        if ($this->header('X-Autoreply') !== null || $this->header('X-Autorespond') !== null || $this->header('List-Id') !== null) {
            return true;
        }

        $local = strtolower(strstr($this->fromEmail, '@', true) ?: '');

        return in_array($local, ['mailer-daemon', 'postmaster', 'noreply', 'no-reply', 'donotreply', 'do-not-reply'], true);
    }

    /** Body to store on the ticket: the new text only for replies, the full text otherwise. */
    public function visibleBody(bool $isReply): string
    {
        $body = $isReply
            ? ($this->strippedReply !== null && trim($this->strippedReply) !== '' ? $this->strippedReply : EmailText::stripQuotedReply($this->textBody))
            : $this->textBody;

        $body = trim($body);

        return mb_substr($body !== '' ? $body : '(empty message)', 0, 10000);
    }
}
