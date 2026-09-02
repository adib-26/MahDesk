<?php

namespace Database\Seeders;

use App\Enums\MemberRole;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\AutomationRule;
use App\Models\Contact;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\SlaPolicy;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SlaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Ava Chen',
            'email' => 'owner@desk.test',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'super@desk.test',
            'password' => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        $admin = User::factory()->create([
            'name' => 'Marcus Hale',
            'email' => 'admin@desk.test',
            'password' => Hash::make('password'),
        ]);

        $agent = User::factory()->create([
            'name' => 'Priya Shah',
            'email' => 'agent@desk.test',
            'password' => Hash::make('password'),
        ]);

        $harborOwner = User::factory()->create([
            'name' => 'Marina Ortiz',
            'email' => 'marina@harbor.test',
            'password' => Hash::make('password'),
        ]);

        $northwind = $this->workspace('Northwind Support', 'northwind-support', [
            $owner->id => MemberRole::Owner,
            $admin->id => MemberRole::Admin,
            $agent->id => MemberRole::Agent,
        ]);

        $harbor = $this->workspace('Harbor Digital', 'harbor-digital', [
            $harborOwner->id => MemberRole::Owner,
        ]);

        $this->seedNorthwind($northwind, $owner, $admin, $agent);
        $this->seedHarbor($harbor, $harborOwner);
    }

    /**
     * @param  array<int, MemberRole>  $members
     */
    private function workspace(string $name, string $slug, array $members): Workspace
    {
        $workspace = Workspace::create(['name' => $name, 'slug' => $slug]);

        foreach ($members as $userId => $role) {
            $workspace->members()->attach($userId, ['role' => $role->value]);
        }

        SlaPolicy::create([
            'workspace_id' => $workspace->id,
            'name' => 'Standard support',
            'description' => 'Default policy applied to every ticket.',
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

        return $workspace;
    }

    private function seedNorthwind(Workspace $workspace, User $owner, User $admin, User $agent): void
    {
        $tags = collect([
            ['billing', '#f97316'],
            ['bug', '#ef4444'],
            ['how-to', '#6366f1'],
            ['vip', '#eab308'],
            ['shipping', '#0ea5e9'],
        ])->mapWithKeys(fn ($tag) => [
            $tag[0] => Tag::create(['workspace_id' => $workspace->id, 'name' => $tag[0], 'color' => $tag[1]]),
        ]);

        AutomationRule::create([
            'workspace_id' => $workspace->id,
            'name' => 'Flag urgent tickets',
            'event' => 'ticket_created',
            'conditions' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'urgent'],
            ],
            'actions' => [
                ['type' => 'add_tag', 'value' => 'vip'],
                ['type' => 'assign_agent', 'value' => (string) $owner->id],
                ['type' => 'add_note', 'value' => 'Urgent ticket auto-assigned to the on-call owner.'],
            ],
            'is_active' => true,
            'position' => 1,
        ]);

        AutomationRule::create([
            'workspace_id' => $workspace->id,
            'name' => 'Tag billing subjects',
            'event' => 'ticket_created',
            'conditions' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'invoice'],
            ],
            'actions' => [
                ['type' => 'add_tag', 'value' => 'billing'],
                ['type' => 'set_priority', 'value' => 'high'],
            ],
            'is_active' => true,
            'position' => 2,
        ]);

        $gettingStarted = KbCategory::create([
            'workspace_id' => $workspace->id,
            'name' => 'Getting started',
            'slug' => 'getting-started',
            'description' => 'Account and first-week questions',
            'position' => 1,
        ]);

        $orders = KbCategory::create([
            'workspace_id' => $workspace->id,
            'name' => 'Orders & shipping',
            'slug' => 'orders-shipping',
            'description' => 'Tracking, delays, and returns',
            'position' => 2,
        ]);

        $this->article($workspace, $gettingStarted, $owner, 'Reset your password', 'Use the forgot-password link on the login page, then check your inbox.', 'published', 184);
        $this->article($workspace, $gettingStarted, $admin, 'Invite a teammate', 'Workspace admins can add agents from Settings → Agents.', 'published', 96);
        $this->article($workspace, $orders, $agent, 'Track an order', 'Open the order, copy the carrier tracking number, and share it with the customer.', 'published', 241);
        $this->article($workspace, $orders, $admin, 'Draft: holiday SLA changes', 'We will extend resolution targets during the last two weeks of December.', 'draft', 3);

        $contacts = [
            Contact::create(['workspace_id' => $workspace->id, 'name' => 'Elena Rossi', 'email' => 'elena@lumen.co', 'company' => 'Lumen Co', 'phone' => '+1 415 555 0142']),
            Contact::create(['workspace_id' => $workspace->id, 'name' => 'Jonah Park', 'email' => 'jonah@brightwell.io', 'company' => 'Brightwell', 'phone' => '+1 206 555 0199']),
            Contact::create(['workspace_id' => $workspace->id, 'name' => 'Sofia Nguyen', 'email' => 'sofia@northline.com', 'company' => 'Northline', 'notes' => 'Prefers email over phone.']),
            Contact::create(['workspace_id' => $workspace->id, 'name' => 'David Okonkwo', 'email' => 'david@harborfreight.test', 'company' => 'Harbor Freight']),
            Contact::create(['workspace_id' => $workspace->id, 'name' => 'Amelia Brooks', 'email' => 'amelia@brookslabs.com', 'company' => 'Brooks Labs']),
        ];

        $scenarios = [
            ['Cannot reset password', TicketPriority::High, TicketStatus::Open, TicketChannel::Email, $contacts[0], $agent, ['how-to'], 18, false],
            ['Invoice #441 is missing tax', TicketPriority::Urgent, TicketStatus::Pending, TicketChannel::Email, $contacts[1], $owner, ['billing', 'vip'], 6, false],
            ['Package stuck in transit', TicketPriority::Normal, TicketStatus::Open, TicketChannel::Chat, $contacts[2], $admin, ['shipping'], 30, false],
            ['App crashes on checkout', TicketPriority::High, TicketStatus::OnHold, TicketChannel::Web, $contacts[3], $agent, ['bug'], 40, false],
            ['How do I add a second admin?', TicketPriority::Low, TicketStatus::Resolved, TicketChannel::Web, $contacts[4], $admin, ['how-to'], 5, true],
            ['Refund for damaged shipment', TicketPriority::Normal, TicketStatus::Resolved, TicketChannel::Phone, $contacts[0], $agent, ['shipping', 'billing'], 12, true],
            ['SSO login loop', TicketPriority::Urgent, TicketStatus::Closed, TicketChannel::Email, $contacts[1], $owner, ['bug', 'vip'], 20, true],
            ['Need CSV export of tickets', TicketPriority::Low, TicketStatus::Open, TicketChannel::Web, $contacts[2], null, ['how-to'], 2, false],
        ];

        foreach ($scenarios as $index => $scenario) {
            $this->ticket($workspace, $index + 1, $scenario, $tags);
        }
    }

    private function seedHarbor(Workspace $workspace, User $owner): void
    {
        $contact = Contact::create([
            'workspace_id' => $workspace->id,
            'name' => 'Chris Patel',
            'email' => 'chris@atelier.test',
            'company' => 'Atelier',
        ]);

        $category = KbCategory::create([
            'workspace_id' => $workspace->id,
            'name' => 'Billing',
            'slug' => 'billing',
            'position' => 1,
        ]);

        $this->article($workspace, $category, $owner, 'Update a payment method', 'Workspace owners can change the card on file from Settings.', 'published', 22);

        $this->ticket($workspace, 1, [
            'Website form is not sending',
            TicketPriority::High,
            TicketStatus::Open,
            TicketChannel::Email,
            $contact,
            $owner,
            [],
            8,
            false,
        ], collect());
    }

    private function article(Workspace $workspace, KbCategory $category, User $author, string $title, string $body, string $status, int $views): void
    {
        KbArticle::create([
            'workspace_id' => $workspace->id,
            'kb_category_id' => $category->id,
            'author_id' => $author->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => Str::limit($body, 90, ''),
            'body' => $body."\n\nIf this does not solve it, reply to your support ticket and an agent will take it from there.",
            'status' => $status,
            'published_at' => $status === 'published' ? now()->subDays(rand(2, 20)) : null,
            'views' => $views,
        ]);
    }

    /**
     * @param  array{0:string,1:TicketPriority,2:TicketStatus,3:TicketChannel,4:Contact,5:?User,6:list<string>,7:int,8:bool}  $scenario
     */
    private function ticket(Workspace $workspace, int $number, array $scenario, $tags): void
    {
        [$subject, $priority, $status, $channel, $contact, $assignee, $tagNames, $hoursAgo, $resolved] = $scenario;

        $created = now()->subHours($hoursAgo);
        $ticket = Ticket::create([
            'workspace_id' => $workspace->id,
            'number' => $number,
            'subject' => $subject,
            'status' => $status,
            'priority' => $priority,
            'channel' => $channel,
            'contact_id' => $contact->id,
            'assignee_id' => $assignee?->id,
        ]);

        $ticket->forceFill([
            'created_at' => $created,
            'updated_at' => $created->copy()->addHour(),
        ])->save();

        $this->message($ticket, [
            'kind' => 'reply',
            'is_from_contact' => true,
            'body' => "Hi team — {$subject}. Can you take a look?",
        ], $created);

        if ($assignee) {
            $repliedAt = $created->copy()->addMinutes(rand(20, 180));
            $ticket->first_responded_at = $repliedAt;
            $this->message($ticket, [
                'author_id' => $assignee->id,
                'kind' => 'reply',
                'body' => 'Thanks for writing in. We are looking into this and will update you shortly.',
            ], $repliedAt);
        }

        if ($resolved) {
            $resolvedAt = $created->copy()->addHours(rand(4, 20));
            $ticket->resolved_at = $resolvedAt;
            if ($status === TicketStatus::Closed) {
                $ticket->closed_at = $resolvedAt->copy()->addHour();
            }
            $this->message($ticket, [
                'author_id' => $assignee?->id,
                'kind' => 'reply',
                'body' => 'This should now be resolved. Reply if you still need help and we will reopen the ticket.',
            ], $resolvedAt);
        }

        $ticket->save();

        if ($tagNames !== []) {
            $ticket->tags()->sync($tags->only($tagNames)->pluck('id')->all());
        }

        app(SlaService::class)->apply($ticket->fresh());
    }

    private function message(Ticket $ticket, array $attributes, $at): void
    {
        $ticket->messages()->create($attributes)->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }
}
