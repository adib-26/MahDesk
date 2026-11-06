<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raw inbound email as received from the provider webhook. Persisting it
 * before processing makes provider retries idempotent and leaves an audit
 * trail of why each email did or did not become a ticket.
 */
class InboundEmail extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_CREATED = 'created';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider', 'message_id', 'workspace_id', 'ticket_id', 'from_email',
        'subject', 'status', 'status_reason', 'payload', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function markAs(string $status, ?string $reason = null, ?int $ticketId = null, ?int $workspaceId = null): void
    {
        $this->forceFill([
            'status' => $status,
            'status_reason' => $reason,
            'ticket_id' => $ticketId ?? $this->ticket_id,
            'workspace_id' => $workspaceId ?? $this->workspace_id,
            'processed_at' => now(),
        ])->save();
    }
}
