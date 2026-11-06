<?php

namespace App\Http\Requests\Api\V1;

class IndexKbCategoriesRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'position' => 'position',
            'name' => 'name',
            'articles_count' => 'articles_count',
            'created_at' => 'created_at',
        ];
    }

    protected function defaultSort(): string
    {
        return 'position,name';
    }

    protected function filterRules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
