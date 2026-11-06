<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\Workspace;
use App\Services\AuditLogger;
use App\Services\InboundEmail\TestmailClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'email' => [
                'inboundAddress' => $workspace->inboundEmailAddress(),
                'supportEmail' => $workspace->support_email,
                'provider' => config('services.inbound_email.driver'),
                'configured' => config('services.inbound_email.driver') === 'testmail'
                    ? app(TestmailClient::class)->isConfigured()
                    : filled(config('services.inbound_email.secret')),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageWorkspace', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'support_email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('workspaces', 'support_email')->ignore($workspace->id),
            ],
        ]);

        $validated['support_email'] = isset($validated['support_email'])
            ? Str::lower($validated['support_email'])
            : null;

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
