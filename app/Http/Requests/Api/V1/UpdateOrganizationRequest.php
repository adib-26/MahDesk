<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageWorkspace', $this->organization());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'support_email' => [
                'sometimes', 'nullable', 'email', 'max:150',
                Rule::unique('workspaces', 'support_email')->ignore($this->organization()->id),
            ],
        ];
    }
}
