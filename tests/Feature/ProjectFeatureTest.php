<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ProjectFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $adminUser;
    /** @var \App\Models\User */
    protected $ownerUser; // Owner of the team
    /** @var \App\Models\User */
    protected $pmUser; // Global Project Manager
    /** @var \App\Models\User */
    protected $memberUser; // Regular member

    /** @var \App\Models\Team */
    protected $team;
    /** @var \App\Models\Project */
    protected $projectByPm; // Project created by PM
    /** @var \App\Models\Project */
    protected $projectByOwner; // Project created by Team Owner

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('password'), 'role' => 'admin']);
        $this->ownerUser = User::factory()->create(['email' => 'owner@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
        $this->pmUser = User::factory()->create(['email' => 'pm@test.com', 'password' => Hash::make('password'), 'role' => 'project_manager']);
        $this->memberUser = User::factory()->create(['email' => 'member@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
        $this->unauthorizedUser = User::factory()->create(['email' => 'unauth@test.com']);

        // Create main team and add users to it
        $this->team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $this->team->members()->attach($this->ownerUser->id, ['role' => 'team_admin']);
        $this->team->members()->attach($this->pmUser->id, ['role' => 'member']);
        $this->team->members()->attach($this->memberUser->id, ['role' => 'member']);

        // Create another team, not related to the above users, for isolation tests
        $this->anotherTeam = Team::factory()->create();

        // Create projects for different creators/roles within the MAIN team
        $this->projectByPm = Project::factory()->create([
            'team_id' => $this->team->id,
            'created_by_user_id' => $this->pmUser->id,
            'name' => 'PMs Project',
        ]);
        $this->projectByPm->members()->attach($this->pmUser->id, ['role' => 'project_manager']);

        $this->projectByOwner = Project::factory()->create([
            'team_id' => $this->team->id,
            'created_by_user_id' => $this->ownerUser->id,
            'name' => 'Owner Project',
        ]);
        $this->projectByOwner->members()->attach($this->ownerUser->id, ['role' => 'project_manager']);
    }

    // --- Create Project Tests ---

    public function test_project_manager_can_create_project(): void
    {
        $projectData = [
            'team_id' => $this->team->id,
            'name' => 'New Project by PM',
            'description' => 'A new project for testing.',
            'due_date' => '2026-01-01',
        ];
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/projects', $projectData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Project by PM']);

        $this->assertDatabaseHas('projects', ['name' => 'New Project by PM', 'created_by_user_id' => $this->pmUser->id]);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $response->json('project.id'),
            'user_id' => $this->pmUser->id,
            'role' => 'project_manager'
        ]);
    }

    public function test_team_owner_can_create_project_in_their_team(): void
    {
        $projectData = [
            'team_id' => $this->team->id,
            'name' => 'New Project by Owner',
            'description' => 'Another project for testing.',
            'due_date' => '2026-02-01',
        ];
        $response = $this->actingAs($this->ownerUser, 'sanctum')->postJson('/api/projects', $projectData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Project by Owner']);
        $this->assertDatabaseHas('projects', ['name' => 'New Project by Owner', 'created_by_user_id' => $this->ownerUser->id]);
    }

    public function test_admin_can_create_project(): void
    {
        $projectData = [
            'team_id' => $this->team->id,
            'name' => 'New Project by Admin',
            'description' => 'Admin created project.',
            'due_date' => '2026-03-01',
        ];
        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/projects', $projectData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Project by Admin']);
        $this->assertDatabaseHas('projects', ['name' => 'New Project by Admin', 'created_by_user_id' => $this->adminUser->id]);
    }

    public function test_member_cannot_create_project(): void
    {
        $projectData = [
            'team_id' => $this->team->id,
            'name' => 'Member Project',
            'description' => 'Unauthorized project creation.',
        ];
        $response = $this->actingAs($this->memberUser, 'sanctum')->postJson('/api/projects', $projectData);

        $response->assertStatus(403); // Members cannot create projects as per policy
    }

    public function test_cannot_create_project_in_unauthorized_team(): void
    {
        $anotherTeam = Team::factory()->create(); // Not owned by pmUser
        $projectData = [
            'team_id' => $anotherTeam->id,
            'name' => 'Unauthorized Team Project',
            'description' => 'Unauthorized team project creation.',
        ];
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/projects', $projectData);

        $response->assertStatus(403); // Policy should prevent creation in unauthorized team
    }

    // --- Get All Projects Tests ---

    public function test_admin_can_view_all_projects(): void
    {
        // Create a project in the anotherTeam, so admin sees it, but PM/Member don't automatically
        Project::factory()->create(['team_id' => $this->anotherTeam->id]); // This makes 3 projects total

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'projects'); // Now expects 3 (2 in main team + 1 in another team)
    }

    public function test_project_manager_can_view_their_projects(): void
    {
       
        $response = $this->actingAs($this->pmUser, 'sanctum')->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'projects'); // Corrected expectation: PM sees both their own and owner's project (as they are in the same team)
    }


    public function test_team_owner_can_view_their_teams_projects(): void
    {
        $response = $this->actingAs($this->ownerUser, 'sanctum')->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'projects'); // PM's project and Owner's project (both in their team)
    }

    public function test_member_can_view_projects_they_belong_to(): void
    {
        
        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'projects'); 
    }

    // --- Get Specific Project Tests ---

    public function test_admin_can_view_any_specific_project(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/projects/' . $this->projectByPm->id);
        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $this->projectByPm->name]);
    }

    public function test_project_manager_can_view_their_specific_project(): void
    {
        $response = $this->actingAs($this->pmUser, 'sanctum')->getJson('/api/projects/' . $this->projectByPm->id);
        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $this->projectByPm->name]);
    }

    public function test_team_owner_can_view_any_project_in_their_team(): void
    {
        $response = $this->actingAs($this->ownerUser, 'sanctum')->getJson('/api/projects/' . $this->projectByPm->id);
        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $this->projectByPm->name]);
    }

    public function test_member_can_view_specific_project_they_belong_to(): void
    {
        $this->projectByPm->members()->attach($this->memberUser->id, ['role' => 'member']);
        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/projects/' . $this->projectByPm->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $this->projectByPm->name]);
    }

    public function test_unauthorized_user_cannot_view_specific_project(): void
    {
        $unauthorizedUser = User::factory()->create(); // Not related to the team or project
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->getJson('/api/projects/' . $this->projectByPm->id);

        $response->assertStatus(403);
    }

    // --- Update Project Tests ---

    public function test_project_manager_can_update_their_project(): void
    {
        $updatedData = ['name' => 'Updated PMs Project', 'status' => 'completed'];
        $response = $this->actingAs($this->pmUser, 'sanctum')->putJson('/api/projects/' . $this->projectByPm->id, $updatedData);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated PMs Project', 'status' => 'completed']);
        $this->assertDatabaseHas('projects', ['id' => $this->projectByPm->id, 'name' => 'Updated PMs Project', 'status' => 'completed']);
    }

    public function test_team_owner_can_update_project_in_their_team(): void
    {
        $updatedData = ['status' => 'in_progress']; // Updating PM's project
        $response = $this->actingAs($this->ownerUser, 'sanctum')->putJson('/api/projects/' . $this->projectByPm->id, $updatedData);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'in_progress']);
        $this->assertDatabaseHas('projects', ['id' => $this->projectByPm->id, 'status' => 'in_progress']);
    }

    public function test_admin_can_update_any_project(): void
    {
        $updatedData = ['description' => 'Admin updated this project.'];
        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson('/api/projects/' . $this->projectByPm->id, $updatedData);

        $response->assertStatus(200)
            ->assertJsonFragment(['description' => 'Admin updated this project.']);
        $this->assertDatabaseHas('projects', ['id' => $this->projectByPm->id, 'description' => 'Admin updated this project.']);
    }

    public function test_member_cannot_update_project(): void
    {
        $this->projectByPm->members()->attach($this->memberUser->id, ['role' => 'member']); // Add member to project
        $updatedData = ['name' => 'Attempted Update'];
        $response = $this->actingAs($this->memberUser, 'sanctum')->putJson('/api/projects/' . $this->projectByPm->id, $updatedData);

        $response->assertStatus(403);
    }

    // --- Delete Project Tests ---

    public function test_project_manager_can_delete_their_project(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id, 'created_by_user_id' => $this->pmUser->id]);
        $project->members()->attach($this->pmUser->id, ['role' => 'project_manager']); // Ensure PM is manager

        $response = $this->actingAs($this->pmUser, 'sanctum')->deleteJson('/api/projects/' . $project->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_team_owner_can_delete_project_in_their_team(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id, 'created_by_user_id' => User::factory()->create()->id]); // Some other user created in owner's team
        $response = $this->actingAs($this->ownerUser, 'sanctum')->deleteJson('/api/projects/' . $project->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_admin_can_delete_any_project(): void
    {
        $project = Project::factory()->create(); // Any project
        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson('/api/projects/' . $project->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_member_cannot_delete_project(): void
    {
        $this->projectByPm->members()->attach($this->memberUser->id, ['role' => 'member']); // Add member to project
        $response = $this->actingAs($this->memberUser, 'sanctum')->deleteJson('/api/projects/' . $this->projectByPm->id);

        $response->assertStatus(403);
    }

    // --- Project Member Management Tests ---

    public function test_project_manager_can_add_member_to_their_project(): void
    {
        $userToAdd = User::factory()->create();
        $this->team->members()->attach($userToAdd->id, ['role' => 'member']); // User must be a member of the team first

        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/projects/' . $this->projectByPm->id . '/members', [
            'user_id' => $userToAdd->id,
            'role' => 'member'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('project_user', ['project_id' => $this->projectByPm->id, 'user_id' => $userToAdd->id, 'role' => 'member']);
    }

    public function test_cannot_add_non_team_member_to_project(): void
    {
        $userToAdd = User::factory()->create(); // Not a member of the team
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/projects/' . $this->projectByPm->id . '/members', [
            'user_id' => $userToAdd->id,
            'role' => 'member'
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'User must be a member of the project\'s team to be added to the project.']);
    }

    public function test_cannot_add_existing_member_to_project(): void
    {
        $this->projectByPm->members()->attach($this->memberUser->id, ['role' => 'member']); // Add already
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/projects/' . $this->projectByPm->id . '/members', [
            'user_id' => $this->memberUser->id,
            'role' => 'member'
        ]);

        $response->assertStatus(409); // Conflict
    }

    public function test_project_manager_can_update_member_role_in_their_project(): void
    {
        $member = User::factory()->create();
        $this->projectByPm->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($this->pmUser, 'sanctum')->putJson('/api/projects/' . $this->projectByPm->id . '/members/' . $member->id, [
            'role' => 'project_manager'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('project_user', ['project_id' => $this->projectByPm->id, 'user_id' => $member->id, 'role' => 'project_manager']);
    }

    public function test_project_manager_can_remove_member_from_their_project(): void
    {
        $member = User::factory()->create();
        $this->projectByPm->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($this->pmUser, 'sanctum')->deleteJson('/api/projects/' . $this->projectByPm->id . '/members/' . $member->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('project_user', ['project_id' => $this->projectByPm->id, 'user_id' => $member->id]);
    }

    public function test_cannot_remove_sole_project_manager_from_project(): void
    {
        
        $response = $this->actingAs($this->pmUser, 'sanctum')->deleteJson('/api/projects/' . $this->projectByPm->id . '/members/' . $this->pmUser->id);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Cannot remove the sole project manager. Assign another manager first or delete the project.']);
    }
}
