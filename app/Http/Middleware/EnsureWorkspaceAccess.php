<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        abort_unless($workspace instanceof Workspace, 404);

        $user = $request->user();
        $role = $user->isSuperAdmin() ? null : $workspace->roleOf($user);

        abort_unless($user->isSuperAdmin() || $role !== null, 403, 'You are not a member of this workspace.');

        $request->attributes->set('memberRole', $user->isSuperAdmin() ? 'super_admin' : $role);

        Inertia::share([
            'currentWorkspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
            ],
            'memberRole' => $user->isSuperAdmin() ? 'super_admin' : $role->value,
        ]);

        return $next($request);
    }
}
