<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for write requests under /api/v1/organizations/{organization}.
 */
abstract class ApiFormRequest extends FormRequest
{
    protected function organization(): Workspace
    {
        return $this->route('organization');
    }
}
