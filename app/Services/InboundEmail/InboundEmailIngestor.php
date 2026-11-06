<?php

namespace App\Services\InboundEmail;

use App\Jobs\ProcessInboundEmail;
use App\Models\InboundEmail;
use Illuminate\Database\UniqueConstraintViolationException;

class InboundEmailIngestor
{
    /**
     * Validate and persist a provider payload, then queue it for processing.
     * Providers retry deliveries and the poller re-reads its last page, so a
     * repeated provider message ID returns the existing record without
     * queueing it again.
     *
     * @throws \InvalidArgumentException when the payload is not a usable email
     */
    public function ingest(InboundEmailParser $parser, array $payload, bool $sync = false): InboundEmail
    {
        $email = $parser->parse($payload);

        $existing = $this->find($parser->provider(), $email->providerId);
        if ($existing) {
            return $existing;
        }

        try {
            $record = InboundEmail::create([
                'provider' => $parser->provider(),
                'message_id' => $email->providerId,
                'from_email' => $email->fromEmail,
                'subject' => mb_substr($email->subject, 0, 255),
                'status' => InboundEmail::STATUS_PENDING,
                'payload' => $parser->sanitizeForStorage($payload),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->find($parser->provider(), $email->providerId);
        }

        $sync
            ? ProcessInboundEmail::dispatchSync($record->id)
            : ProcessInboundEmail::dispatch($record->id);

        return $record->refresh();
    }

    private function find(string $provider, string $providerId): ?InboundEmail
    {
        return InboundEmail::query()
            ->where('provider', $provider)
            ->where('message_id', $providerId)
            ->first();
    }
}
