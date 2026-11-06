<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AutomationRuleNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AutomationEngine
{
    public const EVENT_TICKET_CREATED = 'ticket_created';

    public const EVENT_TICKET_UPDATED = 'ticket_updated';

    public const EVENT_TICKET_ESCALATION = 'ticket_escalation';

    public const EVENTS = [
        self::EVENT_TICKET_CREATED,
        self::EVENT_TICKET_UPDATED,
        self::EVENT_TICKET_ESCALATION,
    ];

    public const CONDITION_FIELDS = [
        'subject',
        'priority',
        'status',
        'category',
        'channel',
        'team_id',
        'assignee_id',
        'contact_email',
        'unresolved_hours',
        'unanswered_hours',
        'hours_until_sla_due',
    ];

    public const TIME_FIELDS = ['unresolved_hours', 'unanswered_hours', 'hours_until_sla_due'];

    public const NOTIFICATION_TARGETS = ['assigned_team', 'assignee', 'managers', 'admins'];

    public const CONDITION_OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'greater_than_or_equal',
        'less_than_or_equal',
    ];

    public const ACTION_TYPES = [
        'set_priority',
        'set_status',
        'set_category',
        'assign_team',
        'assign_agent',
        'escalate_to_manager',
        'set_sla_hours',
        'set_sla_policy',
        'send_notification',
        'add_tag',
        'add_note',
    ];

    public function __construct(private SlaService $slaService)
    {
    }

    /**
     * Run all active rules for the given event against the ticket.
     * Conditions within a rule are AND-ed; rules run in position order.
     *
     * For `ticket_updated`, pass a snapshot of the ticket taken before the
     * change (`clone $ticket`). Rules then fire only when their conditions
     * become true with this update, not on every later update or reply
     * while they stay true. This also stops rules from re-triggering
     * themselves.
     *
     * @return list<string> Summaries of rules that applied actions
     */
    public function run(Ticket $ticket, string $event, ?Ticket $before = null): array
    {
        return $this->evaluate($ticket, $event, $this->activeRules($ticket->workspace_id, $event), $before);
    }

    /**
     * @param  Collection<int, AutomationRule>  $rules
     * @param  array<string, true>|null  $alreadyRan  "ruleId:ticketId" keys of escalation runs already recorded
     * @return list<string>
     */
    private function evaluate(Ticket $ticket, string $event, Collection $rules, ?Ticket $before = null, ?array $alreadyRan = null): array
    {
        if ($rules->isEmpty()) {
            return [];
        }

        if ($event === self::EVENT_TICKET_ESCALATION && $alreadyRan === null) {
            $alreadyRan = $this->recordedRuns($rules, collect([$ticket->id]));
        }

        $triggered = [];

        foreach ($rules as $rule) {
            if ($event === self::EVENT_TICKET_ESCALATION && isset($alreadyRan["{$rule->id}:{$ticket->id}"])) {
                continue;
            }

            $conditions = $rule->conditions ?? [];

            if (! $this->matches($ticket, $conditions)) {
                continue;
            }

            if ($event === self::EVENT_TICKET_UPDATED && $before !== null && $this->matches($before, $conditions)) {
                continue;
            }

            $applied = $this->applyActions($ticket, $rule, $rule->actions ?? []);

            if ($applied === []) {
                continue;
            }

            $summary = sprintf('Automation "%s" applied: %s', $rule->name, implode(', ', $applied));
            $ticket->logEvent($summary, null, ['rule_id' => $rule->id, 'event' => $event, 'actions' => $applied]);

            AutomationRuleRun::create([
                'automation_rule_id' => $rule->id,
                'ticket_id' => $ticket->id,
                'event' => $event,
                'actions' => $applied,
            ]);

            $triggered[] = $summary;
        }

        return $triggered;
    }

    /**
     * @return Collection<int, AutomationRule>
     */
    private function activeRules(int $workspaceId, string $event): Collection
    {
        return AutomationRule::query()
            ->where('workspace_id', $workspaceId)
            ->where('event', $event)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, AutomationRule>  $rules
     * @param  Collection<int, int>  $ticketIds
     * @return array<string, true>
     */
    private function recordedRuns(Collection $rules, Collection $ticketIds): array
    {
        return AutomationRuleRun::query()
            ->whereIn('automation_rule_id', $rules->pluck('id'))
            ->whereIn('ticket_id', $ticketIds)
            ->get(['automation_rule_id', 'ticket_id'])
            ->mapWithKeys(fn ($run) => ["{$run->automation_rule_id}:{$run->ticket_id}" => true])
            ->all();
    }

    /**
     * Evaluate all time-based (`ticket_escalation`) rules across open tickets.
     *
     * @return array{evaluated: int, triggered: int, summaries: list<string>}
     */
    public function runEscalations(?Workspace $workspace = null): array
    {
        $rulesByWorkspace = AutomationRule::query()
            ->where('event', self::EVENT_TICKET_ESCALATION)
            ->where('is_active', true)
            ->when($workspace, fn ($q) => $q->where('workspace_id', $workspace->id))
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('workspace_id');

        if ($rulesByWorkspace->isEmpty()) {
            return ['evaluated' => 0, 'triggered' => 0, 'summaries' => []];
        }

        $evaluated = 0;
        $triggeredCount = 0;
        $summaries = [];

        $allRules = $rulesByWorkspace->flatten(1);

        Ticket::query()
            ->whereIn('workspace_id', $rulesByWorkspace->keys())
            ->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->with(['workspace', 'contact', 'team', 'assignee'])
            ->chunkById(200, function ($tickets) use ($rulesByWorkspace, $allRules, &$evaluated, &$triggeredCount, &$summaries) {
                $alreadyRan = $this->recordedRuns($allRules, $tickets->pluck('id'));

                foreach ($tickets as $ticket) {
                    $evaluated++;
                    $applied = $this->evaluate(
                        $ticket,
                        self::EVENT_TICKET_ESCALATION,
                        $rulesByWorkspace->get($ticket->workspace_id),
                        null,
                        $alreadyRan,
                    );

                    if ($applied !== []) {
                        $triggeredCount += count($applied);
                        array_push($summaries, ...$applied);
                    }
                }
            });

        return [
            'evaluated' => $evaluated,
            'triggered' => $triggeredCount,
            'summaries' => $summaries,
        ];
    }

    public function matches(Ticket $ticket, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $value = (string) ($condition['value'] ?? '');

            if (in_array($field, self::TIME_FIELDS, true)) {
                if (! $this->matchesNumericTimeCondition($ticket, $field, $operator, (float) $value)) {
                    return false;
                }

                continue;
            }

            if ($field === 'assignee_id' && Str::lower($value) === 'unassigned') {
                $isUnassigned = $ticket->assignee_id === null;
                $ok = $operator === 'not_equals' ? ! $isUnassigned : $isUnassigned;
                if (! $ok) {
                    return false;
                }

                continue;
            }

            $actual = match ($field) {
                'subject' => $ticket->subject,
                'priority' => $ticket->priority->value,
                'status' => $ticket->status->value,
                'category' => $ticket->category->value,
                'channel' => $ticket->channel->value,
                'team_id' => $ticket->team_id !== null ? (string) $ticket->team_id : '',
                'assignee_id' => $ticket->assignee_id !== null ? (string) $ticket->assignee_id : '',
                'contact_email' => $ticket->contact?->email ?? '',
                default => null,
            };

            if ($actual === null) {
                return false;
            }

            $ok = match ($operator) {
                'equals' => Str::lower($actual) === Str::lower($value),
                'not_equals' => Str::lower($actual) !== Str::lower($value),
                'contains' => Str::contains(Str::lower($actual), Str::lower($value)),
                'greater_than_or_equal' => is_numeric($actual) && is_numeric($value) && (float) $actual >= (float) $value,
                'less_than_or_equal' => is_numeric($actual) && is_numeric($value) && (float) $actual <= (float) $value,
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    private function matchesNumericTimeCondition(Ticket $ticket, string $field, string $operator, float $thresholdHours): bool
    {
        if ($ticket->status->isFinished() || $ticket->resolved_at !== null) {
            return false;
        }

        $actualHours = match ($field) {
            'unresolved_hours' => $ticket->created_at ? $ticket->created_at->diffInMinutes(now()) / 60 : null,
            'unanswered_hours' => $ticket->first_responded_at === null && $ticket->created_at
                ? $ticket->created_at->diffInMinutes(now()) / 60
                : null,
            'hours_until_sla_due' => $ticket->resolution_due_at
                ? now()->diffInMinutes($ticket->resolution_due_at, false) / 60
                : null,
            default => null,
        };

        if ($actualHours === null) {
            return false;
        }

        return match ($operator) {
            'greater_than_or_equal', 'equals' => $actualHours >= $thresholdHours,
            'less_than_or_equal' => $actualHours <= $thresholdHours,
            'not_equals' => abs($actualHours - $thresholdHours) > 0.01,
            default => false,
        };
    }

    /**
     * @return list<string> human readable descriptions of the applied actions
     */
    private function applyActions(Ticket $ticket, AutomationRule $rule, array $actions): array
    {
        $applied = [];
        $priorityChanged = false;
        $slaOverridden = false;
        $pendingNotifications = [];

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            switch ($type) {
                case 'set_priority':
                    $priority = TicketPriority::tryFrom((string) $value);
                    if ($priority && $ticket->priority !== $priority) {
                        $ticket->priority = $priority;
                        $priorityChanged = true;
                        $applied[] = "priority set to {$priority->value}";
                    }
                    break;

                case 'set_status':
                    $status = TicketStatus::tryFrom((string) $value);
                    if ($status && $ticket->status !== $status) {
                        $ticket->status = $status;
                        if ($status === TicketStatus::Resolved) {
                            $ticket->resolved_at ??= now();
                        } elseif ($status === TicketStatus::Closed) {
                            $ticket->resolved_at ??= now();
                            $ticket->closed_at ??= now();
                        } else {
                            $ticket->resolved_at = null;
                            $ticket->closed_at = null;
                        }
                        $applied[] = "status set to {$status->value}";
                    }
                    break;

                case 'set_category':
                    $category = TicketCategory::tryFrom((string) $value);
                    if ($category && $ticket->category !== $category) {
                        $ticket->category = $category;
                        $applied[] = "category set to {$category->value}";
                    }
                    break;

                case 'assign_team':
                    $team = $this->resolveTeam($ticket->workspace, (string) $value);
                    if ($team && $ticket->team_id !== $team->id) {
                        $ticket->team_id = $team->id;
                        $ticket->setRelation('team', $team);
                        $applied[] = "assigned to {$team->name}";
                    }
                    break;

                case 'assign_agent':
                    $member = $ticket->workspace->members()
                        ->wherePivotIn('role', MemberRole::staffValues())
                        ->where('users.id', (int) $value)
                        ->first();
                    if ($member && $ticket->assignee_id !== $member->id) {
                        $ticket->assignee_id = $member->id;
                        $ticket->setRelation('assignee', $member);
                        $applied[] = "assigned to {$member->name}";
                    }
                    break;

                case 'escalate_to_manager':
                    $manager = $this->resolveEscalationManager($ticket, (string) $value);
                    if ($manager) {
                        $changed = $ticket->assignee_id !== $manager->id;
                        $ticket->assignee_id = $manager->id;
                        $ticket->setRelation('assignee', $manager);
                        if (! in_array($ticket->priority, [TicketPriority::High, TicketPriority::Urgent], true)) {
                            $ticket->priority = TicketPriority::High;
                            $priorityChanged = true;
                        }
                        $applied[] = $changed
                            ? "escalated to {$manager->name}"
                            : "escalation confirmed with {$manager->name}";
                        $pendingNotifications[] = [
                            'recipients' => collect([$manager]),
                            'summary' => "Ticket #{$ticket->number} ({$ticket->subject}) was escalated to you by workflow \"{$rule->name}\".",
                        ];
                    }
                    break;

                case 'set_sla_hours':
                    $hours = max(0.25, (float) $value);
                    $minutes = (int) round($hours * 60);
                    $base = $ticket->created_at ?? now();
                    $firstResponseMinutes = min($minutes, max(15, (int) round($minutes / 2)));
                    $ticket->first_response_due_at = $base->copy()->addMinutes($firstResponseMinutes);
                    $ticket->resolution_due_at = $base->copy()->addMinutes($minutes);
                    $slaOverridden = true;
                    $label = rtrim(rtrim(number_format($hours, 2), '0'), '.');
                    $applied[] = "SLA set to {$label} hours";
                    break;

                case 'set_sla_policy':
                    $policy = $ticket->workspace->slaPolicies()->whereKey($value)->first();
                    if ($policy) {
                        $base = $ticket->created_at ?? now();
                        $ticket->sla_policy_id = $policy->id;
                        $ticket->first_response_due_at = $base->copy()->addMinutes($policy->first_response_minutes);
                        $ticket->resolution_due_at = $base->copy()->addMinutes($policy->resolution_minutes);
                        $slaOverridden = true;
                        $applied[] = "SLA policy set to {$policy->name}";
                    }
                    break;

                case 'send_notification':
                    // Recipients are resolved after the ticket is saved, so a
                    // team assignment in this same rule is taken into account.
                    $pendingNotifications[] = [
                        'target' => (string) ($value ?: 'assigned_team'),
                        'summary' => null,
                    ];
                    break;

                case 'add_tag':
                    $tag = Tag::firstOrCreate(
                        ['workspace_id' => $ticket->workspace_id, 'name' => $value],
                    );
                    $changes = $ticket->tags()->syncWithoutDetaching([$tag->id]);
                    if ($changes['attached'] !== []) {
                        $applied[] = "tagged \"{$value}\"";
                    }
                    break;

                case 'add_note':
                    $ticket->messages()->create([
                        'kind' => 'note',
                        'body' => (string) $value,
                    ]);
                    $applied[] = 'internal note added';
                    break;
            }
        }

        if ($ticket->isDirty()) {
            $ticket->save();
        }

        if ($priorityChanged && ! $slaOverridden) {
            $this->slaService->apply($ticket);
        }

        if ($pendingNotifications !== []) {
            $defaultSummary = $applied !== []
                ? sprintf('Workflow "%s" executed on ticket #%d (%s): %s.', $rule->name, $ticket->number, $ticket->subject, implode(', ', $applied))
                : sprintf('Workflow "%s" matched ticket #%d (%s).', $rule->name, $ticket->number, $ticket->subject);

            foreach ($pendingNotifications as $item) {
                $target = $item['target'] ?? null;
                $summary = $item['summary'] ?? $defaultSummary;

                if ($target !== null && filter_var($target, FILTER_VALIDATE_EMAIL)) {
                    Notification::route('mail', $target)
                        ->notify(new AutomationRuleNotification($ticket, $rule, $summary));
                    $applied[] = "notification sent ({$target})";

                    continue;
                }

                $recipients = ($item['recipients'] ?? $this->resolveNotificationRecipients($ticket, (string) $target))
                    ->unique('id')
                    ->values();

                if ($recipients->isEmpty()) {
                    continue;
                }

                Notification::send($recipients, new AutomationRuleNotification($ticket, $rule, $summary));

                if ($target !== null) {
                    $applied[] = sprintf('notification sent (%s, %d recipient%s)', str_replace('_', ' ', $target), $recipients->count(), $recipients->count() === 1 ? '' : 's');
                }
            }
        }

        return $applied;
    }

    private function resolveTeam(Workspace $workspace, string $value): ?\App\Models\Team
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return $workspace->teams()->whereKey((int) $value)->first();
        }

        return $workspace->teams()
            ->whereRaw('lower(name) = ?', [Str::lower($value)])
            ->first();
    }

    private function resolveEscalationManager(Ticket $ticket, string $value): ?User
    {
        $workspace = $ticket->workspace;

        if ($value !== '' && $value !== 'auto' && ctype_digit($value)) {
            $specific = $workspace->members()
                ->wherePivotIn('role', [MemberRole::Owner->value, MemberRole::Admin->value, MemberRole::Manager->value])
                ->where('users.id', (int) $value)
                ->first();
            if ($specific) {
                return $specific;
            }
        }

        if ($ticket->team_id !== null) {
            $teamManager = $workspace->members()
                ->wherePivot('role', MemberRole::Manager->value)
                ->whereHas('teams', fn ($q) => $q->where('teams.id', $ticket->team_id))
                ->orderBy('users.id')
                ->first();
            if ($teamManager) {
                return $teamManager;
            }
        }

        return $workspace->members()->wherePivot('role', MemberRole::Manager->value)->orderBy('users.id')->first()
            ?? $workspace->members()->wherePivot('role', MemberRole::Admin->value)->orderBy('users.id')->first()
            ?? $workspace->members()->wherePivot('role', MemberRole::Owner->value)->orderBy('users.id')->first();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveNotificationRecipients(Ticket $ticket, string $target): Collection
    {
        $workspace = $ticket->workspace;
        $staff = fn () => $workspace->members()->wherePivotIn('role', MemberRole::staffValues());

        return match ($target) {
            'assignee' => $ticket->assignee
                ? collect([$ticket->assignee])
                : $this->resolveNotificationRecipients($ticket, 'assigned_team'),
            'assigned_team' => $ticket->team_id !== null
                ? $staff()->whereHas('teams', fn ($q) => $q->where('teams.id', $ticket->team_id))->get()
                : $workspace->members()->wherePivotIn('role', [MemberRole::Owner->value, MemberRole::Admin->value, MemberRole::Manager->value])->get(),
            'managers' => $this->managersOrFallback($workspace),
            'admins' => $workspace->members()->wherePivotIn('role', [MemberRole::Owner->value, MemberRole::Admin->value])->get(),
            default => ctype_digit($target)
                ? $staff()->where('users.id', (int) $target)->get()
                : collect(),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function managersOrFallback(Workspace $workspace): Collection
    {
        $managers = $workspace->members()->wherePivot('role', MemberRole::Manager->value)->get();

        return $managers->isNotEmpty()
            ? $managers
            : $workspace->members()->wherePivotIn('role', [MemberRole::Owner->value, MemberRole::Admin->value])->get();
    }
}
