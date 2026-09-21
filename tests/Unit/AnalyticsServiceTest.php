<?php

namespace Tests\Unit;

use App\Enums\MemberRole;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sla_compliance_drops_when_tickets_are_breached(): void
    {
        $workspace = Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $owner = User::factory()->create();
        $workspace->members()->attach($owner, ['role' => MemberRole::Owner->value]);
        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'name' => 'Pat',
            'email' => 'pat@example.test',
        ]);

        $this->ticket($workspace, $contact, 1, [
            'status' => TicketStatus::Resolved,
            'created_at' => now()->subDays(2),
            'first_response_due_at' => now()->subDays(2)->addHour(),
            'first_responded_at' => now()->subDays(2)->addMinutes(20),
            'resolution_due_at' => now()->addDay(),
            'resolved_at' => now()->subDay(),
        ]);

        $this->ticket($workspace, $contact, 2, [
            'status' => TicketStatus::Open,
            'created_at' => now()->subDays(3),
            'first_response_due_at' => now()->subDays(3)->addHour(),
            'first_responded_at' => now()->subDays(3)->addMinutes(20),
            'resolution_due_at' => now()->subDay(),
            'resolved_at' => null,
        ]);

        $analytics = app(AnalyticsService::class)->forWorkspace($workspace, $owner);

        $this->assertSame(1, $analytics['kpis']['slaBreaches']);
        $this->assertSame(50, $analytics['kpis']['slaCompliance']);
    }

    /**
     * @param  array<string, mixed>  $timestamps
     */
    private function ticket(Workspace $workspace, Contact $contact, int $number, array $timestamps): Ticket
    {
        $ticket = Ticket::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'number' => $number,
            'subject' => "Ticket {$number}",
            'status' => $timestamps['status'],
        ]);

        $ticket->forceFill($timestamps)->save();

        return $ticket->fresh();
    }
}
