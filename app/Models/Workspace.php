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

    protected static function booted(): void
    {
        // Tickets are removed by a database cascade, which skips Ticket model events.
        static::deleting(fn (Workspace $workspace) => \App\Services\TicketAttachmentService::purgeFilesForTickets(
            $workspace->tickets()->select('id'),
        ));
    }

    protected $fillable = ['name', 'slug', 'support_email'];

    /**
     * Address the email provider delivers to for this workspace. Companies
     * forward their own support address here.
     */
    public function inboundEmailAddress(): string
    {
        return self::inboundAddressFor($this->slug);
    }

    /**
     * testmail.app: {namespace}.{slug}@inbox.testmail.app (the slug is the
     * testmail.app "tag"). Postmark/other: {slug}@INBOUND_EMAIL_DOMAIN.
     */
    public static function inboundAddressFor(string $slug): string
    {
        if (config('services.inbound_email.driver') === 'testmail') {
            return config('services.testmail.namespace').'.'.$slug.'@'.config('services.testmail.domain');
        }

        return $slug.'@'.config('services.inbound_email.domain');
    }

    /** Inverse of inboundAddressFor(); ignores a "+suffix" on the local part. */
    public static function slugFromInboundAddress(string $address): ?string
    {
        [$local, $host] = array_pad(explode('@', Str::lower(trim($address)), 2), 2, '');
        $local = Str::before($local, '+');

        if (config('services.inbound_email.driver') === 'testmail') {
            $prefix = Str::lower((string) config('services.testmail.namespace')).'.';

            return $host === Str::lower((string) config('services.testmail.domain')) && $prefix !== '.' && str_starts_with($local, $prefix)
                ? (Str::after($local, $prefix) ?: null)
                : null;
        }

        return $host === Str::lower((string) config('services.inbound_email.domain')) && $local !== '' ? $local : null;
    }

    /** Address customers should reply to. */
    public function replyToAddress(): string
    {
        return $this->support_email ?: $this->inboundEmailAddress();
    }

    /**
     * Resolve the workspace an email was sent to from its recipient list.
     * Matches the routing address or a configured support_email.
     *
     * @param  list<string>  $recipients
     */
    public static function forInboundRecipients(array $recipients): ?self
    {
        $slugs = [];
        $addresses = [];

        foreach ($recipients as $recipient) {
            $address = Str::lower(trim($recipient));
            $addresses[] = $address;

            if ($slug = self::slugFromInboundAddress($address)) {
                $slugs[] = $slug;
            }
        }

        if ($slugs !== []) {
            $workspace = self::query()->whereIn('slug', $slugs)->first();
            if ($workspace) {
                return $workspace;
            }
        }

        return $addresses === []
            ? null
            : self::query()->whereIn('support_email', $addresses)->first();
    }

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
