<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexSlaPoliciesRequest;
use App\Http\Requests\Api\V1\SaveSlaPolicyRequest;
use App\Http\Resources\V1\SlaPolicyResource;
use App\Enums\Permission;
use App\Models\SlaPolicy;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SlaPolicyController extends Controller
{
    public function index(IndexSlaPoliciesRequest $request, Workspace $organization): AnonymousResourceCollection
    {
        $query = $organization->slaPolicies()
            ->when($request->filter('priority'), fn ($q, $priority) => $q->where('priority', $priority))
            ->when($request->has('is_default'), fn ($q) => $q->where('is_default', $request->boolean('is_default')));

        return SlaPolicyResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function store(SaveSlaPolicyRequest $request, Workspace $organization): JsonResponse
    {
        $policy = DB::transaction(function () use ($request, $organization) {
            $policy = $organization->slaPolicies()->create($request->validated());
            $this->keepSingleDefault($organization, $policy);

            return $policy;
        });

        return SlaPolicyResource::make($policy)->response()->setStatusCode(201);
    }

    public function show(Workspace $organization, SlaPolicy $slaPolicy): SlaPolicyResource
    {
        return SlaPolicyResource::make($slaPolicy);
    }

    public function update(SaveSlaPolicyRequest $request, Workspace $organization, SlaPolicy $slaPolicy): SlaPolicyResource
    {
        DB::transaction(function () use ($request, $organization, $slaPolicy) {
            $slaPolicy->update($request->validated());
            $this->keepSingleDefault($organization, $slaPolicy);
        });

        return SlaPolicyResource::make($slaPolicy->refresh());
    }

    /** Existing tickets keep their due dates; they just lose the policy link. */
    public function destroy(Workspace $organization, SlaPolicy $slaPolicy): JsonResponse
    {
        Gate::authorize(Permission::ManageSla->value, $organization);

        $slaPolicy->delete();

        return response()->json(null, 204);
    }

    private function keepSingleDefault(Workspace $organization, SlaPolicy $policy): void
    {
        if ($policy->is_default) {
            $organization->slaPolicies()->whereKeyNot($policy->id)->update(['is_default' => false]);
        }
    }
}
