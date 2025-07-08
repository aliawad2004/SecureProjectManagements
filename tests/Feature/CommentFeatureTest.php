<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use App\Events\CommentCreated; // To assert event is dispatched
use App\Notifications\NewCommentNotification; // To assert notification is created

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;
use Mews\Purifier\Facades\Purifier;

class CommentFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $adminUser;
    /** @var \App\Models\User */
    protected $ownerUser;
    /** @var \App\Models\User */
    protected $pmUser; // Global Project Manager & Project Creator
    /** @var \App\Models\User */
    protected $memberUser; // Regular member of the project/team

    /** @var \App\Models\Team */
    protected $team;
    /** @var \App\Models\Project */
    protected $project;
    /** @var \App\Models\Task */
    protected $task;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake external services
        Queue::fake();
        Mail::fake();
        Notification::fake();
        Event::fake();

        // Create users
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('password'), 'role' => 'admin']);
        $this->ownerUser = User::factory()->create(['email' => 'owner@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
        $this->pmUser = User::factory()->create(['email' => 'pm@test.com', 'password' => Hash::make('password'), 'role' => 'project_manager']);
        $this->memberUser = User::factory()->create(['email' => 'member@test.com', 'password' => Hash::make('password'), 'role' => 'member']);

        // Create team and add users
        $this->team = Team::factory()->create(['owner_id' => $this->ownerUser->id]);
        $this->team->members()->attach($this->ownerUser->id, ['role' => 'team_admin']);
        $this->team->members()->attach($this->pmUser->id, ['role' => 'member']);
        $this->team->members()->attach($this->memberUser->id, ['role' => 'member']);

        // Create project and add users (PM as creator/manager, Member as regular)
        $this->project = Project::factory()->create([
            'team_id' => $this->team->id,
            'created_by_user_id' => $this->pmUser->id,
        ]);
        $this->project->members()->attach($this->pmUser->id, ['role' => 'project_manager']);
        $this->project->members()->attach($this->memberUser->id, ['role' => 'member']);

        // Create a task within the project
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to_user_id' => $this->memberUser->id,
        ]);
    }

    // --- Create Comment Tests ---

    public function test_project_manager_can_add_comment_to_project(): void
    {
        $commentContent = 'A new comment on the project.';
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/comments', [
            'content' => $commentContent,
            'commentable_type' => 'project',
            'commentable_id' => $this->project->id,
        ]);

        $response->assertStatus(201)
            // <--- التعديل هنا: تطهير المحتوى المتوقع
            ->assertJsonFragment(['content' => Purifier::clean($commentContent)]); // Assert on sanitized content

        $this->assertDatabaseHas('comments', [
            'content' => Purifier::clean($commentContent), // Assert on sanitized content in DB
            'user_id' => $this->pmUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);

        // Assert event and notification are dispatched
        Event::assertDispatched(CommentCreated::class, function ($event) {
            return $event->comment->commentable_id === $this->project->id;
        });
        Notification::assertSentTo($this->memberUser, NewCommentNotification::class); // Member should be notified
    }

    public function test_project_manager_can_add_comment_to_task(): void
    {
        $commentContent = 'A new comment on the task.';
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/comments', [
            'content' => $commentContent,
            'commentable_type' => 'task',
            'commentable_id' => $this->task->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => Purifier::clean($commentContent)]); // Assert on sanitized content

        $this->assertDatabaseHas('comments', [
            'content' => Purifier::clean($commentContent), // Assert on sanitized content in DB
            'user_id' => $this->pmUser->id,
            'commentable_id' => $this->task->id,
            'commentable_type' => get_class($this->task),
        ]);
        Notification::assertSentTo($this->memberUser, NewCommentNotification::class); // Task assignee should be notified
    }

    public function test_comment_content_is_sanitized(): void
    {
        $maliciousContent = 'Hello <script>alert("XSS");</script> world!';
        $expectedContent = Purifier::clean($maliciousContent); // Get the expected sanitized content

        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/comments', [
            'content' => $maliciousContent,
            'commentable_type' => 'project',
            'commentable_id' => $this->project->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => $expectedContent]);
        $this->assertDatabaseHas('comments', [
            'content' => $expectedContent,
        ]);
    }


    public function test_unauthorized_user_cannot_add_comment(): void
    {
        $unauthorizedUser = User::factory()->create(); // Not related to the project/team
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->postJson('/api/comments', [
            'content' => 'Unauthorized comment.',
            'commentable_type' => 'project',
            'commentable_id' => $this->project->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_add_comment_to_non_existent_resource(): void
    {
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/comments', [
            'content' => 'Comment on non-existent project.',
            'commentable_type' => 'project',
            'commentable_id' => 99999, // Non-existent ID
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commentable_id']);
    }

    // --- Get All Comments Tests ---

    public function test_admin_can_view_all_comments(): void
    {
        // Create comments specifically for this project
        Comment::factory()->count(2)->create([
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
            'user_id' => $this->pmUser->id, // Ensure user_id is provided by factory or explicitly
        ]);
        // Create a comment for another resource (e.g., task)
        Comment::factory()->count(1)->create([
            'commentable_id' => $this->task->id,
            'commentable_type' => get_class($this->task),
            'user_id' => $this->memberUser->id, // Ensure user_id is provided
        ]);

        // Test viewing comments for the specific project
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/comments?commentable_type=project&commentable_id=' . $this->project->id);
        $response->assertStatus(200)
            ->assertJsonCount(2, 'comments'); // Expect 2 comments for this project

        // Test viewing comments for the specific task
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/comments?commentable_type=task&commentable_id=' . $this->task->id);
        $response->assertStatus(200)
            ->assertJsonCount(1, 'comments'); // Expect 1 comment for this task
    }

    public function test_project_member_can_view_comments_for_their_project_resource(): void
    {
        // Create comment on project
        Comment::factory()->create([
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        // Create comment on task
        Comment::factory()->create([
            'commentable_id' => $this->task->id,
            'commentable_type' => get_class($this->task),
        ]);

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/comments?commentable_type=project&commentable_id=' . $this->project->id);
        $response->assertStatus(200)
            ->assertJsonCount(1, 'comments');

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/comments?commentable_type=task&commentable_id=' . $this->task->id);
        $response->assertStatus(200)
            ->assertJsonCount(1, 'comments');
    }

    public function test_unauthorized_user_cannot_view_comments_for_resource(): void
    {
        $comment = Comment::factory()->create([
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser, 'sanctum')->getJson('/api/comments?commentable_type=project&commentable_id=' . $this->project->id);
        $response->assertStatus(403);
    }

    public function test_cannot_get_comments_without_specifying_resource(): void
    {
        $response = $this->actingAs($this->pmUser, 'sanctum')->getJson('/api/comments');
        $response->assertStatus(400); // Bad Request because commentable_type/id are required
    }

    // --- Get Specific Comment Tests ---

    public function test_admin_can_view_any_specific_comment(): void
    {
        $comment = Comment::factory()->create([
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/comments/' . $comment->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['content' => $comment->content]);
    }

    public function test_comment_owner_can_view_their_specific_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->memberUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/comments/' . $comment->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['content' => $comment->content]);
    }

    public function test_project_manager_can_view_specific_comment_in_their_project(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->memberUser->id, // Another user's comment
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $response = $this->actingAs($this->pmUser, 'sanctum')->getJson('/api/comments/' . $comment->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['content' => $comment->content]);
    }

    public function test_unauthorized_user_cannot_view_specific_comment(): void
    {
        $comment = Comment::factory()->create([
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $unauthorizedUser = User::factory()->create();
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->getJson('/api/comments/' . $comment->id);

        $response->assertStatus(403);
    }

    // --- Update Comment Tests ---

    public function test_comment_owner_can_update_their_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->memberUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
            'content' => 'Original comment',
        ]);
        $updatedContent = 'Updated comment by owner.';
        $response = $this->actingAs($this->memberUser, 'sanctum')->putJson('/api/comments/' . $comment->id, [
            'content' => $updatedContent,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['content' => Purifier::clean($updatedContent)]); // Assert on sanitized content
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'content' => Purifier::clean($updatedContent)]); // Assert on sanitized content in DB
    }

    public function test_project_manager_can_update_any_comment_in_their_project(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->memberUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
            'content' => 'Original comment by member',
        ]);
        $updatedContent = 'Updated by PM.';
        $response = $this->actingAs($this->pmUser, 'sanctum')->putJson('/api/comments/' . $comment->id, [
            'content' => $updatedContent,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['content' => Purifier::clean($updatedContent)]); // Assert on sanitized content
        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'content' => Purifier::clean($updatedContent)]); // Assert on sanitized content in DB
    }

    public function test_admin_can_update_any_comment(): void
    {
        $comment = Comment::factory()->create();
        $updatedContent = 'Updated by Admin.';
        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson('/api/comments/' . $comment->id, [
            'content' => $updatedContent,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['content' => Purifier::clean($updatedContent)]); // Assert on sanitized content
    }

    public function test_unauthorized_user_cannot_update_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->pmUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $unauthorizedUser = User::factory()->create();
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->putJson('/api/comments/' . $comment->id, [
            'content' => 'Unauthorized update attempt.',
        ]);

        $response->assertStatus(403);
    }

    // --- Delete Comment Tests ---

    public function test_comment_owner_can_delete_their_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->memberUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $response = $this->actingAs($this->memberUser, 'sanctum')->deleteJson('/api/comments/' . $comment->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_project_manager_can_delete_any_comment_in_their_project(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->memberUser->id, // Comment by another user
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $response = $this->actingAs($this->pmUser, 'sanctum')->deleteJson('/api/comments/' . $comment->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $comment = Comment::factory()->create(); // Any comment
        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson('/api/comments/' . $comment->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_unauthorized_user_cannot_delete_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->pmUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
        $unauthorizedUser = User::factory()->create();
        $response = $this->actingAs($unauthorizedUser, 'sanctum')->deleteJson('/api/comments/' . $comment->id);

        $response->assertStatus(403);
    }
}
