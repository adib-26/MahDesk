<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MemberRole;
use App\Enums\TicketStatus;
use App\Support\AttachmentRules;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketMessageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $ability = $this->input('kind', 'reply') === 'note' ? 'addInternalNote' : 'reply';

        return $this->user()->can($ability, $this->route('ticket'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['sometimes', Rule::in(['reply', 'note'])],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['sometimes', 'nullable', Rule::enum(TicketStatus::class), Rule::prohibitedIf(
                fn () => $this->organization()->roleOf($this->user()) === MemberRole::Customer,
            )],
            ...AttachmentRules::rules(),
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
