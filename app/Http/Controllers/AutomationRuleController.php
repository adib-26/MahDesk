<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        Gate::authorize('manageWorkspace', $workspace);

        return Inertia::render('desk/settings/automations', [
            'rules' => $workspace->automationRules()->orderBy('position')->get(),
            'agents' => $workspace->members()->get(['users.id', 'users.name']),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);

        $validated = $this->validated($request);

        $workspace->automationRules()->create([
            ...$validated,
            'position' => (int) $workspace->automationRules()->max('position') + 1,
        ]);

        return back()->with('success', 'Automation rule created.');
    }

    public function update(Request $request, Workspace $workspace, AutomationRule $rule): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);
        abort_unless($rule->workspace_id === $workspace->id, 404);

        if ($request->has('is_active') && ! $request->has('name')) {
            $rule->update(['is_active' => $request->boolean('is_active')]);

            return back()->with('success', $rule->is_active ? 'Rule enabled.' : 'Rule disabled.');
        }

        $rule->update($this->validated($request));

        return back()->with('success', 'Automation rule updated.');
    }

    public function destroy(Workspace $workspace, AutomationRule $rule): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);
        abort_unless($rule->workspace_id === $workspace->id, 404);

        $rule->delete();

        return back()->with('success', 'Automation rule deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'event' => ['required', Rule::in(['ticket_created', 'ticket_updated'])],
            'is_active' => ['boolean'],
            'conditions' => ['array'],
            'conditions.*.field' => ['required', Rule::in(['subject', 'priority', 'status', 'channel', 'contact_email'])],
            'conditions.*.operator' => ['required', Rule::in(['equals', 'not_equals', 'contains'])],
            'conditions.*.value' => ['required', 'string', 'max:200'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', Rule::in(['set_priority', 'set_status', 'assign_agent', 'add_tag', 'add_note'])],
            'actions.*.value' => ['required', 'string', 'max:2000'],
        ]);
    }
}
