<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class SaveTeamRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTeams', $this->organization());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('teams', 'name')
                    ->where('workspace_id', $this->organization()->id)
                    ->ignore($this->route('team')?->id),
            ],
        ];
    }
}
