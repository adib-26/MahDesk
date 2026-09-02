<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SlaPolicyController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        Gate::authorize('manageWorkspace', $workspace);

        return Inertia::render('desk/settings/sla', [
            'policies' => $workspace->slaPolicies()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);

        $validated = $this->validated($request);

        $workspace->slaPolicies()->create($validated);

        $this->ensureSingleDefault($workspace, $validated);

        return back()->with('success', 'SLA policy created.');
    }

    public function update(Request $request, Workspace $workspace, SlaPolicy $slaPolicy): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);
        abort_unless($slaPolicy->workspace_id === $workspace->id, 404);

        $validated = $this->validated($request);

        $slaPolicy->update($validated);

        $this->ensureSingleDefault($workspace, $validated, $slaPolicy);

        return back()->with('success', 'SLA policy updated.');
    }

    public function destroy(Workspace $workspace, SlaPolicy $slaPolicy): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);
        abort_unless($slaPolicy->workspace_id === $workspace->id, 404);

        $slaPolicy->delete();

        return back()->with('success', 'SLA policy deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'first_response_minutes' => ['required', 'integer', 'min:5', 'max:20160'],
            'resolution_minutes' => ['required', 'integer', 'min:15', 'max:80640'],
            'is_default' => ['boolean'],
        ]);
    }

    private function ensureSingleDefault(Workspace $workspace, array $validated, ?SlaPolicy $current = null): void
    {
        if (! empty($validated['is_default'])) {
            $keepId = $current?->id ?? $workspace->slaPolicies()->where('is_default', true)->latest('id')->value('id');

            $workspace->slaPolicies()
                ->where('id', '!=', $keepId)
                ->update(['is_default' => false]);
        }
    }
}
