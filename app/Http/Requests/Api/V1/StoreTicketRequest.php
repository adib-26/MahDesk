<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Support\AttachmentRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Ticket::class, $this->organization()]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $orgId = $this->organization()->id;

        return [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'channel' => ['sometimes', Rule::enum(TicketChannel::class)],
            'category' => ['sometimes', Rule::enum(TicketCategory::class)],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('workspace_id', $orgId)],
            'assignee_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('workspace_id', $orgId)],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:100'],
            'customer_email' => ['required_without:customer_id', 'nullable', 'email', 'max:150'],
            ...AttachmentRules::rules(),
        ];
    }

    /**
     * Map API field names onto the TicketService input shape.
     *
     * @return array<string, mixed>
     */
    public function ticketData(): array
    {
        $data = $this->validated();

        return [
            'subject' => $data['subject'],
            'body' => $data['body'],
            'priority' => $data['priority'] ?? TicketPriority::Normal->value,
            'channel' => $data['channel'] ?? TicketChannel::Web->value,
            'category' => $data['category'] ?? TicketCategory::General->value,
            'team_id' => $data['team_id'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'contact_id' => $data['customer_id'] ?? null,
            'contact_name' => $data['customer_name'] ?? null,
            'contact_email' => $data['customer_email'] ?? null,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return AttachmentRules::messages();
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => AttachmentRules::checkTotalSize($validator, $this->file('attachments'))];
    }
}
