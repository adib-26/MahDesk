<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Models\User;

class HomeRedirector
{
    public function url(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return route('platform.workspaces.index');
        }

        $staffWorkspace = $user->workspaces()
            ->wherePivotIn('role', MemberRole::staffValues())
            ->orderBy('name')
            ->first();

        if ($staffWorkspace) {
            return route('desk.dashboard', $staffWorkspace);
        }

        return route('customer.tickets.index');
    }
}
