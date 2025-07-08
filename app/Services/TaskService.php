<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use Illuminate\Support\Facades\Auth;

class TaskService
{
    /**
     * Get a listing of tasks based on user access and filters.
     *
     * @param \App\Models\User $user The authenticated user.
     * @param array $filters Optional filters (e.g., 'status' => 'overdue').
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTasks(User $user, array $filters = []): Collection
    {
        // This method is primarily for non-admin users. Admin users bypass this logic.
        Log::info("TaskService: Fetching tasks for user ID: {$user->id} with filters: " . json_encode($filters));

        $accessibleProjectIds = $user->projects->pluck('id')
            ->merge($user->createdProjects->pluck('id'))
            ->merge($user->ownedTeams->pluck('id')->flatMap(function ($teamId) {
                return Project::where('team_id', $teamId)->pluck('id');
            }))->unique();

        $query = Task::whereIn('project_id', $accessibleProjectIds);

        if (isset($filters['status']) && $filters['status'] === 'overdue') {
            $query->overdueTasks();
        }

        return $query->with('project', 'assignee', 'comments')->withCount('comments')->get();
    }

    /**
     * Create a new task.
     *
     * @param array $taskData Data containing task details.
     * @param int $creatorId User ID of the task creator (assigner).
     * @return \App\Models\Task
     * @throws \Exception If assignee is not a project member.
     */
    public function createTask(array $taskData, int $creatorId): Task
    {
        Log::info("TaskService: Creating task '{$taskData['name']}' for creator ID: {$creatorId}");

        $project = Project::find($taskData['project_id']);
        if (!$project) {
            throw new \Exception('Project not found for task creation.');
        }

        if (isset($taskData['assigned_to_user_id'])) {
            $assignee = User::find($taskData['assigned_to_user_id']);
            if (!$assignee || !$project->members->contains($assignee->id)) {
                throw new \Exception('Assigned user must be a member of the project.');
            }
        } else {
            $assignee = null;
        }

        $task = Task::create([
            'project_id' => $taskData['project_id'],
            'name' => $taskData['name'],
            'description' => $taskData['description'] ?? null,
            'assigned_to_user_id' => $taskData['assigned_to_user_id'] ?? null,
            'status' => $taskData['status'] ?? 'open',
            'priority' => $taskData['priority'] ?? 'medium',
            'due_date' => $taskData['due_date'] ?? null,
        ]);

        Log::info("TaskService: New task '{$task->name}' created with ID: {$task->id}");

        // Dispatch TaskAssigned event (moved from controller)
        if ($task->assigned_to_user_id && $assignee) {
            $task->load('project.creator', 'project.team.owner', 'assignee');
            event(new TaskAssigned($task, $assignee, User::find($creatorId)));
            Log::info("TaskService: TaskAssigned event dispatched for task #{$task->id}.");
        }

        return $task->load('project', 'assignee');
    }

    /**
     * Get a specific task by ID.
     *
     * @param \App\Models\Task $task
     * @return \App\Models\Task
     */
    public function getTaskById(Task $task): Task
    {
        return $task->load('project.team', 'assignee', 'comments.user', 'attachments');
    }

    /**
     * Update an existing task.
     *
     * @param \App\Models\Task $task The task model instance.
     * @param array $data Data to update.
     * @param int $updaterId User ID of the updater.
     * @return \App\Models\Task
     * @throws \Exception If new assignee is not a project member.
     */
    public function updateTask(Task $task, array $data, int $updaterId): Task
    {
        $oldStatus = $task->status;
        Log::info("TaskService: Updating task ID: {$task->id}. Old status: {$oldStatus}. New data: " . json_encode($data));

        // Check if assigned_to_user_id is being changed and the new user is a project member
        if (isset($data['assigned_to_user_id']) && $task->assigned_to_user_id !== (int) $data['assigned_to_user_id']) {
            $newAssignee = User::find($data['assigned_to_user_id']);
            if (!$newAssignee || !$task->project->members->contains($newAssignee->id)) {
                throw new \Exception('New assigned user must be a member of the project.');
            }
        }

        $task->update($data);

        

        Log::info("TaskService: Task ID: {$task->id} updated. New status: {$task->status}");

        return $task->load('project', 'assignee');
    }

    /**
     * Delete a task.
     *
     * @param \App\Models\Task $task The task model instance.
     * @return bool
     */
    public function deleteTask(Task $task): bool
    {
        Log::info("TaskService: Deleting task ID: {$task->id}.");
        return $task->delete();
    }
}
