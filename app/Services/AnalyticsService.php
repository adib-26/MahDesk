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

        $totalTickets = Ticket::query()
            ->visibleTo($user, $workspace)
            ->count();

        $recent = Ticket::query()
            ->visibleTo($user, $workspace)
            ->where(fn ($q) => $q->where('created_at', '>=', $since)->orWhere('resolved_at', '>=', $since))
            ->get([
                'id', 'status', 'priority', 'channel', 'category', 'assignee_id', 'created_at',
                'first_responded_at', 'first_response_due_at', 'resolved_at', 'resolution_due_at',
            ]);

        $open = Ticket::query()
            ->visibleTo($user, $workspace)
            ->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->get([
                'id', 'status', 'priority', 'channel', 'assignee_id', 'created_at',
                'first_responded_at', 'first_response_due_at', 'resolved_at', 'resolution_due_at',
            ]);

        $createdInWindow = $recent->filter(fn ($t) => $t->created_at >= $since);
        $resolvedInWindow = $recent->filter(fn ($t) => $t->resolved_at && $t->resolved_at >= $since);

        $responded = $createdInWindow->filter(fn ($t) => $t->first_responded_at);
        $avgFirstResponseMinutes = $responded->isEmpty() ? null : round(
            $responded->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_responded_at))
        );

        $avgResolutionMinutes = $resolvedInWindow->isEmpty() ? null : round(
            $resolvedInWindow->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at))
        );

        $rated = Ticket::query()
            ->visibleTo($user, $workspace)
            ->whereNotNull('satisfaction_rating')
            ->where('satisfaction_rated_at', '>=', $since)
            ->get(['id', 'assignee_id', 'satisfaction_rating']);

        $withSla = $open
            ->merge($recent)
            ->unique('id')
            ->filter(fn ($t) => $t->first_response_due_at || $t->resolution_due_at);

        $slaBreaches = $withSla
            ->filter(fn ($t) => $t->first_response_breached || $t->resolution_breached)
            ->count();

        $slaCompliance = $withSla->isEmpty()
            ? null
            : (int) round(($withSla->count() - $slaBreaches) / $withSla->count() * 100);

        return [
            'kpis' => [
                'totalTickets' => $totalTickets,
                'open' => $open->count(),
                'resolvedTickets' => $resolvedInWindow->count(),
                'slaBreaches' => $slaBreaches,
                'unassigned' => $open->whereNull('assignee_id')->count(),
                'breachingSoon' => $open->filter(function ($t) {
                    $minutesUntilDue = $t->first_response_due_at
                        ? now()->diffInMinutes($t->first_response_due_at, false)
                        : null;

                    return $t->first_response_due_at
                        && ! $t->first_responded_at
                        && $minutesUntilDue >= 0
                        && $minutesUntilDue <= 120;
                })->count(),
                'createdToday' => $createdInWindow->filter(fn ($t) => $t->created_at->isToday())->count(),
                'resolvedThisWeek' => $resolvedInWindow->filter(fn ($t) => $t->resolved_at->greaterThanOrEqualTo(now()->startOfWeek()))->count(),
                'avgFirstResponseMinutes' => $avgFirstResponseMinutes,
                'avgResolutionMinutes' => $avgResolutionMinutes,
                'slaCompliance' => $slaCompliance,
                'customerSatisfaction' => $rated->isEmpty()
                    ? null
                    : (int) round($rated->avg('satisfaction_rating') / 5 * 100),
                'csatResponses' => $rated->count(),
            ],
            'series' => $this->dailySeries($createdInWindow, $resolvedInWindow, $since),
            'byStatus' => $this->countBy($open, fn ($t) => $t->status->value),
            'byPriority' => $this->countBy($open, fn ($t) => $t->priority->value),
            'byPriorityInPeriod' => $this->countBy($createdInWindow, fn ($t) => $t->priority->value),
            'byChannel' => $this->countBy($createdInWindow, fn ($t) => $t->channel->value),
            'byCategory' => $this->countBy($createdInWindow, fn ($t) => $t->category->value),
            'agents' => $this->agentLeaderboard($workspace, $user, $open, $createdInWindow, $resolvedInWindow, $rated),
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

    private function agentLeaderboard(Workspace $workspace, User $user, $open, $createdInWindow, $resolvedInWindow, $rated): array
    {
        $members = $workspace->members()->wherePivotIn('role', MemberRole::staffValues())->get();

        if ($workspace->roleOf($user) === MemberRole::Agent) {
            $members = $members->where('id', $user->getKey())->values();
        } elseif (! $user->isSuperAdmin() && ! $workspace->roleOf($user)?->isOrganizationAdmin()) {
            $teamMemberIds = $workspace->teamsFor($user)
                ->with('members:id')
                ->get()
                ->pluck('members')
                ->flatten()
                ->pluck('id');

            $members = $members->whereIn('id', $teamMemberIds)->values();
        }

        return $members->map(function ($member) use ($open, $createdInWindow, $resolvedInWindow, $rated) {
            // "Assigned" is current queue workload, so it intentionally excludes
            // terminal tickets. Other activity metrics share the 30-day window.
            $assigned = $open->where('assignee_id', $member->id);
            $resolved = $resolvedInWindow->where('assignee_id', $member->id);
            // Ticket messages do not record which agent made the first response.
            // Attribute the response to the ticket's current assignee instead.
            $responded = $createdInWindow
                ->where('assignee_id', $member->id)
                ->filter(fn ($t) => $t->first_responded_at);
            $withResolutionSla = $resolved->filter(fn ($t) => $t->resolution_due_at);
            $resolutionSlaBreaches = $withResolutionSla
                ->filter(fn ($t) => $t->resolved_at->greaterThan($t->resolution_due_at))
                ->count();
            $ratings = $rated->where('assignee_id', $member->id);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'role' => $member->pivot->role,
                // Kept as a compatibility alias for the existing dashboard.
                'openTickets' => $assigned->count(),
                'ticketsAssigned' => $assigned->count(),
                'resolvedLast30Days' => $resolved->count(),
                'ticketsResolved' => $resolved->count(),
                'avgResponseMinutes' => $responded->isEmpty() ? null : round(
                    $responded->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_responded_at))
                ),
                'avgResolutionMinutes' => $resolved->isEmpty() ? null : round(
                    $resolved->avg(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at))
                ),
                // This intentionally measures only resolution targets on tickets
                // resolved in the reporting period, not first-response SLAs.
                'slaCompliance' => $withResolutionSla->isEmpty()
                    ? null
                    : (int) round(($withResolutionSla->count() - $resolutionSlaBreaches) / $withResolutionSla->count() * 100),
                'customerRating' => $ratings->isEmpty() ? null : round($ratings->avg('satisfaction_rating'), 1),
                'customerRatingResponses' => $ratings->count(),
            ];
        })->sortByDesc('resolvedLast30Days')->values()->all();
    }
}
