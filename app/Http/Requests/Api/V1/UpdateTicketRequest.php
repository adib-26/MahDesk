<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        if (! $this->user()->can('update', $ticket)) {
            return false;
        }

        // Moving or reassigning a ticket needs the stronger "assign" permission.
        if ($this->hasAny(['team_id', 'assignee_id'])) {
            return $this->user()->can('assign', $ticket);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $orgId = $this->organization()->id;

        return [
            'subject' => ['sometimes', 'string', 'max:200'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'category' => ['sometimes', Rule::enum(TicketCategory::class)],
            'team_id' => ['sometimes', 'nullable', 'integer', Rule::exists('teams', 'id')->where('workspace_id', $orgId)],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('workspace_id', $orgId)],
        ];
    }
}
