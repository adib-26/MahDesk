<?php

namespace App\Policies;

use App\Authorization\RolePermissions;
use App\Enums\MemberRole;
use App\Enums\Permission;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class TicketPolicy
{
    /**
     * Platform administrators bypass organization membership checks. The
     * platform-authentication layer provides User::isSuperAdmin().
     */
    public function before(User $user, string $ability): ?bool
    {
        return $this->isPlatformSuperAdmin($user) ? true : null;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        if ($this->isPlatformSuperAdmin($user)) {
            return true;
        }

        return match ($workspace->roleOf($user)) {
            MemberRole::Owner, MemberRole::Admin, MemberRole::Agent => true,
            MemberRole::Manager => $workspace->teamsFor($user)->exists(),
            MemberRole::Customer => $workspace->customerContactFor($user) !== null,
            null => false,
        };
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->isPlatformSuperAdmin($user) || $this->canAccessTicket($user, $ticket);
    }

    /**
     * Organization administrators and managers can create work for their
     * workspace/team. Support agents are deliberately limited to tickets
     * already assigned to them.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        if ($this->isPlatformSuperAdmin($user)) {
            return true;
        }

        $role = $workspace->roleOf($user);

        if (! RolePermissions::allows($role, Permission::CreateTickets)) {
            return false;
        }

        // Without workspace-wide access, tickets can only be created for one's own teams.
        return RolePermissions::allows($role, Permission::ViewAllTickets) || $workspace->teamsFor($user)->exists();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($this->isPlatformSuperAdmin($user)) {
            return true;
        }

        $role = $ticket->workspace->roleOf($user);

        return RolePermissions::allows($role, Permission::AccessDesk) && $this->canAccessTicket($user, $ticket, $role);
    }

    /**
     * Deleting a support record is deliberately reserved for organization
     * administrators. Managers and agents can update only their scoped work.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->isPlatformSuperAdmin($user)
            || RolePermissions::allows($ticket->workspace->roleOf($user), Permission::DeleteTickets);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->isPlatformSuperAdmin($user) || $this->canAccessTicket($user, $ticket);
    }

    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        if ($this->isPlatformSuperAdmin($user)) {
            return true;
        }

        $role = $ticket->workspace->roleOf($user);

        return RolePermissions::allows($role, Permission::ViewInternalNotes) && $this->canAccessTicket($user, $ticket, $role);
    }

    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $this->viewInternalNotes($user, $ticket);
    }

    /**
     * Organization administrators can assign anywhere in the workspace;
     * managers can assign tickets only within teams they belong to.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        if ($this->isPlatformSuperAdmin($user)) {
            return true;
        }

        $role = $ticket->workspace->roleOf($user);

        if (! RolePermissions::allows($role, Permission::AssignTickets)) {
            return false;
        }

        // Without workspace-wide access, assignment is limited to tickets in one's own teams.
        return RolePermissions::allows($role, Permission::ViewAllTickets) || $ticket->isInTeamOf($user);
    }

    /**
     * Convenient policy-level entry point for controllers and services.
     */
    public static function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        return $query->visibleTo($user, $workspace);
    }

    private function canAccessTicket(User $user, Ticket $ticket, ?MemberRole $role = null): bool
    {
        $role ??= $ticket->workspace->roleOf($user);

        if (RolePermissions::allows($role, Permission::ViewAllTickets)) {
            return true;
        }

        // Everyone else is limited to their slice of the workspace.
        return match ($role) {
            MemberRole::Owner, MemberRole::Admin => true,
            MemberRole::Manager => $ticket->isInTeamOf($user),
            MemberRole::Agent => $ticket->isAssignedTo($user)
                || ($ticket->assignee_id === null
                    && $ticket->isInTeamOf($user)
                    && $ticket->workspace->memberCanViewUnassigned($user)),
            MemberRole::Customer => $ticket->isOwnedByCustomer($user),
            null => false,
        };
    }

    private function isPlatformSuperAdmin(User $user): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
