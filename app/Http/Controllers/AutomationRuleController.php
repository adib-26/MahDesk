<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Enums\Permission;
use App\Models\Workspace;
use App\Services\AutomationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function __construct(private AutomationEngine $automationEngine)
    {
    }

    public function index(Workspace $workspace): Response
    {
        Gate::authorize(Permission::ManageAutomations->value, $workspace);

        $staff = $workspace->members()
            ->wherePivotIn('role', MemberRole::staffValues())
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->pivot->role,
            ]);

        $rules = $workspace->automationRules()
            ->withCount('runs')
            ->withMax('runs', 'created_at')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $recentRuns = AutomationRuleRun::query()
            ->whereIn('automation_rule_id', $rules->modelKeys())
            ->with(['rule:id,name', 'ticket:id,number,subject'])
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (AutomationRuleRun $run) => [
                'id' => $run->id,
                'rule_name' => $run->rule?->name,
                'event' => $run->event,
                'actions' => $run->actions,
                'ticket_id' => $run->ticket?->id,
                'ticket_number' => $run->ticket?->number,
                'ticket_subject' => $run->ticket?->subject,
                'created_at' => $run->created_at?->toIso8601String(),
            ]);

        return Inertia::render('desk/settings/automations', [
            'rules' => $rules,
            'recentRuns' => $recentRuns,
            'agents' => $staff,
            'teams' => $workspace->teams()->orderBy('name')->get(['id', 'name']),
            'slaPolicies' => $workspace->slaPolicies()->orderBy('name')->get(['id', 'name', 'resolution_minutes']),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize(Permission::ManageAutomations->value, $workspace);

        $validated = $this->validated($request, $workspace);

        $workspace->automationRules()->create([
            ...$validated,
            'position' => (int) $workspace->automationRules()->max('position') + 1,
        ]);

        return back()->with('success', 'Automation rule created.');
    }

    public function update(Request $request, Workspace $workspace, AutomationRule $rule): RedirectResponse
    {
        Gate::authorize(Permission::ManageAutomations->value, $workspace);
        abort_unless($rule->workspace_id === $workspace->id, 404);

        if ($request->has('is_active') && ! $request->has('name')) {
            $rule->update(['is_active' => $request->boolean('is_active')]);

            return back()->with('success', $rule->is_active ? 'Rule enabled.' : 'Rule disabled.');
        }

        $rule->update($this->validated($request, $workspace));

        return back()->with('success', 'Automation rule updated.');
    }

    public function destroy(Workspace $workspace, AutomationRule $rule): RedirectResponse
    {
        Gate::authorize(Permission::ManageAutomations->value, $workspace);
        abort_unless($rule->workspace_id === $workspace->id, 404);

        $rule->delete();

        return back()->with('success', 'Automation rule deleted.');
    }

    public function runEscalations(Workspace $workspace): RedirectResponse
    {
        Gate::authorize(Permission::ManageAutomations->value, $workspace);

        $stats = $this->automationEngine->runEscalations($workspace);

        $message = $stats['triggered'] > 0
            ? sprintf('Evaluated %d open ticket(s); %d escalation rule(s) triggered.', $stats['evaluated'], $stats['triggered'])
            : sprintf('Evaluated %d open ticket(s); no new escalations matched.', $stats['evaluated']);

        return back()->with('success', $message);
    }

    private function validated(Request $request, Workspace $workspace): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'event' => ['required', Rule::in(AutomationEngine::EVENTS)],
            'is_active' => ['boolean'],
            'conditions' => [
                'array',
                // Update and escalation rules with no conditions would match every ticket.
                Rule::requiredIf(fn () => $request->input('event') !== AutomationEngine::EVENT_TICKET_CREATED),
                Rule::when($request->input('event') !== AutomationEngine::EVENT_TICKET_CREATED, ['min:1']),
            ],
            'conditions.*.field' => ['required', Rule::in(AutomationEngine::CONDITION_FIELDS)],
            'conditions.*.operator' => ['required', Rule::in(AutomationEngine::CONDITION_OPERATORS)],
            'conditions.*.value' => ['required', 'string', 'max:200'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', Rule::in(AutomationEngine::ACTION_TYPES)],
            'actions.*.value' => ['required', 'string', 'max:2000'],
        ], [
            'conditions.required' => 'Add at least one condition for update and scheduled rules.',
            'conditions.min' => 'Add at least one condition for update and scheduled rules.',
        ]);

        $validator->after(function ($validator) use ($request, $workspace) {
            foreach ((array) $request->input('conditions', []) as $i => $condition) {
                if ($error = $this->conditionError((array) $condition, $request->input('event'))) {
                    $validator->errors()->add("conditions.{$i}.value", $error);
                }
            }

            foreach ((array) $request->input('actions', []) as $i => $action) {
                if ($error = $this->actionError((array) $action, $workspace)) {
                    $validator->errors()->add("actions.{$i}.value", $error);
                }
            }
        });

        return $validator->validate();
    }

    private function conditionError(array $condition, ?string $event): ?string
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = (string) ($condition['value'] ?? '');

        $enum = match ($field) {
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'category' => TicketCategory::class,
            'channel' => TicketChannel::class,
            default => null,
        };

        if ($enum !== null) {
            if (! in_array($operator, ['equals', 'not_equals'], true)) {
                return 'Use "is" or "is not" for this field.';
            }

            return $enum::tryFrom($value) ? null : 'Choose a valid value.';
        }

        if (in_array($field, AutomationEngine::TIME_FIELDS, true)) {
            if ($event === AutomationEngine::EVENT_TICKET_CREATED) {
                return 'Time-based conditions only work on scheduled or update rules.';
            }

            return is_numeric($value) && (float) $value >= 0 ? null : 'Enter a number of hours.';
        }

        if (in_array($field, ['team_id', 'assignee_id'], true) && ! in_array($operator, ['equals', 'not_equals'], true)) {
            return 'Use "is" or "is not" for this field.';
        }

        return null;
    }

    private function actionError(array $action, Workspace $workspace): ?string
    {
        $value = (string) ($action['value'] ?? '');

        return match ($action['type'] ?? null) {
            'set_priority' => TicketPriority::tryFrom($value) ? null : 'Choose a valid priority.',
            'set_status' => TicketStatus::tryFrom($value) ? null : 'Choose a valid status.',
            'set_category' => TicketCategory::tryFrom($value) ? null : 'Choose a valid category.',
            'assign_team' => $workspace->teams()
                ->where(fn ($q) => ctype_digit($value)
                    ? $q->whereKey((int) $value)
                    : $q->whereRaw('lower(name) = ?', [mb_strtolower($value)]))
                ->exists() ? null : 'Choose a team in this workspace.',
            'assign_agent' => ctype_digit($value) && $workspace->members()
                ->wherePivotIn('role', MemberRole::staffValues())
                ->where('users.id', (int) $value)
                ->exists() ? null : 'Choose a support team member.',
            'escalate_to_manager' => $value === 'auto' || (ctype_digit($value) && $workspace->members()
                ->wherePivotIn('role', [MemberRole::Owner->value, MemberRole::Admin->value, MemberRole::Manager->value])
                ->where('users.id', (int) $value)
                ->exists()) ? null : 'Choose a manager, admin or owner.',
            'set_sla_hours' => is_numeric($value) && (float) $value >= 0.25 && (float) $value <= 8760
                ? null : 'Enter between 0.25 and 8760 hours.',
            'set_sla_policy' => ctype_digit($value) && $workspace->slaPolicies()->whereKey((int) $value)->exists()
                ? null : 'Choose an SLA policy in this workspace.',
            'send_notification' => in_array($value, AutomationEngine::NOTIFICATION_TARGETS, true)
                || filter_var($value, FILTER_VALIDATE_EMAIL)
                || (ctype_digit($value) && $workspace->members()
                    ->wherePivotIn('role', MemberRole::staffValues())
                    ->where('users.id', (int) $value)
                    ->exists())
                ? null : 'Choose who should be notified.',
            'add_tag' => mb_strlen($value) <= 50 ? null : 'Tag names are limited to 50 characters.',
            default => null,
        };
    }
}
