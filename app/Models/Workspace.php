<?php

namespace App\Models;

use App\Enums\MemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['role', 'can_view_unassigned'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Authenticated customer users, as distinct from contact records that have
     * not yet been linked to a user account.
     */
    public function customers(): BelongsToMany
    {
        return $this->members()->wherePivot('role', MemberRole::Customer->value);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Contacts linked to authenticated customer accounts in this workspace.
     */
    public function customerContacts(): HasMany
    {
        return $this->contacts()->whereNotNull('user_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }

    public function kbCategories(): HasMany
    {
        return $this->hasMany(KbCategory::class);
    }

    public function kbArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(AutomationRule::class);
    }

    public function roleOf(User $user): ?MemberRole
    {
        $member = $this->members()->whereKey($user->getKey())->first();

        return $member ? MemberRole::tryFrom($member->pivot->role) : null;
    }

    public function hasMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->members()->whereKey($userId)->exists();
    }

    public function hasRole(User $user, MemberRole ...$roles): bool
    {
        return in_array($this->roleOf($user), $roles, true);
    }

    /**
     * Teams in this workspace to which the user belongs. This is the scope
     * used for manager ticket visibility.
     */
    public function teamsFor(User $user): HasMany
    {
        return $this->teams()->forMember($user);
    }

    public function customerContactFor(User $user): ?Contact
    {
        return $this->contacts()->where('user_id', $user->getKey())->first();
    }

    public function nextTicketNumber(): int
    {
        return (int) $this->tickets()->max('number') + 1;
    }

    public function memberCanViewUnassigned(User $user): bool
    {
        $role = $this->roleOf($user);

        if ($role === null) {
            return false;
        }

        if ($role->isOrganizationAdmin() || $role === MemberRole::Manager) {
            return true;
        }

        if ($role !== MemberRole::Agent) {
            return false;
        }

        $member = $this->members()->whereKey($user->getKey())->first();

        return (bool) $member?->pivot->can_view_unassigned;
    }

    public static function uniqueSlugFrom(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
