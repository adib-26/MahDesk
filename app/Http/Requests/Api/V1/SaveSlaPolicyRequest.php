<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TicketPriority;
use App\Enums\Permission;
use Illuminate\Validation\Rule;

class SaveSlaPolicyRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageSla->value, $this->organization());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'nullable', Rule::enum(TicketPriority::class)],
            'first_response_minutes' => [$required, 'integer', 'min:5', 'max:20160'],
            'resolution_minutes' => [$required, 'integer', 'min:15', 'max:80640'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
