<?php

namespace App\Providers;

use App\Enums\MemberRole;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ContactPolicy;
use App\Policies\TicketPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);

        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        Gate::define('manageWorkspace', function (User $user, Workspace $workspace) {
            return $user->can('update', $workspace);
        });

        Gate::define('manageMembers', function (User $user, Workspace $workspace) {
            return $user->can('manageMembers', $workspace);
        });

        Gate::define('manageTeams', function (User $user, Workspace $workspace) {
            return $user->can('manageTeams', $workspace);
        });

        Gate::define('viewAnalytics', function (User $user, Workspace $workspace) {
            return $user->can('viewAnalytics', $workspace);
        });

        Gate::define('manageKnowledgeBase', function (User $user, Workspace $workspace) {
            return $workspace->roleOf($user)?->canManageKnowledgeBase() ?? false;
        });

        Gate::define('viewKnowledgeBase', function (User $user, Workspace $workspace) {
            return $workspace->roleOf($user)?->isStaff() ?? false;
        });

        Gate::define('manageContacts', function (User $user, Workspace $workspace) {
            return match ($workspace->roleOf($user)) {
                MemberRole::Owner, MemberRole::Admin, MemberRole::Manager => true,
                default => false,
            };
        });

        Gate::define('manageTags', function (User $user, Workspace $workspace) {
            return match ($workspace->roleOf($user)) {
                MemberRole::Owner, MemberRole::Admin, MemberRole::Manager => true,
                default => false,
            };
        });
    }
}
