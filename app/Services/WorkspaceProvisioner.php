<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class WorkspaceProvisioner
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * @return array{workspace: Workspace, invitation: WorkspaceInvitation}
     */
    public function createWithOwnerInvite(
        string $name,
        string $ownerEmail,
        string $ownerName,
        User $actor,
    ): array {
        return DB::transaction(function () use ($name, $ownerEmail, $ownerName, $actor) {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => Workspace::uniqueSlugFrom($name),
            ]);

            $workspace->teams()->create(['name' => 'General Support']);

            SlaPolicy::create([
                'workspace_id' => $workspace->id,
                'name' => 'Standard support',
                'description' => 'Default policy applied to every ticket.',
                'priority' => null,
                'first_response_minutes' => 8 * 60,
                'resolution_minutes' => 72 * 60,
                'is_default' => true,
            ]);

            SlaPolicy::create([
                'workspace_id' => $workspace->id,
                'name' => 'Urgent response',
                'description' => 'Tight targets for urgent tickets.',
                'priority' => 'urgent',
                'first_response_minutes' => 60,
                'resolution_minutes' => 8 * 60,
            ]);

            $invitation = WorkspaceInvitation::issue(
                $workspace,
                $ownerEmail,
                MemberRole::Owner,
                $actor,
                $ownerName,
            );

            Notification::route('mail', $invitation->email)
                ->notify(new WorkspaceInvitationNotification($invitation));

            $this->audit->record(
                'workspace.created',
                $actor,
                $workspace,
                $workspace,
                ['owner_email' => $invitation->email],
            );

            return compact('workspace', 'invitation');
        });
    }
}
