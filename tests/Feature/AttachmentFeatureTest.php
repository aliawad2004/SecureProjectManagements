<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile; // For faking file uploads
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; // To fake storage
use Illuminate\Support\Facades\Log; // For debugging during testing (optional)

class AttachmentFeatureTest extends TestCase
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
    /** @var \App\Models\User */
    protected $unauthorizedUser; // User without access

    /** @var \App\Models\Team */
    protected $team;
    /** @var \App\Models\Project */
    protected $project;
    /** @var \App\Models\Task */
    protected $task;
    /** @var \App\Models\Comment */
    protected $comment;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the storage disk to prevent actual file writes
        Storage::fake('local');

        // Create users
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('password'), 'role' => 'admin']);
        $this->ownerUser = User::factory()->create(['email' => 'owner@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
        $this->pmUser = User::factory()->create(['email' => 'pm@test.com', 'password' => Hash::make('password'), 'role' => 'project_manager']);
        $this->memberUser = User::factory()->create(['email' => 'member@test.com', 'password' => Hash::make('password'), 'role' => 'member']);
        $this->unauthorizedUser = User::factory()->create(['email' => 'unauth@test.com']);

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

        // Create a comment within the project
        $this->comment = Comment::factory()->create([
            'user_id' => $this->pmUser->id,
            'commentable_id' => $this->project->id,
            'commentable_type' => get_class($this->project),
        ]);
    }

    // --- Create Attachment Tests ---

    public function test_project_manager_can_upload_attachment_to_project(): void
    {
        $file = UploadedFile::fake()->image('document.pdf', 100, 100)->size(500); // 500 KB PDF
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'project',
            'attachable_id' => $this->project->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['file_name' => 'document.pdf']);

        // Assert the file was stored in fake storage
        Storage::disk('local')->assertExists(basename($response->json('attachment.path')));
        $this->assertDatabaseHas('attachments', [
            'file_name' => 'document.pdf',
            'user_id' => $this->pmUser->id,
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
    }

    public function test_project_manager_can_upload_attachment_to_task(): void
    {
        $file = UploadedFile::fake()->image('image.png', 200, 200)->size(800); // 800 KB PNG
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'task',
            'attachable_id' => $this->task->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['file_name' => 'image.png']);
        Storage::disk('local')->assertExists(basename($response->json('attachment.path')));
        $this->assertDatabaseHas('attachments', [
            'file_name' => 'image.png',
            'user_id' => $this->pmUser->id,
            'attachable_id' => $this->task->id,
            'attachable_type' => get_class($this->task),
        ]);
    }

    public function test_project_manager_can_upload_attachment_to_comment(): void
    {
        $file = UploadedFile::fake()->image('notes.txt', 10, 10)->size(50); // 50 KB TXT
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'comment',
            'attachable_id' => $this->comment->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['file_name' => 'notes.txt']);
        Storage::disk('local')->assertExists(basename($response->json('attachment.path')));
        $this->assertDatabaseHas('attachments', [
            'file_name' => 'notes.txt',
            'user_id' => $this->pmUser->id,
            'attachable_id' => $this->comment->id,
            'attachable_type' => get_class($this->comment),
        ]);
    }

    public function test_unauthorized_user_cannot_upload_attachment(): void
    {
        $file = UploadedFile::fake()->image('unauth.jpg', 100, 100);
        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'project',
            'attachable_id' => $this->project->id,
        ]);

        $response->assertStatus(403);
        Storage::disk('local')->assertMissing($file->hashName()); // Assert file was NOT stored
    }

    public function test_cannot_upload_attachment_to_non_existent_resource(): void
    {
        $file = UploadedFile::fake()->image('nonexistent.jpg', 100, 100);
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'project',
            'attachable_id' => 99999, // Non-existent ID
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['attachable_id']);
        Storage::disk('local')->assertMissing($file->hashName());
    }

    public function test_cannot_upload_too_large_file(): void
    {
        // Max size is 10240 KB (10 MB) defined in StoreAttachmentRequest
        $file = UploadedFile::fake()->create('large.pdf', 10241); // 10241 KB
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'project',
            'attachable_id' => $this->project->id,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['file']);
        Storage::disk('local')->assertMissing($file->hashName());
    }

    public function test_cannot_upload_unsupported_file_type(): void
    {
        // Allowed mimes are: jpeg,png,gif,pdf,doc,docx,xlsx,pptx,txt,zip,rar
        $file = UploadedFile::fake()->create('script.exe', 100);
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'project',
            'attachable_id' => $this->project->id,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['file']);
        Storage::disk('local')->assertMissing($file->hashName());
    }

    // --- Get All Attachments Tests ---

    public function test_admin_can_view_all_attachments_for_a_resource(): void
    {
        Attachment::factory()->count(2)->create([
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        Attachment::factory()->count(1)->create([
            'attachable_id' => $this->task->id,
            'attachable_type' => get_class($this->task),
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/attachments?attachable_type=project&attachable_id=' . $this->project->id);
        $response->assertStatus(200)
                 ->assertJsonCount(2, 'attachments');

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/attachments?attachable_type=task&attachable_id=' . $this->task->id);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'attachments');
    }

    public function test_project_member_can_view_attachments_for_their_project_resource(): void
    {
        // Create attachment on project
        Attachment::factory()->create([
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        // Create attachment on task
        Attachment::factory()->create([
            'attachable_id' => $this->task->id,
            'attachable_type' => get_class($this->task),
        ]);

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/attachments?attachable_type=project&attachable_id=' . $this->project->id);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'attachments');

        $response = $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/attachments?attachable_type=task&attachable_id=' . $this->task->id);
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'attachments');
    }

    public function test_unauthorized_user_cannot_view_attachments_for_resource(): void
    {
        Attachment::factory()->create([
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')->getJson('/api/attachments?attachable_type=project&attachable_id=' . $this->project->id);
        $response->assertStatus(403);
    }

    // --- Download Specific Attachment Tests ---

    public function test_admin_can_download_any_specific_attachment(): void
    {
        Storage::disk('local')->put('attachments/admin_test.txt', 'Admin can download this.');
        $attachment = Attachment::factory()->create([
            'path' => 'attachments/admin_test.txt',
            'disk' => 'local',
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')->get('/api/attachments/' . $attachment->id); // Use get() not getJson() for file downloads

        $response->assertStatus(200)
                 ->assertHeader('Content-Disposition', 'attachment; filename="admin_test.txt"');
    }

    public function test_project_manager_can_download_attachment_in_their_project(): void
    {
        Storage::disk('local')->put('attachments/pm_test.txt', 'PM can download this.');
        $attachment = Attachment::factory()->create([
            'path' => 'attachments/pm_test.txt',
            'disk' => 'local',
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);

        $response = $this->actingAs($this->pmUser, 'sanctum')->get('/api/attachments/' . $attachment->id);

        $response->assertStatus(200);
    }

    public function test_unauthorized_user_cannot_download_attachment(): void
    {
        Storage::disk('local')->put('attachments/unauth_test.txt', 'Unauthorized cannot download this.');
        $attachment = Attachment::factory()->create([
            'path' => 'attachments/unauth_test.txt',
            'disk' => 'local',
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);

        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')->get('/api/attachments/' . $attachment->id);
        $response->assertStatus(403);
    }

    // --- Update Attachment Metadata Tests ---

    public function test_project_manager_can_update_attachment_metadata(): void
    {
        $attachment = Attachment::factory()->create([
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        $updatedFileName = 'New_Attachment_Name.pdf';

        $response = $this->actingAs($this->pmUser, 'sanctum')->putJson('/api/attachments/' . $attachment->id, [
            'file_name' => $updatedFileName,
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['file_name' => $updatedFileName]);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id, 'file_name' => $updatedFileName]);
    }

    public function test_unauthorized_user_cannot_update_attachment_metadata(): void
    {
        $attachment = Attachment::factory()->create([
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')->putJson('/api/attachments/' . $attachment->id, [
            'file_name' => 'Unauthorized_Name.pdf',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id, 'file_name' => $attachment->file_name]); // Should not be updated
    }

    // --- Delete Attachment Tests ---

    public function test_project_manager_can_delete_attachment(): void
    {
        Storage::fake('local');
        $filePath = 'attachments/deletable_file.txt';
        Storage::disk('local')->put($filePath, 'Content to be deleted.');
        $attachment = Attachment::factory()->create([
            'path' => $filePath,
            'disk' => 'local',
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        $response = $this->actingAs($this->pmUser, 'sanctum')->deleteJson('/api/attachments/' . $attachment->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($filePath); // Assert physical file deleted by Observer
    }

    public function test_admin_can_delete_any_attachment(): void
    {
        Storage::fake('local');
        $filePath = 'attachments/admin_deletable.txt';
        Storage::disk('local')->put($filePath, 'Content for admin to delete.');
        $attachment = Attachment::factory()->create([
            'path' => $filePath,
            'disk' => 'local',
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson('/api/attachments/' . $attachment->id);

        $response->assertStatus(200);
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_unauthorized_user_cannot_delete_attachment(): void
    {
        Storage::fake('local');
        $filePath = 'attachments/protected_file.txt';
        Storage::disk('local')->put($filePath, 'Protected content.');
        $attachment = Attachment::factory()->create([
            'path' => $filePath,
            'disk' => 'local',
            'attachable_id' => $this->project->id,
            'attachable_type' => get_class($this->project),
        ]);
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')->deleteJson('/api/attachments/' . $attachment->id);

        $response->assertStatus(403);
        Storage::disk('local')->assertExists($filePath); // Assert physical file was NOT deleted
    }

    public function test_upload_rate_limiting(): void
    {
        // Set up a custom rate limiter for uploads in AppServiceProvider for testing purposes
        // This test assumes a rate limit of 10 requests per minute by default for 'uploads'
        // In AppServiceProvider, configure it for example to Limit::perMinute(1) or Limit::perMinute(2) for easier testing

        // Consume the allowed requests
        for ($i = 0; $i < 10; $i++) {
            $file = UploadedFile::fake()->image("test_file_{$i}.jpg", 100, 100);
            $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
                'file' => $file,
                'attachable_type' => 'project',
                'attachable_id' => $this->project->id,
            ]);
            $response->assertStatus(201);
        }

        // The 11th attempt should be rate limited
        $file = UploadedFile::fake()->image("test_file_10.jpg", 100, 100);
        $response = $this->actingAs($this->pmUser, 'sanctum')->postJson('/api/attachments', [
            'file' => $file,
            'attachable_type' => 'project',
            'attachable_id' => $this->project->id,
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $this->assertArrayHasKey('Retry-After', $response->headers->all());
    }
}
