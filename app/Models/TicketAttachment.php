<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    protected $fillable = [
        'uuid', 'ticket_id', 'ticket_message_id', 'uploaded_by',
        'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
    ];

    /** Storage details never leave the server. */
    protected $hidden = ['disk', 'path', 'sha256', 'ticket_id', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function booted(): void
    {
        // Remove the stored file when the record goes. Rows deleted by a
        // database cascade (ticket deletion) are handled in TicketAttachmentService.
        static::deleted(fn (TicketAttachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
