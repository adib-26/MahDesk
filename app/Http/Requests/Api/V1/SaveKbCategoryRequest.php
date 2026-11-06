<?php

namespace App\Http\Requests\Api\V1;

class SaveKbCategoryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageKnowledgeBase', $this->organization());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
