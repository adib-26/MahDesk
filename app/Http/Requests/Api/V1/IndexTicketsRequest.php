<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Validation\Rule;

class IndexTicketsRequest extends IndexRequest
{
    protected function sortable(): array
    {
        return [
            'number' => 'number',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'resolution_due_at' => 'resolution_due_at',
            'first_response_due_at' => 'first_response_due_at',
            // Priority is stored as text; sort by severity instead of alphabetically.
            // $direction is produced by IndexRequest::applySort() and is only ever "asc" or "desc".
            'priority' => fn ($query, string $direction) => $query->orderByRaw(
                'case priority when ? then 1 when ? then 2 when ? then 3 when ? then 4 else 0 end '.($direction === 'desc' ? 'desc' : 'asc'),
                ['low', 'normal', 'high', 'urgent'],
            ),
        ];
    }

    protected function defaultSort(): string
    {
        return '-updated_at';
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', 'array'],
            'priority.*' => [Rule::enum(TicketPriority::class)],
            'category' => ['sometimes', 'array'],
            'category.*' => [Rule::enum(TicketCategory::class)],
            'channel' => ['sometimes', 'array'],
            'channel.*' => [Rule::enum(TicketChannel::class)],
            'assignee' => ['sometimes', 'string', 'regex:/^(me|unassigned|\d+)$/'],
            'team_id' => ['sometimes', 'integer'],
            'customer_id' => ['sometimes', 'integer'],
            'tag' => ['sometimes', 'string', 'max:50'],
            'sla_breached' => ['sometimes', 'boolean'],
            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date', 'after_or_equal:created_from'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Accept both ?status=open,pending and ?status[]=open&status[]=pending.
     */
    protected function prepareForValidation(): void
    {
        foreach (['status', 'priority', 'category', 'channel'] as $key) {
            $value = $this->query($key);

            if (is_string($value)) {
                $this->merge([$key => array_values(array_filter(explode(',', $value)))]);
            }
        }
    }
}
