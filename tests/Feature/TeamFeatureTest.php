<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class TeamFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $adminUser;
    /** @var \App\Models\User */
    protected $ownerUser; // Will be the owner of the team
    /** @var \App\Models\User */
    protected $pmUser; // Global Project Manager
    /** @var \App\Models\User */
    protected $memberUser; // Regular member of the project/team (should NOT create teams)

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with specific roles and emails for testing
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('password'), 'role' => 'admin']);
        $this->ownerUser = User::factory()->create(['email' => 'owner@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
        $this->pmUser = User::factory()->create(['email' => 'pm@test.com', 'password' => Hash::make('password'), 'role' => 'project_manager']);
        $this->memberUser = User::factory()->create(['email' => 'member@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
    }

    // --- Create Team Tests ---

    public function test_team_owner_cannot_create_first_team(): void // Renamed to reflect expected failure
    {
        $teamData = ['name' => 'Owner First Team Attempt'];
        $response = $this->actingAs($this->ownerUser, 'sanctum')->postJson('/api/teams', $teamData);

        $response->assertStatus(403) // Expect 403 Forbidden, as new ownerUser is just a 'member' initially
            ->assertJson(['message' => 'This action is unauthorized.']);
        $this->assertDatabaseMissing('teams', ['name' => 'Owner First Team Attempt']); // Ensure no team is created
    }
   

    public function test_project_manager_can_create_team(): void // Renamed from admin for clarity
    {
        $teamData = ['name' => 'PM Team'];
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'PM Team']);
        $this->assertDatabaseHas('teams', ['name' => 'PM Team', 'owner_id' => $this->pmUser->id]);
    }

    public function test_admin_can_create_team(): void
    {
        $teamData = ['name' => 'Admin Team'];
        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/teams', $teamData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Admin Team']);
        $this->assertDatabaseHas('teams', ['name' => 'Admin Team', 'owner_id' => $this->adminUser->id]);
    }

    public function test_member_cannot_create_team(): void // Renamed and adjusted for strict policy
    {
        $teamData = ['name' => 'Member Cannot Create Team'];
        $response = $this->actingAs($this->memberUser, 'sanctum')->postJson('/api/teams', $teamData);

        $response->assertStatus(403) // Expect 403 as regular member is restricted
            ->assertJson(['message' => 'This action is unauthorized.']);
    }


    public function test_cannot_create_team_with_duplicate_name(): void
    {
        $teamData = ['name' => 'Unique Team'];
        // Create the first team by PM to ensure they are authorized
        $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/teams', $teamData)->assertStatus(201);

        // Attempt to create with duplicate name by Admin (who is also authorized)
        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/teams', $teamData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_team_unauthenticated(): void
    {
        $teamData = ['name' => 'Unauth Team'];
        $response = $this->postJson('/api/teams', $teamData); // No actingAs

        $response->assertStatus(401);
    }

    // --- Get All Teams Tests --- (These should be fine)

    public function test_admin_can_view_all_teams(): void
    {
        // Ensure ownerUser has an owned team that admin can see
        Team::factory()->create(['owner_id' => $this->ownerUser->id]); // This is 1 team
        Team::factory()->count(3)->create(); 

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'teams'); // Now expects 4
    }

    public function test_team_owner_can_view_only_their_teams(): void
    {
        $ownedTeam = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        Team::factory()->create(); // Another team by different user
        Team::factory()->create(); // Another team by different user

        $response = $this->actingAs($this->ownerUser, 'sanctum')->getJson('/api/teams');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'teams') // Only the one they own
            ->assertJsonFragment(['name' => $ownedTeam->name]);
    }

    // --- Get Specific Team Tests --- (These should be fine)

    public function test_admin_can_view_any_specific_team(): void
    {
        $team = Team::factory()->create(); // Created by a random user by factory
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/teams/' . $team->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $team->name]);
    }

    public function test_team_owner_can_view_their_specific_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $response = $this->actingAs($this->ownerUser, 'sanctum')->getJson('/api/teams/' . $team->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $team->name]);
    }

    public function test_team_member_can_view_their_specific_team(): void
    {
        $team = Team::factory()->create();
        $team->members()->attach($this->memberUser->id, ['role' => 'member']);

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/teams/' . $team->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $team->name]);
    }

    public function test_unauthorized_user_cannot_view_specific_team(): void
    {
        $team = Team::factory()->create(); // Team owned by another user
        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/teams/' . $team->id);

        $response->assertStatus(403);
    }

    // --- Update Team Tests --- (These should be fine)

    public function test_team_owner_can_update_their_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $updatedName = 'Updated Owner Team Name';
        $response = $this->actingAs($this->ownerUser, 'sanctum')->putJson('/api/teams/' . $team->id, ['name' => $updatedName]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $updatedName]);
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => $updatedName]);
    }

    public function test_admin_can_update_any_team(): void
    {
        $team = Team::factory()->create(); // Team owned by a random user
        $updatedName = 'Updated By Admin';
        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson('/api/teams/' . $team->id, ['name' => $updatedName]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $updatedName]);
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => $updatedName]);
    }

    public function test_unauthorized_user_cannot_update_team(): void
    {
        $team = Team::factory()->create(); // Team owned by a random user
        $response = $this->actingAs($this->memberUser, 'sanctum')->putJson('/api/teams/' . $team->id, ['name' => 'Attempted Update']);

        $response->assertStatus(403);
    }

    // --- Delete Team Tests --- (These should be fine)

    public function test_team_owner_can_delete_their_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $response = $this->actingAs($this->ownerUser, 'sanctum')->deleteJson('/api/teams/' . $team->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Team deleted successfully']);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_admin_can_delete_any_team(): void
    {
        $team = Team::factory()->create(); // Team owned by a random user
        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson('/api/teams/' . $team->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Team deleted successfully']);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_unauthorized_user_cannot_delete_team(): void
    {
        $team = Team::factory()->create(); // Team owned by a random user
        $response = $this->actingAs($this->memberUser, 'sanctum')->deleteJson('/api/teams/' . $team->id);

        $response->assertStatus(403);
    }

    // --- Team Member Management Tests --- (These should be fine)

    public function test_team_owner_can_add_member_to_their_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $newUser = User::factory()->create();
        $response = $this->actingAs($this->ownerUser, 'sanctum')->postJson('/api/teams/' . $team->id . '/members', [
            'user_id' => $newUser->id,
            'role' => 'member'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Member added to team successfully']);
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $newUser->id, 'role' => 'member']);
    }

    public function test_admin_can_add_member_to_any_team(): void
    {
        $team = Team::factory()->create(); // Owned by random user
        $newUser = User::factory()->create();
        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/teams/' . $team->id . '/members', [
            'user_id' => $newUser->id,
            'role' => 'member'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Member added to team successfully']);
    }

    public function test_unauthorized_user_cannot_add_member_to_team(): void
    {
        $team = Team::factory()->create();
        $newUser = User::factory()->create();
        $response = $this->actingAs($this->memberUser, 'sanctum')->postJson('/api/teams/' . $team->id . '/members', [
            'user_id' => $newUser->id,
            'role' => 'member'
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_add_existing_member_to_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $team->members()->attach($this->memberUser->id, ['role' => 'member']); // Add already

        $response = $this->actingAs($this->ownerUser, 'sanctum')->postJson('/api/teams/' . $team->id . '/members', [
            'user_id' => $this->memberUser->id,
            'role' => 'member'
        ]);

        $response->assertStatus(409) // Conflict
            ->assertJsonFragment(['message' => 'User is already a member of this team.']);
    }

    public function test_team_owner_can_update_member_role_in_their_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $member = User::factory()->create();
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($this->ownerUser, 'sanctum')->putJson('/api/teams/' . $team->id . '/members/' . $member->id, [
            'role' => 'team_admin'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Member role updated successfully']);
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => 'team_admin']);
    }

    public function test_admin_can_update_member_role_in_any_team(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson('/api/teams/' . $team->id . '/members/' . $member->id, [
            'role' => 'team_admin'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Member role updated successfully']);
    }

    public function test_team_owner_can_remove_member_from_their_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $member = User::factory()->create();
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($this->ownerUser, 'sanctum')->deleteJson('/api/teams/' . $team->id . '/members/' . $member->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Member removed from team successfully']);
        $this->assertDatabaseMissing('team_user', ['team_id' => $team->id, 'user_id' => $member->id]);
    }

    public function test_admin_can_remove_member_from_any_team(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson('/api/teams/' . $team->id . '/members/' . $member->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Member removed from team successfully']);
    }

    public function test_team_owner_cannot_remove_themselves_from_their_team(): void
    {
        $team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        // Owner is automatically added as team_admin

        $response = $this->actingAs($this->ownerUser, 'sanctum')->deleteJson('/api/teams/' . $team->id . '/members/' . $this->ownerUser->id);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Team owner cannot be removed as a member. Transfer ownership first or delete the team.']);
    }
}
