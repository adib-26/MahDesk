<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\AutomationRule;
use App\Models\Contact;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AutomationRuleNotification;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutomationWorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_when_priority_high_and_category_technical_then_assign_team_notify_and_set_two_hour_sla(): void
    {
        Notification::fake();

        $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $admin = User::factory()->create(['name' => 'Marcus Hale']);
        $techAgent = User::factory()->create(['name' => 'Priya Shah']);

        $workspace->members()->attach($admin->id, ['role' => MemberRole::Admin->value]);
        $workspace->members()->attach($techAgent->id, ['role' => MemberRole::Agent->value]);

        $generalTeam = Team::create(['workspace_id' => $workspace->id, 'name' => 'General Support']);
        $technicalTeam = Team::create(['workspace_id' => $workspace->id, 'name' => 'Technical Team']);
        $technicalTeam->members()->attach([$admin->id, $techAgent->id]);

        AutomationRule::create([
            'workspace_id' => $workspace->id,
            'name' => 'Route high-priority technical tickets',
            'event' => 'ticket_created',
            'conditions' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
                ['field' => 'category', 'operator' => 'equals', 'value' => 'technical'],
            ],
            'actions' => [
                ['type' => 'assign_team', 'value' => (string) $technicalTeam->id],
                ['type' => 'send_notification', 'value' => 'assigned_team'],
                ['type' => 'set_sla_hours', 'value' => '2'],
            ],
            'is_active' => true,
            'position' => 1,
        ]);

        $this->actingAs($admin)
            ->post("/w/{$workspace->slug}/tickets", [
                'subject' => 'Production API returning 502',
                'body' => 'Our integration is failing with 502 errors.',
                'priority' => TicketPriority::High->value,
                'channel' => TicketChannel::Web->value,
                'category' => TicketCategory::Technical->value,
                'team_id' => $generalTeam->id,
                'contact_name' => 'Elena Rossi',
                'contact_email' => 'elena@lumen.co',
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('workspace_id', $workspace->id)->firstOrFail();

        $this->assertSame($technicalTeam->id, $ticket->team_id);
        $this->assertNotNull($ticket->resolution_due_at);
        $this->assertEqualsWithDelta(120, $ticket->created_at->diffInMinutes($ticket->resolution_due_at), 1);

        Notification::assertSentTo([$admin, $techAgent], AutomationRuleNotification::class);
    }

    public function test_when_ticket_remains_unresolved_for_four_hours_then_escalate_to_manager_once(): void
    {
        Notification::fake();

        $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $manager = User::factory()->create(['name' => 'Nora Manager']);
        $agent = User::factory()->create(['name' => 'Priya Shah']);

        $workspace->members()->attach($manager->id, ['role' => MemberRole::Manager->value]);
        $workspace->members()->attach($agent->id, ['role' => MemberRole::Agent->value]);

        $team = Team::create(['workspace_id' => $workspace->id, 'name' => 'Technical Team']);
        $team->members()->attach([$manager->id, $agent->id]);

        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'name' => 'Jonah Park',
            'email' => 'jonah@brightwell.io',
        ]);

        $staleTicket = Ticket::create([
            'workspace_id' => $workspace->id,
            'number' => 1,
            'subject' => 'Database failover stuck',
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
            'channel' => TicketChannel::Email,
            'category' => TicketCategory::Technical,
            'contact_id' => $contact->id,
            'team_id' => $team->id,
            'assignee_id' => $agent->id,
        ]);
        $staleTicket->forceFill(['created_at' => now()->subHours(5)])->save();

        $freshTicket = Ticket::create([
            'workspace_id' => $workspace->id,
            'number' => 2,
            'subject' => 'Recent question',
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Low,
            'channel' => TicketChannel::Web,
            'category' => TicketCategory::General,
            'contact_id' => $contact->id,
            'team_id' => $team->id,
            'assignee_id' => $agent->id,
        ]);
        $freshTicket->forceFill(['created_at' => now()->subHours(1)])->save();

        AutomationRule::create([
            'workspace_id' => $workspace->id,
            'name' => 'Escalate after 4h unresolved',
            'event' => 'ticket_escalation',
            'conditions' => [
                ['field' => 'unresolved_hours', 'operator' => 'greater_than_or_equal', 'value' => '4'],
            ],
            'actions' => [
                ['type' => 'escalate_to_manager', 'value' => 'auto'],
            ],
            'is_active' => true,
            'position' => 1,
        ]);

        $engine = app(AutomationEngine::class);
        $firstRun = $engine->runEscalations($workspace);

        $this->assertSame(2, $firstRun['evaluated']);
        $this->assertSame(1, $firstRun['triggered']);

        $staleTicket->refresh();
        $this->assertSame($manager->id, $staleTicket->assignee_id);
        $this->assertSame(TicketPriority::High, $staleTicket->priority);

        Notification::assertSentTo($manager, AutomationRuleNotification::class);

        // Running again must not re-trigger the same escalation rule on the same ticket.
        $secondRun = $engine->runEscalations($workspace);
        $this->assertSame(0, $secondRun['triggered']);
    }

    public function test_admin_can_create_multi_condition_workflow_rule_via_settings_endpoint(): void
    {
        $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $admin = User::factory()->create();
        $workspace->members()->attach($admin->id, ['role' => MemberRole::Admin->value]);
        $team = Team::create(['workspace_id' => $workspace->id, 'name' => 'Technical Team']);

        $this->actingAs($admin)
            ->post("/w/{$workspace->slug}/settings/automations", [
                'name' => 'High technical workflow',
                'event' => 'ticket_created',
                'is_active' => true,
                'conditions' => [
                    ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
                    ['field' => 'category', 'operator' => 'equals', 'value' => 'technical'],
                ],
                'actions' => [
                    ['type' => 'assign_team', 'value' => (string) $team->id],
                    ['type' => 'send_notification', 'value' => 'assigned_team'],
                    ['type' => 'set_sla_hours', 'value' => '2'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('automation_rules', [
            'workspace_id' => $workspace->id,
            'name' => 'High technical workflow',
            'event' => 'ticket_created',
        ]);
    }
}
