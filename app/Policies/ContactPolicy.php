<?php

namespace App\Policies;

use App\Enums\MemberRole;
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
            MemberRole::Owner, MemberRole::Admin => true,
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
        return in_array($workspace->roleOf($user), [MemberRole::Owner, MemberRole::Admin, MemberRole::Manager], true);
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
