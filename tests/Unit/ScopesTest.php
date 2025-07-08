<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Project;
use App\Models\Notification;
use App\Models\User; 
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_tasks_scope_returns_only_overdue_and_uncompleted_tasks(): void
    {
        // Create a completed task (should not be overdue)
        Task::factory()->create([
            'due_date' => Carbon::yesterday(),
            'status' => 'completed',
        ]);

        // Create an overdue task
        $overdueTask = Task::factory()->create([
            'due_date' => Carbon::yesterday(),
            'status' => 'open',
        ]);

        // Create a pending task (not overdue)
        Task::factory()->create([
            'due_date' => Carbon::tomorrow(),
            'status' => 'open',
        ]);

        $overdueTasks = Task::overdueTasks()->get();

        $this->assertCount(1, $overdueTasks);
        $this->assertEquals($overdueTask->id, $overdueTasks->first()->id);
    }

    public function test_active_projects_scope_returns_only_non_completed_projects(): void
    {
        // Create a completed project
        Project::factory()->create(['status' => 'completed']);

        // Create an active project
        $activeProject = Project::factory()->create(['status' => 'in_progress']);

        // Create another active project
        $pendingProject = Project::factory()->create(['status' => 'pending']);

        $activeProjects = Project::activeProjects()->get();

        $this->assertCount(2, $activeProjects);
        $this->assertContains($activeProject->id, $activeProjects->pluck('id'));
        $this->assertContains($pendingProject->id, $activeProjects->pluck('id'));
    }

    public function test_unread_notifications_scope_returns_only_unread_notifications(): void
    {
        $user = User::factory()->create();

        // Create a read notification
        Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => Carbon::now(),
        ]);

        // Create an unread notification
        $unreadNotification = Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        // Create another unread notification for a different user (should not be counted)
        Notification::factory()->create([
            'read_at' => null,
        ]);

        $unreadNotifications = Notification::unreadNotifications()->where('user_id', $user->id)->get(); // Filter by user for accuracy

        $this->assertCount(1, $unreadNotifications);
        $this->assertEquals($unreadNotification->id, $unreadNotifications->first()->id);
    }
}
