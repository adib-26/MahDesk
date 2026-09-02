<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $currentSessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentSessionId = str_repeat('a', 40);
        $this->withCookie(config('session.cookie'), $this->currentSessionId);
    }

    public function test_user_can_view_only_their_own_sessions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherSessionId = str_repeat('b', 40);

        $this->createSession($user, $this->currentSessionId, now()->timestamp, 'Current Browser');
        $this->createSession($user, $otherSessionId, now()->subMinute()->timestamp, 'Other Browser');
        $this->createSession($otherUser, str_repeat('c', 40), now()->timestamp, 'Private Browser');

        $response = $this
            ->actingAs($user)
            ->get('/settings/sessions', [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('component', 'settings/sessions')
            ->assertJsonCount(2, 'props.sessions')
            ->assertJsonPath('props.sessions.0.id', $this->currentSessionId)
            ->assertJsonPath('props.sessions.0.user_agent', 'Current Browser')
            ->assertJsonPath('props.sessions.0.is_current', true)
            ->assertJsonPath('props.sessions.1.id', $otherSessionId)
            ->assertJsonPath('props.sessions.1.is_current', false);
    }

    public function test_user_can_revoke_one_of_their_other_sessions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $sessionId = str_repeat('b', 40);
        $otherUserSessionId = str_repeat('c', 40);

        $this->createSession($user, $this->currentSessionId, now()->timestamp);
        $this->createSession($user, $sessionId, now()->subMinute()->timestamp);
        $this->createSession($otherUser, $otherUserSessionId, now()->timestamp);

        $response = $this
            ->actingAs($user)
            ->from('/settings/sessions')
            ->delete("/settings/sessions/{$sessionId}");

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/sessions');

        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $this->currentSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $otherUserSessionId]);
    }

    public function test_user_cannot_revoke_someone_elses_session(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $sessionId = str_repeat('b', 40);

        $this->createSession($otherUser, $sessionId, now()->timestamp);

        $response = $this
            ->actingAs($user)
            ->delete("/settings/sessions/{$sessionId}");

        $response->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $otherUser->id]);
    }

    public function test_user_can_revoke_the_current_session(): void
    {
        $user = User::factory()->create();

        $this->createSession($user, $this->currentSessionId, now()->timestamp);

        $response = $this
            ->actingAs($user)
            ->delete("/settings/sessions/{$this->currentSessionId}");

        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => $this->currentSessionId]);
    }

    public function test_user_can_revoke_all_other_sessions_after_confirming_their_password(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherSessionId = str_repeat('b', 40);
        $anotherSessionId = str_repeat('c', 40);
        $otherUserSessionId = str_repeat('d', 40);

        $this->createSession($user, $this->currentSessionId, now()->timestamp);
        $this->createSession($user, $otherSessionId, now()->subMinute()->timestamp);
        $this->createSession($user, $anotherSessionId, now()->subMinutes(2)->timestamp);
        $this->createSession($otherUser, $otherUserSessionId, now()->timestamp);

        $response = $this
            ->actingAs($user)
            ->from('/settings/sessions')
            ->delete('/settings/sessions/other', [
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/sessions');

        $this->assertDatabaseHas('sessions', ['id' => $this->currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => $anotherSessionId]);
        $this->assertDatabaseHas('sessions', ['id' => $otherUserSessionId]);
    }

    public function test_current_password_is_required_to_revoke_all_other_sessions(): void
    {
        $user = User::factory()->create();
        $otherSessionId = str_repeat('b', 40);

        $this->createSession($user, $this->currentSessionId, now()->timestamp);
        $this->createSession($user, $otherSessionId, now()->subMinute()->timestamp);

        $response = $this
            ->actingAs($user)
            ->from('/settings/sessions')
            ->delete('/settings/sessions/other', [
                'current_password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/settings/sessions');

        $this->assertDatabaseHas('sessions', ['id' => $otherSessionId]);
    }

    private function createSession(User $user, string $id, int $lastActivity, string $userAgent = 'Test Browser'): void
    {
        DB::table(config('session.table'))->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => base64_encode(''),
            'last_activity' => $lastActivity,
        ]);
    }
}
