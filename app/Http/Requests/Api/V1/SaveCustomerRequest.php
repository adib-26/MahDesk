<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Contact;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact
            ? $this->user()->can('update', $contact)
            : $this->user()->can('create', [Contact::class, $this->organization()]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $contact = $this->route('contact');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'email' => [
                $required, 'email', 'max:150',
                Rule::unique('contacts', 'email')
                    ->where('workspace_id', $this->organization()->id)
                    ->ignore($contact?->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
