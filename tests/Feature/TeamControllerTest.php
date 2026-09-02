<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Http\Controllers\TeamController;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_manage_workspace_teams_and_members(): void
    {
        $workspace = $this->workspace('alpha');
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => MemberRole::Admin->value]);
        $workspace->members()->attach($member, ['role' => MemberRole::Agent->value]);
        $this->actingAs($admin);

        $controller = app(TeamController::class);
        $controller->store($this->request(['name' => 'Escalations']), $workspace);

        $team = Team::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('Escalations', $team->name);

        $controller->storeMember($this->request(['member_id' => $member->id]), $workspace, $team);
        $this->assertTrue($team->fresh()->hasMember($member));

        $controller->update($this->request(['name' => 'Priority Support']), $workspace, $team);
        $this->assertSame('Priority Support', $team->fresh()->name);

        $controller->destroyMember($workspace, $team->fresh(), $member);
        $this->assertFalse($team->fresh()->hasMember($member));

        $controller->destroy($workspace, $team->fresh());
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_team_and_member_identifiers_are_limited_to_the_current_workspace(): void
    {
        $workspace = $this->workspace('alpha');
        $otherWorkspace = $this->workspace('bravo');
        $admin = User::factory()->create();
        $outsideMember = User::factory()->create();
        $workspace->members()->attach($admin, ['role' => MemberRole::Admin->value]);
        $otherWorkspace->members()->attach($outsideMember, ['role' => MemberRole::Agent->value]);
        $team = Team::create(['workspace_id' => $workspace->id, 'name' => 'Support']);
        $outsideTeam = Team::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Support']);
        $this->actingAs($admin);

        $controller = app(TeamController::class);

        try {
            $controller->storeMember($this->request(['member_id' => $outsideMember->id]), $workspace, $team);
            $this->fail('Expected validation to reject a member from another workspace.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('member_id', $exception->errors());
        }

        $this->assertFalse($team->hasMember($outsideMember));

        try {
            $controller->update($this->request(['name' => 'Cross tenant']), $workspace, $outsideTeam);
            $this->fail('Expected a cross-workspace team to be hidden.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        try {
            $controller->destroyMember($workspace, $team, $outsideMember);
            $this->fail('Expected a cross-workspace member to be hidden.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_non_admin_workspace_members_cannot_manage_teams(): void
    {
        $workspace = $this->workspace('alpha');
        $agent = User::factory()->create();
        $workspace->members()->attach($agent, ['role' => MemberRole::Agent->value]);
        $this->actingAs($agent);

        $this->expectException(AuthorizationException::class);

        app(TeamController::class)->store($this->request(['name' => 'Should not exist']), $workspace);
    }

    private function workspace(string $slug): Workspace
    {
        return Workspace::create(['name' => ucfirst($slug), 'slug' => $slug]);
    }

    /**
     * A controller-level request keeps these tests independent from routes the
     * root agent will wire after this artifact is complete.
     */
    private function request(array $input): Request
    {
        $request = Request::create('/_test/teams', 'POST', $input);
        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }
}
