<?php

namespace App\Http\Requests\Api\V1;

class IndexCustomersRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'company' => 'company',
            'created_at' => 'created_at',
            'tickets_count' => 'tickets_count',
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
            'company' => ['sometimes', 'nullable', 'string', 'max:100'],
            'has_account' => ['sometimes', 'boolean'],
        ];
    }
}
