<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\Workspace;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function create(): RedirectResponse
    {
        Gate::authorize('create', Workspace::class);

        return redirect()->route('platform.workspaces.index');
    }

    public function store(): RedirectResponse
    {
        Gate::authorize('create', Workspace::class);

        return redirect()->route('platform.workspaces.index');
    }

    public function edit(Workspace $workspace): Response
    {
        Gate::authorize('manageWorkspace', $workspace);

        return Inertia::render('desk/settings/general', [
            'isOwner' => $workspace->roleOf(request()->user()) === MemberRole::Owner,
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace->update($validated);

        $this->audit->record('workspace.updated', $request->user(), $workspace, $workspace, $validated);

        return back()->with('success', 'Workspace updated.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('delete', $workspace);

        $name = $workspace->name;
        $workspace->delete();

        $this->audit->record(
            'workspace.deleted',
            $request->user(),
            null,
            null,
            ['name' => $name],
        );

        return redirect()->route('dashboard')->with('success', 'Workspace deleted.');
    }
}
