<?php

namespace App\Http\Requests\Api\V1;

class IndexTeamsRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'name' => 'name',
            'created_at' => 'created_at',
            'tickets_count' => 'tickets_count',
            'members_count' => 'members_count',
        ];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function filterRules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'member_id' => ['sometimes', 'integer'],
        ];
    }
}
