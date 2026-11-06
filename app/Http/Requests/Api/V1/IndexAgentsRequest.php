<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MemberRole;
use Illuminate\Validation\Rule;

class IndexAgentsRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'name' => 'users.name',
            'email' => 'users.email',
            'joined_at' => 'workspace_members.created_at',
            'open_tickets_count' => 'open_tickets_count',
        ];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filterRules(): array
    {
        return [
            'role' => ['sometimes', Rule::in(MemberRole::staffValues())],
            'team_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
