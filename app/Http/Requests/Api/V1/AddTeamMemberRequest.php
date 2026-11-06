<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MemberRole;
use Illuminate\Validation\Rule;

class AddTeamMemberRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTeams', $this->organization());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'agent_id' => [
                'required', 'integer',
                Rule::exists('workspace_members', 'user_id')
                    ->where('workspace_id', $this->organization()->id)
                    ->whereIn('role', MemberRole::staffValues()),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['agent_id.exists' => 'Choose a support team member of this organization.'];
    }
}
