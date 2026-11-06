<?php

namespace App\Http\Resources\V1;

use App\Models\Workspace;
use App\Authorization\RolePermissions;
use App\Enums\MemberRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
class OrganizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'support_email' => $this->support_email,
            'inbound_email' => $this->inboundEmailAddress(),
            'role' => $this->whenPivotLoaded('workspace_members', fn () => $this->pivot->role),
            'permissions' => $this->whenPivotLoaded('workspace_members', fn () => RolePermissions::valuesFor(
                MemberRole::tryFrom((string) $this->pivot->role),
            )),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
