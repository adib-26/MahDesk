<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class IndexTicketMessagesRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return ['created_at' => 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'created_at';
    }

    protected function filterRules(): array
    {
        return [
            'kind' => ['sometimes', Rule::in(['reply', 'note', 'event'])],
        ];
    }
}
