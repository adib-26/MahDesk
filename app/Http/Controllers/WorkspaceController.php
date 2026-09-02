<?php

namespace App\Http\Controllers;

use App\Enums\MemberRole;
use App\Models\SlaPolicy;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('workspaces/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace = DB::transaction(function () use ($validated, $request) {
            $workspace = Workspace::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
            ]);

            $workspace->members()->attach($request->user()->id, ['role' => MemberRole::Owner->value]);

            $workspace->teams()->create(['name' => 'General Support'])
                ->members()
                ->attach($request->user()->id);

            SlaPolicy::create([
                'workspace_id' => $workspace->id,
                'name' => 'Standard support',
                'description' => 'Default policy applied to every ticket.',
                'priority' => null,
                'first_response_minutes' => 8 * 60,
                'resolution_minutes' => 72 * 60,
                'is_default' => true,
            ]);

            SlaPolicy::create([
                'workspace_id' => $workspace->id,
                'name' => 'Urgent response',
                'description' => 'Tight targets for urgent tickets.',
                'priority' => 'urgent',
                'first_response_minutes' => 60,
                'resolution_minutes' => 8 * 60,
            ]);

            return $workspace;
        });

        return redirect()
            ->route('desk.dashboard', $workspace)
            ->with('success', 'Workspace created. Welcome aboard!');
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

        return back()->with('success', 'Workspace updated.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('delete', $workspace);

        $workspace->delete();

        return redirect()->route('dashboard')->with('success', 'Workspace deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
