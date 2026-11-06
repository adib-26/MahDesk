<?php

namespace App\Http\Resources\V1;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'category' => $this->category->value,
            'channel' => $this->channel->value,
            'customer' => CustomerResource::make($this->whenLoaded('contact')),
            'assignee' => UserSummaryResource::make($this->whenLoaded('assignee')),
            'team' => TeamSummaryResource::make($this->whenLoaded('team')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'sla' => [
                'policy' => $this->whenLoaded('slaPolicy', fn () => $this->slaPolicy
                    ? ['id' => $this->slaPolicy->id, 'name' => $this->slaPolicy->name]
                    : null),
                'first_response_due_at' => $this->first_response_due_at?->toIso8601String(),
                'resolution_due_at' => $this->resolution_due_at?->toIso8601String(),
                'first_response_breached' => $this->first_response_breached,
                'resolution_breached' => $this->resolution_breached,
            ],
            'first_responded_at' => $this->first_responded_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
