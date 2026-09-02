<?php

namespace App\Policies;

use App\Enums\MemberRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Platform administrators are intentionally not represented in the
     * workspace_members pivot. The User domain object supplies this method as
     * part of the platform-authentication layer.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $this->isPlatformSuperAdmin($user) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->isPlatformSuperAdmin($user) || $workspace->hasMember($user);
    }

    /**
     * Keep workspace creation compatible with the current onboarding flow.
     * Controllers can impose more restrictive product rules if required.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->isPlatformSuperAdmin($user)
            || $workspace->roleOf($user)?->canManageWorkspace() === true;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $this->isPlatformSuperAdmin($user)
            || $workspace->roleOf($user) === MemberRole::Owner;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->isPlatformSuperAdmin($user)
            || $workspace->roleOf($user)?->canManageMembers() === true;
    }

    public function manageTeams(User $user, Workspace $workspace): bool
    {
        return $this->isPlatformSuperAdmin($user)
            || $workspace->roleOf($user)?->canManageTeams() === true;
    }

    public function viewAnalytics(User $user, Workspace $workspace): bool
    {
        if ($this->isPlatformSuperAdmin($user)) {
            return true;
        }

        $role = $workspace->roleOf($user);

        if (! $role?->canViewAnalytics()) {
            return false;
        }

        return $role !== MemberRole::Manager || $workspace->teamsFor($user)->exists();
    }

    private function isPlatformSuperAdmin(User $user): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
