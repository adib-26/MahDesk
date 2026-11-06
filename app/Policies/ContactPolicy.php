<?php

namespace App\Policies;

use App\Authorization\RolePermissions;
use App\Enums\MemberRole;
use App\Enums\Permission;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;

class ContactPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user) !== null;
    }

    public function view(User $user, Contact $contact): bool
    {
        $role = $contact->workspace->roleOf($user);

        return match ($role) {
            // Org admins see every customer; others only customers whose tickets they can see.
            MemberRole::Owner, MemberRole::Admin => RolePermissions::allows($role, Permission::ViewAllTickets),
            MemberRole::Manager => Ticket::query()
                ->visibleTo($user, $contact->workspace)
                ->where('contact_id', $contact->id)
                ->exists(),
            MemberRole::Agent => $contact->tickets()
                ->where('assignee_id', $user->id)
                ->exists(),
            MemberRole::Customer => $contact->isOwnedBy($user),
            null => false,
        };
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return RolePermissions::allows($workspace->roleOf($user), Permission::ManageContacts);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->create($user, $contact->workspace);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->create($user, $contact->workspace);
    }
}
