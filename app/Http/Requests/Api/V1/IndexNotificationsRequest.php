<?php

namespace App\Http\Requests\Api\V1;

class IndexNotificationsRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'created_at' => 'created_at',
            'read_at' => 'read_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function filterRules(): array
    {
        return [
            'unread' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'string', 'max:100'],
            'organization' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
