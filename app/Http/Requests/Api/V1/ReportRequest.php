<?php

namespace App\Http\Requests\Api\V1;

class ReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAnalytics', $this->organization());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
