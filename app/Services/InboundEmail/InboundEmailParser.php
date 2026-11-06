<?php

namespace App\Services\InboundEmail;

/**
 * Adapter from one email provider's payload format to ParsedEmail.
 */
interface InboundEmailParser
{
    /** Value stored in inbound_emails.provider. */
    public function provider(): string;

    /**
     * @throws \InvalidArgumentException when the payload is not a usable email
     */
    public function parse(array $payload): ParsedEmail;

    /** Drop bulky or sensitive fields before the payload is persisted. */
    public function sanitizeForStorage(array $payload): array;
}
