<?php

namespace Tests\Unit\Policies;

use App\Enums\MemberRole;
use App\Models\Contact;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TicketPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_role_labels_and_permissions_preserve_existing_values(): void
    {
        $this->assertSame('owner', MemberRole::Owner->value);
        $this->assertSame('admin', MemberRole::Admin->value);
        $this->assertSame('agent', MemberRole::Agent->value);
        $this->assertSame('Organization Admin', MemberRole::Admin->label());
        $this->assertSame('Support Agent', MemberRole::Agent->label());
        $this->assertTrue(MemberRole::Manager->canViewAnalytics());
        $this->assertFalse(MemberRole::Customer->canManageWorkspace());
    }

    public function test_ticket_visibility_and_actions_are_limited_by_workspace_role(): void
    {
        $workspace = $this->workspace('alpha');
        $otherWorkspace = $this->workspace('bravo');

        $admin = User::factory()->create();
        $manager = User::factory()->create();
        $agent = User::factory()->create();
        $customer = User::factory()->create();
        $otherAgent = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $workspace->members()->attach($admin, ['role' => MemberRole::Admin->value]);
        $workspace->members()->attach($manager, ['role' => MemberRole::Manager->value]);
        $workspace->members()->attach($agent, ['role' => MemberRole::Agent->value]);
        $workspace->members()->attach($customer, ['role' => MemberRole::Customer->value]);
        $workspace->members()->attach($otherAgent, ['role' => MemberRole::Agent->value]);

        $team = Team::create(['workspace_id' => $workspace->id, 'name' => 'Escalations']);
        $team->members()->attach($manager);

        $customerContact = $this->contact($workspace, 'customer@example.test', $customer);
        $otherContact = $this->contact($workspace, 'other@example.test');
        $otherTeam = Team::create(['workspace_id' => $workspace->id, 'name' => 'Billing']);

        $teamTicket = $this->ticket($workspace, $otherContact, 1, $team, $otherAgent);
        $otherTeamTicket = $this->ticket($workspace, $otherContact, 2, $otherTeam, $otherAgent);
        $agentTicket = $this->ticket($workspace, $otherContact, 3, null, $agent);
        $customerTicket = $this->ticket($workspace, $customerContact, 4, null, $otherAgent);
        $outsideTicket = $this->ticket(
            $otherWorkspace,
            $this->contact($otherWorkspace, 'outside@example.test'),
            1,
        );

        $policy = new TicketPolicy;

        $this->assertFalse($policy->view($admin, $outsideTicket));
        $this->assertTrue($policy->delete($admin, $teamTicket));
        $this->assertTrue($policy->view($superAdmin, $outsideTicket));
        $this->assertTrue($policy->delete($superAdmin, $outsideTicket));

        $this->assertTrue($policy->view($manager, $teamTicket));
        $this->assertTrue($policy->update($manager, $teamTicket));
        $this->assertFalse($policy->view($manager, $otherTeamTicket));
        $this->assertFalse($policy->delete($manager, $teamTicket));

        $this->assertTrue($policy->view($agent, $agentTicket));
        $this->assertTrue($policy->update($agent, $agentTicket));
        $this->assertFalse($policy->view($agent, $teamTicket));
        $this->assertFalse($policy->reply($agent, $teamTicket));

        $this->assertTrue($policy->view($customer, $customerTicket));
        $this->assertTrue($policy->reply($customer, $customerTicket));
        $this->assertFalse($policy->view($customer, $agentTicket));
        $this->assertFalse($policy->update($customer, $customerTicket));
        $this->assertFalse($policy->delete($customer, $customerTicket));
        $this->assertFalse($policy->viewInternalNotes($customer, $customerTicket));

        $this->assertSame([$teamTicket->id], Ticket::query()->visibleTo($manager, $workspace)->pluck('id')->all());
        $this->assertSame([$agentTicket->id], Ticket::query()->visibleTo($agent, $workspace)->pluck('id')->all());
        $this->assertSame([$customerTicket->id], Ticket::query()->visibleTo($customer, $workspace)->pluck('id')->all());
    }

    public function test_agents_can_see_unassigned_team_tickets_when_granted_the_queue_permission(): void
    {
        $workspace = $this->workspace('queues');
        $agent = User::factory()->create();
        $workspace->members()->attach($agent, [
            'role' => MemberRole::Agent->value,
            'can_view_unassigned' => true,
        ]);

        $team = Team::create(['workspace_id' => $workspace->id, 'name' => 'Support']);
        $team->members()->attach($agent);
        $contact = $this->contact($workspace, 'queue@example.test');
        $unassigned = $this->ticket($workspace, $contact, 1, $team);

        $policy = new TicketPolicy;

        $this->assertTrue($policy->view($agent, $unassigned));
        $this->assertTrue($policy->reply($agent, $unassigned));
        $this->assertSame([$unassigned->id], Ticket::query()->visibleTo($agent, $workspace)->pluck('id')->all());
    }

    public function test_only_super_admins_can_create_workspaces(): void
    {
        $user = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->create($user));
        $this->assertTrue($policy->create($superAdmin));
    }

    public function test_manager_analytics_requires_membership_in_a_workspace_team(): void
    {
        $workspace = $this->workspace('analytics');
        $manager = User::factory()->create();
        $workspace->members()->attach($manager, ['role' => MemberRole::Manager->value]);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->viewAnalytics($manager, $workspace));

        $team = Team::create(['workspace_id' => $workspace->id, 'name' => 'Support']);
        $team->members()->attach($manager);

        $this->assertTrue($policy->viewAnalytics($manager, $workspace));
    }

    private function workspace(string $slug): Workspace
    {
        return Workspace::create(['name' => ucfirst($slug), 'slug' => $slug]);
    }

    private function contact(Workspace $workspace, string $email, ?User $user = null): Contact
    {
        return Contact::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user?->id,
            'name' => (string) str($email)->before('@')->title(),
            'email' => $email,
        ]);
    }

    private function ticket(
        Workspace $workspace,
        Contact $contact,
        int $number,
        ?Team $team = null,
        ?User $assignee = null,
    ): Ticket {
        return Ticket::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'team_id' => $team?->id,
            'assignee_id' => $assignee?->id,
            'number' => $number,
            'subject' => "Ticket {$number}",
        ]);
    }
}
