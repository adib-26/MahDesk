<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TicketPriority;
use Illuminate\Validation\Rule;

class IndexSlaPoliciesRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'name' => 'name',
            'first_response_minutes' => 'first_response_minutes',
            'resolution_minutes' => 'resolution_minutes',
            'is_default' => 'is_default',
            'created_at' => 'created_at',
        ];
    }

    protected function defaultSort(): string
    {
        return '-is_default,name';
    }

    protected function filterRules(): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
