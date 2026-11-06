<?php

namespace App\Http\Resources\V1;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketAttachment */
class AttachmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ticket = $request->route('ticket');

        return [
            'id' => $this->uuid,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => $ticket instanceof Ticket
                ? route('api.v1.tickets.attachments.show', [$request->route('organization')->slug, $ticket->number, $this->uuid])
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
