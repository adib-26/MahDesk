<?php

namespace App\Http\Resources\V1;

use App\Enums\MemberRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A staff member of an organization. Expects the workspace_members pivot.
 *
 * @mixin User
 */
class AgentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = MemberRole::tryFrom((string) $this->pivot?->role);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'can_view_unassigned' => $role === MemberRole::Agent
                ? (bool) $this->pivot->can_view_unassigned
                : true,
            'open_tickets_count' => $this->whenHas('open_tickets_count', fn () => (int) $this->open_tickets_count),
            'teams' => TeamSummaryResource::collection($this->whenLoaded('teams')),
            'joined_at' => $this->pivot?->created_at?->toIso8601String(),
        ];
    }
}
