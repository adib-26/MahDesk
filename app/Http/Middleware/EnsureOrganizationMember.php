<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API counterpart of EnsureWorkspaceAccess: the caller must belong to the
 * {organization} in the URL. Pass "staff" to also reject customer accounts.
 *
 * Unknown organizations and non-members both get 404 so the API does not
 * reveal which organization slugs exist.
 */
class EnsureOrganizationMember
{
    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $organization = $request->route('organization');
        abort_unless($organization instanceof Workspace, 404, 'Organization not found.');

        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            $role = $organization->roleOf($user);

            abort_if($role === null, 404, 'Organization not found.');
            abort_if($level === 'staff' && ! $role->can(Permission::AccessDesk), 403, 'This endpoint is only available to support staff.');
        }

        return $next($request);
    }
}
