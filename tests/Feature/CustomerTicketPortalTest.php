<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerTicketPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_index_lists_only_their_tickets_grouped_by_workspace(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        [$acme, $acmeContact] = $this->customerWorkspace($customer, 'Acme Support');
        [$beta, $betaContact] = $this->customerWorkspace($customer, 'Beta Support');
        $otherContact = $this->customerContact($acme, $otherCustomer);

        $acmeTicket = $this->ticket($acme, $acmeContact, 1, 'Acme request');
        $betaTicket = $this->ticket($beta, $betaContact, 1, 'Beta request');
        $otherTicket = $this->ticket($acme, $otherContact, 2, 'Private request');

        $response = $this
            ->actingAs($customer)
            ->get('/customer/tickets', $this->inertiaHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('component', 'customer/tickets/index')
            ->assertJsonCount(2, 'props.ticketGroups')
            ->assertJsonPath('props.ticketGroups.0.name', 'Acme Support')
            ->assertJsonPath('props.ticketGroups.0.tickets.0.id', $acmeTicket->id)
            ->assertJsonPath('props.ticketGroups.1.name', 'Beta Support')
            ->assertJsonPath('props.ticketGroups.1.tickets.0.id', $betaTicket->id)
            ->assertJsonMissing(['subject' => $otherTicket->subject]);
    }

    public function test_customer_can_view_only_public_replies_on_an_owned_ticket(): void
    {
        $customer = User::factory()->create();
        [$workspace, $contact] = $this->customerWorkspace($customer, 'Acme Support');
        $ticket = $this->ticket($workspace, $contact, 1, 'Unable to sign in');

        $ticket->messages()->create([
            'author_id' => $customer->id,
            'kind' => 'reply',
            'is_from_contact' => true,
            'body' => 'Customer-visible question',
        ]);
        $ticket->messages()->create([
            'kind' => 'reply',
            'body' => 'Customer-visible support response',
        ]);
        $ticket->messages()->create([
            'kind' => 'note',
            'body' => 'Internal-only troubleshooting details',
        ]);
        $ticket->messages()->create([
            'kind' => 'event',
            'body' => 'Ticket assigned to the escalation team',
        ]);

        $response = $this
            ->actingAs($customer)
            ->get("/customer/tickets/{$ticket->id}", $this->inertiaHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('component', 'customer/tickets/show')
            ->assertJsonPath('props.ticket.id', $ticket->id)
            ->assertJsonPath('props.ticket.workspace.name', $workspace->name)
            ->assertJsonCount(2, 'props.ticket.messages')
            ->assertJsonPath('props.ticket.messages.0.body', 'Customer-visible question')
            ->assertJsonPath('props.ticket.messages.0.is_from_contact', true)
            ->assertJsonPath('props.ticket.messages.1.body', 'Customer-visible support response')
            ->assertJsonPath('props.ticket.messages.1.is_from_contact', false)
            ->assertJsonMissing(['body' => 'Internal-only troubleshooting details'])
            ->assertJsonMissing(['body' => 'Ticket assigned to the escalation team'])
            ->assertJsonMissing(['contact_id' => $contact->id]);
    }

    public function test_customer_cannot_view_another_customers_ticket(): void
    {
        $owner = User::factory()->create();
        $otherCustomer = User::factory()->create();
        [$workspace, $ownerContact] = $this->customerWorkspace($owner, 'Acme Support');
        $this->customerContact($workspace, $otherCustomer);
        $ticket = $this->ticket($workspace, $ownerContact, 1, 'Private request');

        $response = $this
            ->actingAs($otherCustomer)
            ->get("/customer/tickets/{$ticket->id}", $this->inertiaHeaders());

        $response->assertForbidden();
    }

    public function test_customer_reply_is_always_a_public_customer_reply(): void
    {
        $customer = User::factory()->create();
        $otherUser = User::factory()->create();
        [$workspace, $contact] = $this->customerWorkspace($customer, 'Acme Support');
        $ticket = $this->ticket($workspace, $contact, 1, 'Unable to sign in');

        $response = $this
            ->actingAs($customer)
            ->from("/customer/tickets/{$ticket->id}")
            ->post("/customer/tickets/{$ticket->id}/messages", [
                'body' => 'I am still unable to sign in.',
                'kind' => 'note',
                'is_from_contact' => false,
                'author_id' => $otherUser->id,
                'status' => TicketStatus::Closed->value,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect("/customer/tickets/{$ticket->id}");

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'author_id' => $customer->id,
            'kind' => 'reply',
            'is_from_contact' => true,
            'body' => 'I am still unable to sign in.',
        ]);
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_customer_cannot_reply_to_another_customers_ticket(): void
    {
        $owner = User::factory()->create();
        $otherCustomer = User::factory()->create();
        [$workspace, $ownerContact] = $this->customerWorkspace($owner, 'Acme Support');
        $this->customerContact($workspace, $otherCustomer);
        $ticket = $this->ticket($workspace, $ownerContact, 1, 'Private request');

        $response = $this
            ->actingAs($otherCustomer)
            ->post("/customer/tickets/{$ticket->id}/messages", [
                'body' => 'Attempted reply',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body' => 'Attempted reply',
        ]);
    }

    public function test_customer_reply_requires_a_body(): void
    {
        $customer = User::factory()->create();
        [$workspace, $contact] = $this->customerWorkspace($customer, 'Acme Support');
        $ticket = $this->ticket($workspace, $contact, 1, 'Private request');

        $response = $this
            ->actingAs($customer)
            ->from("/customer/tickets/{$ticket->id}")
            ->post("/customer/tickets/{$ticket->id}/messages", [
                'kind' => 'note',
            ]);

        $response
            ->assertSessionHasErrors('body')
            ->assertRedirect("/customer/tickets/{$ticket->id}");

        $this->assertDatabaseCount('ticket_messages', 0);
    }

    /**
     * @return array{Workspace, Contact}
     */
    private function customerWorkspace(User $user, string $name): array
    {
        $workspace = Workspace::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(8),
        ]);

        $workspace->members()->attach($user->id, ['role' => MemberRole::Customer->value]);

        return [$workspace, $this->customerContact($workspace, $user)];
    }

    private function customerContact(Workspace $workspace, User $user): Contact
    {
        if (! $workspace->members()->whereKey($user->id)->exists()) {
            $workspace->members()->attach($user->id, ['role' => MemberRole::Customer->value]);
        }

        return $workspace->contacts()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    private function ticket(Workspace $workspace, Contact $contact, int $number, string $subject): Ticket
    {
        return $workspace->tickets()->create([
            'number' => $number,
            'subject' => $subject,
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
            'channel' => TicketChannel::Web,
            'contact_id' => $contact->id,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
        ];
    }
}
