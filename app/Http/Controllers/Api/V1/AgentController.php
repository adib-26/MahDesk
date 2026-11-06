<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemberRole;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAgentsRequest;
use App\Http\Resources\V1\AgentResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Support staff (owners, admins, managers and agents) of an organization.
 * Read-only: membership changes go through invitations in the desk.
 */
class AgentController extends Controller
{
    public function index(IndexAgentsRequest $request, Workspace $organization): AnonymousResourceCollection
    {
        $query = $this->staff($organization)
            ->when($request->filter('role'), fn ($q, $role) => $q->where('workspace_members.role', $role))
            ->when($request->filter('team_id'), fn ($q, $teamId) => $q->whereHas(
                'teams',
                fn ($t) => $t->where('teams.id', $teamId),
            ))
            ->whereContains(['users.name', 'users.email'], $request->filter('q'));

        return AgentResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function show(Workspace $organization, User $member): AgentResource
    {
        $agent = $this->staff($organization)->whereKey($member->id)->first();
        abort_if($agent === null, 404, 'Agent not found.');

        return AgentResource::make($agent);
    }

    private function staff(Workspace $organization)
    {
        return $organization->members()
            ->wherePivotIn('role', MemberRole::staffValues())
            ->with(['teams' => fn ($q) => $q->where('teams.workspace_id', $organization->id)->select('teams.id', 'teams.name')])
            ->withCount(['assignedTickets as open_tickets_count' => fn (Builder $q) => $q
                ->where('workspace_id', $organization->id)
                ->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])]);
    }
}
