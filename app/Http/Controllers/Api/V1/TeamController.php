<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddTeamMemberRequest;
use App\Http\Requests\Api\V1\IndexTeamsRequest;
use App\Http\Requests\Api\V1\SaveTeamRequest;
use App\Http\Resources\V1\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(IndexTeamsRequest $request, Workspace $organization): AnonymousResourceCollection
    {
        $query = $organization->teams()
            ->withCount(['members', 'tickets'])
            ->whereContains('name', $request->filter('q'))
            ->when($request->filter('member_id'), fn ($q, $id) => $q->forMember((int) $id));

        return TeamResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function store(SaveTeamRequest $request, Workspace $organization): JsonResponse
    {
        $team = $organization->teams()->create($request->validated());

        return TeamResource::make($this->loaded($team))->response()->setStatusCode(201);
    }

    public function show(Workspace $organization, Team $team): TeamResource
    {
        return TeamResource::make($this->loaded($team));
    }

    public function update(SaveTeamRequest $request, Workspace $organization, Team $team): TeamResource
    {
        $team->update($request->validated());

        return TeamResource::make($this->loaded($team));
    }

    /** Tickets stay in place and simply lose their team. */
    public function destroy(Workspace $organization, Team $team): JsonResponse
    {
        Gate::authorize('manageTeams', $organization);

        $team->delete();

        return response()->json(null, 204);
    }

    public function addMember(AddTeamMemberRequest $request, Workspace $organization, Team $team): TeamResource
    {
        $agentId = (int) $request->validated('agent_id');

        if ($team->hasMember($agentId)) {
            throw ValidationException::withMessages(['agent_id' => 'That agent is already on this team.']);
        }

        $team->members()->attach($agentId);

        return TeamResource::make($this->loaded($team));
    }

    public function removeMember(Workspace $organization, Team $team, User $member): JsonResponse
    {
        Gate::authorize('manageTeams', $organization);

        $team->members()->detach($member->id);

        return response()->json(null, 204);
    }

    private function loaded(Team $team): Team
    {
        return $team
            ->load(['members' => fn ($q) => $q->select('users.id', 'users.name', 'users.email')->orderBy('users.name')])
            ->loadCount(['members', 'tickets']);
    }
}
