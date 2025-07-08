<?php

namespace Tests\Unit;

use Tests\TestCase; 
use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase; 

class UserAuthHelpersTest extends TestCase
{
    use RefreshDatabase; // Resets database for each test

    public function test_user_has_role_method(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $memberUser = User::factory()->create(['role' => 'member']);
        $pmUser = User::factory()->create(['role' => 'project_manager']);

        $this->assertTrue($adminUser->hasRole('admin'));
        $this->assertFalse($adminUser->hasRole('member'));

        $this->assertTrue($memberUser->hasRole('member'));
        $this->assertFalse($memberUser->hasRole('admin'));

        $this->assertTrue($pmUser->hasRole('project_manager'));
        $this->assertFalse($pmUser->hasRole('member'));
    }

    public function test_user_owns_team_method(): void
    {
        $owner = User::factory()->create();
        $nonOwner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->ownsTeam($team));
        $this->assertFalse($nonOwner->ownsTeam($team));
    }

    public function test_user_belongs_to_team_method(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user->id, ['role' => 'member']);

        $anotherUser = User::factory()->create();
        $anotherTeam = Team::factory()->create();

        $this->assertTrue($user->belongsToTeam($team));
        $this->assertFalse($anotherUser->belongsToTeam($team));
        $this->assertFalse($user->belongsToTeam($anotherTeam));
    }

    public function test_user_has_team_role_method(): void
    {
        $adminMember = User::factory()->create();
        $regularMember = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($adminMember->id, ['role' => 'team_admin']);
        $team->members()->attach($regularMember->id, ['role' => 'member']);

        $this->assertTrue($adminMember->hasTeamRole($team, 'team_admin'));
        $this->assertFalse($adminMember->hasTeamRole($team, 'member'));

        $this->assertTrue($regularMember->hasTeamRole($team, 'member'));
        $this->assertFalse($regularMember->hasTeamRole($team, 'team_admin'));
    }

    public function test_user_belongs_to_project_method(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $project = Project::factory()->create(['team_id' => $team->id]);
        $project->members()->attach($user->id, ['role' => 'member']);

        $anotherUser = User::factory()->create();

        $this->assertTrue($user->belongsToProject($project));
        $this->assertFalse($anotherUser->belongsToProject($project));
    }

    public function test_user_has_project_role_method(): void
    {
        $pmMember = User::factory()->create();
        $regularMember = User::factory()->create();
        $team = Team::factory()->create();
        $project = Project::factory()->create(['team_id' => $team->id]);

        $project->members()->attach($pmMember->id, ['role' => 'project_manager']);
        $project->members()->attach($regularMember->id, ['role' => 'member']);

        $this->assertTrue($pmMember->hasProjectRole($project, 'project_manager'));
        $this->assertFalse($pmMember->hasProjectRole($project, 'member'));

        $this->assertTrue($regularMember->hasProjectRole($project, 'member'));
        $this->assertFalse($regularMember->hasProjectRole($project, 'project_manager'));
    }
}
