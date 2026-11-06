<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class IndexKbArticlesRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'title' => 'title',
            'views' => 'views',
            'published_at' => 'published_at',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-updated_at';
    }

    protected function filterRules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'author_id' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
