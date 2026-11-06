<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertRedirect(route('customer.tickets.index'));
    }

    public function test_customers_cannot_open_the_agent_desk()
    {
        $user = User::factory()->create();
        $workspace = \App\Models\Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace->members()->attach($user, ['role' => \App\Enums\MemberRole::Customer->value]);

        $this->actingAs($user)
            ->get("/w/{$workspace->slug}")
            ->assertForbidden();
    }

    public function test_agents_see_their_own_analytics_instead_of_the_ticket_list(): void
    {
        $agent = User::factory()->create();
        $workspace = \App\Models\Workspace::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace->members()->attach($agent, ['role' => \App\Enums\MemberRole::Agent->value]);

        $this->actingAs($agent)
            ->get('/w/acme')
            ->assertOk()
            ->assertSee('desk/dashboard', false);
    }

    public function test_a_manager_without_a_team_still_opens_analytics(): void
    {
        $manager = User::factory()->create();
        $workspace = \App\Models\Workspace::create(['name' => 'Acme', 'slug' => 'acme-manager']);
        $workspace->members()->attach($manager, ['role' => \App\Enums\MemberRole::Manager->value]);

        $this->actingAs($manager)
            ->get('/w/acme-manager')
            ->assertOk()
            ->assertSee('desk/dashboard', false);
    }

    public function test_super_admins_are_sent_to_the_platform_console()
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('platform.workspaces.index'));
    }
}
