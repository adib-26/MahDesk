<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MemberRole;
use Illuminate\Validation\Rule;

class IndexOrganizationsRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'name' => 'workspaces.name',
            'slug' => 'workspaces.slug',
            'created_at' => 'workspaces.created_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filterRules(): array
    {
        return [
            'role' => ['sometimes', Rule::enum(MemberRole::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
