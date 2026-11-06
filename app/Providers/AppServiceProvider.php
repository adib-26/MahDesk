<?php

namespace App\Providers;

use App\Authorization\RolePermissions;
use App\Enums\Permission;
use App\Hashing\UpgradingArgon2IdHasher;
use App\Support\PasswordRules;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ContactPolicy;
use App\Policies\TicketPolicy;
use App\Policies\WorkspacePolicy;
use App\Support\Like;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        // Argon2id that also verifies (and upgrades) older bcrypt hashes. See config/hashing.php.
        Hash::extend('argon2id', fn ($app) => new UpgradingArgon2IdHasher($app['config']['hashing.argon'] ?? []));

        // Rules for every new password: registration, reset, change and invitation sign-up.
        Password::defaults(fn () => PasswordRules::make());

        Like::registerMacros();

        RateLimiter::for('invitations', function (Request $request) {
            return Limit::perMinute(6)->by($request->user()?->id ?: $request->ip());
        });

        // Sign-in, registration, password confirmation and reset submissions, per IP.
        // Login additionally locks an email address after 5 failures (LoginRequest).
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by('auth:'.$request->ip()));

        // "Forgot password" sends email, so limit both per address and per IP
        // to stop mailbox flooding and account enumeration.
        RateLimiter::for('password-email', fn (Request $request) => [
            Limit::perMinutes(15, 3)->by('reset-email:'.Str::lower((string) $request->input('email'))),
            Limit::perMinute(10)->by('reset-ip:'.$request->ip()),
        ]);

        // Creating tickets and posting replies or notes. Uploads get a tighter
        // limit because each request can carry several files.
        RateLimiter::for('ticket-writes', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            $limits = [Limit::perMinute(30)->by('writes:'.$key)];

            if ($request->allFiles() !== []) {
                $limits[] = Limit::perMinute(10)->by('uploads:'.$key);
                $limits[] = Limit::perHour(100)->by('uploads-hourly:'.$key);
            }

            return $limits;
        });

        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(60)->by('downloads:'.($request->user()?->id ?: $request->ip())));

        // Public, unauthenticated help center (and its search).
        RateLimiter::for('help-center', fn (Request $request) => Limit::perMinute(60)->by('help:'.$request->ip()));

        // Authenticated API calls: per token (or per user for cookie sessions).
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $token = $user?->currentAccessToken();
            $key = $token instanceof \Laravel\Sanctum\PersonalAccessToken
                ? 'token:'.$token->id
                : 'user:'.($user?->id ?? $request->ip());

            return Limit::perMinute((int) config('services.api.rate_limit', 120))->by($key);
        });

        // Credential exchange: slow down password guessing per account and per IP.
        RateLimiter::for('api-auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by('email:'.Str::lower((string) $request->input('email')).'|'.$request->ip()),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);

        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // One Gate per permission, e.g. Gate::allows('teams.manage', $workspace).
        // The role -> permission matrix lives in App\Authorization\RolePermissions.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user, Workspace $workspace) => match ($permission) {
                // Managers only get reports when they belong to a team (reports are team-scoped).
                Permission::ViewAnalytics => app(WorkspacePolicy::class)->viewAnalytics($user, $workspace),
                default => RolePermissions::allows($workspace->roleOf($user), $permission),
            });
        }

        // Gate names used before the permission list existed; kept as aliases.
        foreach ([
            'manageWorkspace' => Permission::ManageWorkspace,
            'manageMembers' => Permission::ManageMembers,
            'manageTeams' => Permission::ManageTeams,
            'viewAnalytics' => Permission::ViewAnalytics,
            'manageKnowledgeBase' => Permission::ManageKnowledgeBase,
            'viewKnowledgeBase' => Permission::ViewKnowledgeBase,
            'manageContacts' => Permission::ManageContacts,
            'manageTags' => Permission::ManageTags,
        ] as $alias => $permission) {
            Gate::define($alias, fn (User $user, Workspace $workspace) => Gate::forUser($user)->allows($permission->value, $workspace));
        }
    }
}
