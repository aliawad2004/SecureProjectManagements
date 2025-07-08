<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Mail\TaskAssignedMail; // To assert mail is sent
use App\Notifications\TaskAssignedNotification; // To assert notification is created
use App\Notifications\TaskCompletedNotification; // To assert task completion notification

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue; // To fake queues
use Illuminate\Support\Facades\Mail;   // To fake mail
use Illuminate\Support\Facades\Notification; // To fake notifications
use Illuminate\Support\Facades\Event; // To fake events
use Carbon\Carbon; // For dates

class TaskFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $adminUser;
    /** @var \App\Models\User */
    protected $ownerUser;
    /** @var \App\Models\User */
    protected $pmUser; // Global Project Manager
    /** @var \App\Models\User */
    protected $memberUser; // Regular member of the project/team

    /** @var \App\Models\Team */
    protected $team;
    /** @var \App\Models\Project */
    protected $project; // Project for task creation

    protected function setUp(): void
    {
        parent::setUp();

        // Fake external services
        Queue::fake();
        Mail::fake();
        Notification::fake();
        Event::fake(); // Fake events as we have listeners attached to them

        // Create users
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('password'), 'role' => 'admin']);
        $this->ownerUser = User::factory()->create(['email' => 'owner@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
        $this->pmUser = User::factory()->create(['email' => 'pm@test.com', 'password' => Hash::make('password'), 'role' => 'project_manager']);
        $this->memberUser = User::factory()->create(['email' => 'member@test.com', 'password' => Hash::make('password'), 'role' => 'member']);

        // Create team and add users to it
        $this->team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $this->team->members()->attach($this->ownerUser->id, ['role' => 'team_admin']);
        $this->team->members()->attach($this->pmUser->id, ['role' => 'member']);
        $this->team->members()->attach($this->memberUser->id, ['role' => 'member']);

        // Create project and add users to it (PM as manager, Member as regular)
        $this->project = Project::factory()->create([
            'team_id' => $this->team->id,
            'created_by_user_id' => $this->pmUser->id,
        ]);
        $this->project->members()->attach($this->pmUser->id, ['role' => 'project_manager']);
        $this->project->members()->attach($this->memberUser->id, ['role' => 'member']);
    }

    // --- Create Task Tests ---

    public function test_project_manager_can_create_task(): void
    {
        $taskData = [
            'project_id' => $this->project->id,
            'name' => 'Design wireframes',
            'description' => 'Create wireframes for homepage.',
            'assigned_to_user_id' => $this->memberUser->id,
            'due_date' => Carbon::tomorrow()->format('Y-m-d'),
            'priority' => 'high',
            'status' => 'open',
        ];
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/tasks', $taskData);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Design wireframes']);

        $this->assertDatabaseHas('tasks', ['name' => 'Design wireframes', 'project_id' => $this->project->id]);

        // Assert mail and notification are sent via queue
        Mail::assertQueued(TaskAssignedMail::class, function ($mail) {
            return $mail->assignee->id === $this->memberUser->id;
        });
        Notification::assertSentTo($this->memberUser, TaskAssignedNotification::class);
    }

    public function test_member_can_create_task_if_in_project(): void
    {
        $taskData = [
            'project_id' => $this->project->id,
            'name' => 'Test project feature test task by member',
            'assigned_to_user_id' => $this->pmUser->id,
        ];
        $response = $this->actingAs($this->memberUser, 'sanctum')->postJson('/api/tasks', $taskData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tasks', ['name' => 'Test project feature test task by member', 'assigned_to_user_id' => $this->pmUser->id]);
    }

    public function test_unauthorized_user_cannot_create_task(): void
    {
        $unauthorizedUser = User::factory()->create(); // Not related to the project/team
        $taskData = [
            'project_id' => $this->project->id,
            'name' => 'Unauthorized task',
        ];
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->postJson('/api/tasks', $taskData);

        $response->assertStatus(403);
    }

    public function test_cannot_create_task_with_non_project_member_assignee(): void
    {
        $nonProjectMember = User::factory()->create(); // Not a member of the project
        $taskData = [
            'project_id' => $this->project->id,
            'name' => 'Task with invalid assignee',
            'assigned_to_user_id' => $nonProjectMember->id,
        ];
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/tasks', $taskData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['assigned_to_user_id']);
    }

    // --- Get All Tasks Tests ---

    public function test_admin_can_view_all_tasks(): void
    {
        Task::factory()->count(3)->create(['project_id' => $this->project->id]);
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/tasks');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'tasks');
    }

    public function test_project_manager_can_view_tasks_in_their_project(): void
    {
        Task::factory()->count(2)->create(['project_id' => $this->project->id]);
        $response = $this->actingAs($this->pmUser, 'sanctum')->getJson('/api/tasks');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'tasks');
    }

    public function test_member_can_view_tasks_in_their_project_or_assigned_to_them(): void
    {
        // Task assigned to memberUser in $this->project
        Task::factory()->create(['project_id' => $this->project->id, 'assigned_to_user_id' => $this->memberUser->id]);
        // Task in another project, assigned to someone else
        $anotherProject = Project::factory()->create(['team_id' => $this->team->id]);
        Task::factory()->create(['project_id' => $anotherProject->id]);

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/tasks');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'tasks'); // Only the one they are assigned or in their project
    }

    public function test_tasks_can_be_filtered_by_overdue_status(): void
    {
        // Create an overdue task
        Task::factory()->create([
            'project_id' => $this->project->id,
            'due_date' => Carbon::yesterday(),
            'status' => 'open',
        ]);
        // Create a future task
        Task::factory()->create([
            'project_id' => $this->project->id,
            'due_date' => Carbon::tomorrow(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->pmUser, 'sanctum')->getJson('/api/tasks?status=overdue');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'tasks');
    }


    // --- Get Specific Task Tests ---

    public function test_admin_can_view_any_specific_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/tasks/' . $task->id);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => $task->name]);
    }

    public function test_project_manager_can_view_specific_task_in_their_project(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $response = $this->actingAs($this->pmUser, 'sanctum')->getJson('/api/tasks/' . $task->id);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => $task->name]);
    }

    public function test_assigned_member_can_view_their_specific_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to_user_id' => $this->memberUser->id]);
        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/tasks/' . $task->id);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => $task->name]);
    }

    public function test_unauthorized_user_cannot_view_specific_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $unauthorizedUser = User::factory()->create(); // Not related to the project/team
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->getJson('/api/tasks/' . $task->id);

        $response->assertStatus(403);
    }

    // --- Update Task Tests ---

    public function test_project_manager_can_update_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'status' => 'open']);
        $updatedData = ['status' => 'completed', 'name' => 'Updated task name'];
        $response = $this->actingAs($this->pmUser, 'sanctum')->putJson('/api/tasks/' . $task->id, $updatedData);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'completed', 'name' => 'Updated task name']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed', 'name' => 'Updated task name']);

        // Assert notification dispatched on status change to 'completed'
        Notification::assertSentTo(
            [$this->pmUser, $this->ownerUser], // Project creator and team owner (who is also a team admin)
            TaskCompletedNotification::class,
            function ($notification) use ($task) {
                return $notification->task->id === $task->id;
            }
        );
    }

    public function test_assigned_member_can_update_their_task_status(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to_user_id' => $this->memberUser->id, 'status' => 'open']);
        $response = $this->actingAs($this->memberUser, 'sanctum')->putJson('/api/tasks/' . $task->id, ['status' => 'in_progress']);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'in_progress']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
    }

    public function test_unauthorized_user_cannot_update_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $unauthorizedUser = User::factory()->create();
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->putJson('/api/tasks/' . $task->id, ['status' => 'completed']);

        $response->assertStatus(403);
    }

    public function test_cannot_update_task_with_non_project_member_assignee(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assigned_to_user_id' => $this->memberUser->id]);
        $nonProjectMember = User::factory()->create();
        $response = $this->actingAs($this->pmUser, 'sanctum')->putJson('/api/tasks/' . $task->id, [
            'assigned_to_user_id' => $nonProjectMember->id,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['assigned_to_user_id']);
    }

    // --- Delete Task Tests ---

    public function test_project_manager_can_delete_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $response = $this->actingAs($this->pmUser, 'sanctum')->deleteJson('/api/tasks/' . $task->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_admin_can_delete_any_task(): void
    {
        $task = Task::factory()->create();
        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson('/api/tasks/' . $task->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_unauthorized_user_cannot_delete_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $unauthorizedUser = User::factory()->create();
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->deleteJson('/api/tasks/' . $task->id);

        $response->assertStatus(403);
    }
}
