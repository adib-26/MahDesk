<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationAndPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_sends_an_invitation_instead_of_creating_a_privileged_user(): void
    {
        Notification::fake();

        $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $admin = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => MemberRole::Admin->value]);

        $this->actingAs($admin)
            ->post("/w/{$workspace->slug}/settings/members", [
                'name' => 'Priya Shah',
                'email' => 'priya@example.test',
                'role' => MemberRole::Agent->value,
                'can_view_unassigned' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['email' => 'priya@example.test']);
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'priya@example.test',
            'role' => MemberRole::Agent->value,
            'can_view_unassigned' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.sent',
            'workspace_id' => $workspace->id,
        ]);

        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
    }

    public function test_invitee_can_create_an_account_and_join_the_workspace(): void
    {
        $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace->teams()->create(['name' => 'General Support']);
        $invitation = WorkspaceInvitation::issue($workspace, 'priya@example.test', MemberRole::Agent, null, 'Priya', true);

        $this->post("/invitations/{$invitation->token}", [
            'name' => 'Priya Shah',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertTrue($workspace->fresh()->hasMember(User::query()->where('email', 'priya@example.test')->first()));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertTrue((bool) $workspace->members()->where('email', 'priya@example.test')->first()?->pivot->can_view_unassigned);
    }

    public function test_regular_users_cannot_create_workspaces(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/workspaces/create')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/platform/workspaces', [
                'name' => 'Unauthorized Co',
                'owner_name' => 'Pat',
                'owner_email' => 'pat@example.test',
            ])
            ->assertForbidden();
    }

    public function test_super_admin_creates_a_workspace_and_invites_the_owner(): void
    {
        Notification::fake();

        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($superAdmin)
            ->post('/platform/workspaces', [
                'name' => 'Northwind',
                'owner_name' => 'Ava Chen',
                'owner_email' => 'ava@northwind.test',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $workspace = Workspace::query()->where('slug', 'northwind')->first();
        $this->assertNotNull($workspace);
        $this->assertFalse($workspace->hasMember($superAdmin));
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'ava@northwind.test',
            'role' => MemberRole::Owner->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'workspace.created']);
    }
}
