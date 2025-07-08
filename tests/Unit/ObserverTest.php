<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Attachment;
use App\Models\User;
use App\Models\Project;
use App\Events\TaskCompleted; 
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event; 
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\Log; 

class ObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create some dummy data for relationships needed by observers
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['created_by_user_id' => $this->user->id]);
        $this->taskAssignee = User::factory()->create();
    }

    public function test_task_observer_dispatches_task_completed_event_on_status_change(): void
    {
        Event::fake(); // Fake events to ensure they are dispatched

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assigned_to_user_id' => $this->taskAssignee->id,
            'status' => 'in_progress',
        ]);

        $task->status = 'completed';
        $task->save();

        Event::assertDispatched(TaskCompleted::class, function ($event) use ($task) {
            return $event->task->id === $task->id && $event->newStatus === 'completed'; // Ensure event is dispatched with correct data
        });
    }

    public function test_task_observer_does_not_dispatch_event_if_status_is_not_completed(): void
    {
        Event::fake();

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'in_progress',
        ]);

        $task->status = 'open'; // Change to a non-completed status
        $task->save();

        Event::assertNotDispatched(TaskCompleted::class);
    }

    public function test_attachment_observer_deletes_physical_file_on_model_deletion(): void
    {
        Storage::fake('local'); // Fake the storage disk

        // Create a dummy file in the faked storage
        $filePath = 'attachments/test_file.txt';
        Storage::disk('local')->put($filePath, 'Test content for deletion.');
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        // Create an attachment model instance
        $attachment = Attachment::factory()->create([
            'path' => $filePath,
            'disk' => 'local',
            'file_name' => 'test_file.txt',
            'file_size' => 100,
            'mime_type' => 'text/plain',
            'attachable_id' => $this->project->id, // Attach to a project
            'attachable_type' => get_class($this->project),
            'user_id' => $this->user->id,
        ]);

        $attachment->delete(); // This should trigger the observer

        $this->assertFalse(Storage::disk('local')->exists($filePath)); // Assert file is deleted
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]); // Assert record is deleted from DB
    }
}
