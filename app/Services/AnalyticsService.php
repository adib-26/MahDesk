<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

class AnalyticsService
{
    /**
     * Aggregates are computed in PHP over a bounded window so the same code
     * works on SQLite, MySQL and Postgres without dialect-specific SQL.
     */
    public function forWorkspace(Workspace $workspace, User $user): array
    {
        $since = now()->subDays(29)->startOfDay();

        $recent = Ticket::query()
            ->visibleTo($user, $workspace)
            ->where(fn ($q) => $q->where('created_at', '>=', $since)->orWhere('resolved_at', '>=', $since))
            ->get([
                'id', 'status', 'priority', 'channel', 'assignee_id', 'created_at',
                'first_responded_at', 'first_response_due_at', 'resolved_at', 'resolution_due_at',
            ]);

        $open = Ticket::query()
            ->visibleTo($user, $workspace)
            ->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->get(['id', 'status', 'priority', 'channel', 'assignee_id', 'created_at', 'first_response_due_at', 'first_responded_at']);

        $createdInWindow = $recent->filter(fn ($t) => $t->created_at >= $since);
        $resolvedInWindow = $recent->filter(fn ($t) => $t->resolved_at && $t->resolved_at >= $since);

        $responded = $createdInWindow->filter(fn ($t) => $t->first_responded_at);
        $avgFirstResponseMinutes = $responded->isEmpty() ? null : round(
            $responded->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_responded_at))
        );

        $avgResolutionMinutes = $resolvedInWindow->isEmpty() ? null : round(
            $resolvedInWindow->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at))
        );

        $withSla = $resolvedInWindow->filter(fn ($t) => $t->resolution_due_at);
        $slaCompliance = $withSla->isEmpty() ? null : round(
            $withSla->filter(fn ($t) => $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at))->count()
                / $withSla->count() * 100
        );

        return [
            'kpis' => [
                'open' => $open->count(),
                'unassigned' => $open->whereNull('assignee_id')->count(),
                'breachingSoon' => $open->filter(function ($t) {
                    return $t->first_response_due_at
                        && ! $t->first_responded_at
                        && now()->diffInMinutes($t->first_response_due_at, false) < 120;
                })->count(),
                'createdToday' => $createdInWindow->filter(fn ($t) => $t->created_at->isToday())->count(),
                'resolvedThisWeek' => $resolvedInWindow->filter(fn ($t) => $t->resolved_at->greaterThanOrEqualTo(now()->startOfWeek()))->count(),
                'avgFirstResponseMinutes' => $avgFirstResponseMinutes,
                'avgResolutionMinutes' => $avgResolutionMinutes,
                'slaCompliance' => $slaCompliance,
            ],
            'series' => $this->dailySeries($createdInWindow, $resolvedInWindow, $since),
            'byStatus' => $this->countBy($open, fn ($t) => $t->status->value),
            'byPriority' => $this->countBy($open, fn ($t) => $t->priority->value),
            'byChannel' => $this->countBy($createdInWindow, fn ($t) => $t->channel->value),
            'agents' => $this->agentLeaderboard($workspace, $user, $open, $resolvedInWindow),
        ];
    }

    private function dailySeries($created, $resolved, Carbon $since): array
    {
        $createdByDay = $created->groupBy(fn ($t) => $t->created_at->toDateString());
        $resolvedByDay = $resolved->groupBy(fn ($t) => $t->resolved_at->toDateString());

        $series = [];
        for ($day = $since->copy(); $day->lessThanOrEqualTo(now()); $day->addDay()) {
            $key = $day->toDateString();
            $series[] = [
                'date' => $key,
                'created' => $createdByDay->get($key)?->count() ?? 0,
                'resolved' => $resolvedByDay->get($key)?->count() ?? 0,
            ];
        }

        return $series;
    }

    private function countBy($tickets, callable $key): array
    {
        return $tickets->groupBy($key)
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
            ->values()
            ->all();
    }

    private function agentLeaderboard(Workspace $workspace, User $user, $open, $resolvedInWindow): array
    {
        $members = $workspace->members()->wherePivotIn('role', MemberRole::staffValues())->get();

        if (! $user->isSuperAdmin() && ! $workspace->roleOf($user)?->isOrganizationAdmin()) {
            $teamMemberIds = $workspace->teamsFor($user)
                ->with('members:id')
                ->get()
                ->pluck('members')
                ->flatten()
                ->pluck('id');

            $members = $members->whereIn('id', $teamMemberIds)->values();
        }

        return $members->map(function ($member) use ($open, $resolvedInWindow) {
            $resolved = $resolvedInWindow->where('assignee_id', $member->id);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'role' => $member->pivot->role,
                'openTickets' => $open->where('assignee_id', $member->id)->count(),
                'resolvedLast30Days' => $resolved->count(),
                'avgResolutionMinutes' => $resolved->isEmpty() ? null : round(
                    $resolved->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at))
                ),
            ];
        })->sortByDesc('resolvedLast30Days')->values()->all();
    }
}
