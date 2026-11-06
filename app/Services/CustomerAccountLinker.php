<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CustomerAccountLinker
{
    /**
     * Link a contact to an existing user account with the same email and make
     * that user a customer member of the workspace, so they see the ticket in
     * the customer portal.
     */
    public function link(Workspace $workspace, Contact $contact): void
    {
        if ($contact->user_id) {
            return;
        }

        $userId = User::query()
            ->where('email', Str::lower($contact->email))
            ->value('id');

        if (! $userId) {
            return;
        }

        $contact->update(['user_id' => $userId]);

        if (! $workspace->hasMember($userId)) {
            $workspace->members()->attach($userId, ['role' => MemberRole::Customer->value]);
        }
    }
}
