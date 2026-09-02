<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'number', 'subject', 'status', 'priority', 'channel',
        'contact_id', 'team_id', 'assignee_id', 'sla_policy_id',
        'first_response_due_at', 'resolution_due_at',
        'first_responded_at', 'resolved_at', 'closed_at',
    ];

    protected $appends = ['first_response_breached', 'resolution_breached'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'channel' => TicketChannel::class,
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ticket_tag');
    }

    public function isAssignedTo(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->assignee_id !== null && (int) $this->assignee_id === (int) $userId;
    }

    public function isInTeamOf(User|int $user): bool
    {
        if ($this->team_id === null) {
            return false;
        }

        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->team()
            ->where('workspace_id', $this->workspace_id)
            ->whereHas('members', fn (Builder $members) => $members->whereKey($userId))
            ->exists();
    }

    public function isOwnedByCustomer(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $this->contact()
            ->where('workspace_id', $this->workspace_id)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Restrict a ticket query to the part of a workspace visible to a member.
     * A workspace argument is deliberately required so ordinary queries cannot
     * accidentally span organizations.
     */
    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        $query->where('workspace_id', $workspace->getKey());

        if (self::isPlatformSuperAdmin($user)) {
            return $query;
        }

        $role = $workspace->roleOf($user);

        return match ($role) {
            MemberRole::Owner, MemberRole::Admin => $query,
            MemberRole::Manager => $query->whereIn(
                'team_id',
                Team::query()
                    ->where('workspace_id', $workspace->getKey())
                    ->forMember($user)
                    ->select('teams.id'),
            ),
            MemberRole::Agent => $query->where(function (Builder $tickets) use ($user, $workspace) {
                $tickets->where('assignee_id', $user->getKey());

                if ($workspace->memberCanViewUnassigned($user)) {
                    $tickets->orWhere(function (Builder $unassigned) use ($user, $workspace) {
                        $unassigned
                            ->whereNull('assignee_id')
                            ->whereIn(
                                'team_id',
                                Team::query()
                                    ->where('workspace_id', $workspace->getKey())
                                    ->forMember($user)
                                    ->select('teams.id'),
                            );
                    });
                }
            }),
            MemberRole::Customer => $query->whereHas(
                'contact',
                fn (Builder $contacts) => $contacts
                    ->where('workspace_id', $workspace->getKey())
                    ->where('user_id', $user->getKey()),
            ),
            null => $query->whereRaw('0 = 1'),
        };
    }

    public function getFirstResponseBreachedAttribute(): bool
    {
        if (! $this->first_response_due_at) {
            return false;
        }

        return ($this->first_responded_at ?? now())->greaterThan($this->first_response_due_at);
    }

    public function getResolutionBreachedAttribute(): bool
    {
        if (! $this->resolution_due_at) {
            return false;
        }

        return ($this->resolved_at ?? now())->greaterThan($this->resolution_due_at);
    }

    public function transitionStatus(TicketStatus $new, ?int $actorId = null): void
    {
        $old = $this->status;

        if ($old === $new) {
            return;
        }

        $this->status = $new;

        if ($new === TicketStatus::Resolved) {
            $this->resolved_at ??= now();
        } elseif ($new === TicketStatus::Closed) {
            $this->resolved_at ??= now();
            $this->closed_at ??= now();
        } else {
            $this->resolved_at = null;
            $this->closed_at = null;
        }

        $this->save();
        $this->logEvent(sprintf('Status changed from %s to %s', $old->value, $new->value), $actorId);
    }

    public function logEvent(string $body, ?int $authorId = null, array $meta = []): TicketMessage
    {
        return $this->messages()->create([
            'author_id' => $authorId,
            'kind' => 'event',
            'body' => $body,
            'meta' => $meta ?: null,
        ]);
    }

    private static function isPlatformSuperAdmin(User $user): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
