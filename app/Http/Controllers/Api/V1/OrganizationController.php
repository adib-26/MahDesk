<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexOrganizationsRequest;
use App\Http\Requests\Api\V1\UpdateOrganizationRequest;
use App\Http\Resources\V1\OrganizationResource;
use App\Models\Workspace;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /** Organizations the caller is a member of. */
    public function index(IndexOrganizationsRequest $request): AnonymousResourceCollection
    {
        $query = $request->user()->workspaces()
            ->when($request->filter('role'), fn ($q, $role) => $q->where('workspace_members.role', $role))
            ->whereContains(['workspaces.name', 'workspaces.slug'], $request->filter('q'));

        return OrganizationResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function show(Request $request, Workspace $organization): OrganizationResource
    {
        $member = $request->user()->workspaces()->whereKey($organization->id)->first();

        return OrganizationResource::make($member ?? $organization);
    }

    public function update(UpdateOrganizationRequest $request, Workspace $organization): OrganizationResource
    {
        $validated = $request->validated();

        if (array_key_exists('support_email', $validated)) {
            $validated['support_email'] = $validated['support_email'] !== null
                ? Str::lower($validated['support_email'])
                : null;
        }

        $organization->update($validated);
        $this->audit->record('workspace.updated', $request->user(), $organization, $organization, $validated, $request);

        return $this->show($request, $organization);
    }
}
