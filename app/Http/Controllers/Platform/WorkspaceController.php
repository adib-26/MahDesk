<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\WorkspaceProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(
        private WorkspaceProvisioner $provisioner,
        private AuditLogger $audit,
    ) {
    }

    public function index(): Response
    {
        Gate::authorize('create', Workspace::class);

        $workspaces = Workspace::query()
            ->withCount(['members', 'tickets'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'created_at']);

        return Inertia::render('platform/workspaces/index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Workspace::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_email' => ['required', 'email', 'max:150'],
        ]);

        $this->provisioner->createWithOwnerInvite(
            $validated['name'],
            $validated['owner_email'],
            $validated['owner_name'],
            $request->user(),
        );

        return back()->with('success', 'Workspace created. An invitation was sent to the organization owner.');
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

        return back()->with('success', "{$name} was deleted.");
    }
}
