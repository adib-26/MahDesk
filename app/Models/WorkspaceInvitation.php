<?php

namespace App\Models;

use App\Enums\MemberRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkspaceInvitation extends Model
{
    protected $fillable = [
        'workspace_id',
        'invited_by',
        'name',
        'email',
        'role',
        'can_view_unassigned',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'can_view_unassigned' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function role(): MemberRole
    {
        return MemberRole::from($this->role);
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->expires_at->isPast();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isPast();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public static function issue(
        Workspace $workspace,
        string $email,
        MemberRole $role,
        ?User $inviter = null,
        ?string $name = null,
        bool $canViewUnassigned = false,
    ): self {
        $workspace->invitations()
            ->pending()
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->delete();

        return $workspace->invitations()->create([
            'invited_by' => $inviter?->id,
            'name' => $name,
            'email' => Str::lower($email),
            'role' => $role->value,
            'can_view_unassigned' => $canViewUnassigned && $role === MemberRole::Agent,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
