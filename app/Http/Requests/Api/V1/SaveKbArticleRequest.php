<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class SaveKbArticleRequest extends ApiFormRequest
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
            'title' => [$required, 'string', 'max:200'],
            'category_id' => [$required, 'integer', Rule::exists('kb_categories', 'id')->where('workspace_id', $this->organization()->id)],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => [$required, 'string', 'max:100000'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
        ];
    }
}
