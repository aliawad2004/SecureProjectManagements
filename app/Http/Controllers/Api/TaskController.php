<?php

namespace App\Http\Controllers\Api;

use App\Events\TaskAssigned;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Services\TaskService;

class TaskController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->middleware('auth:sanctum');
        $this->taskService = $taskService;
    }

    /**
     * Display a listing of tasks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $this->authorize('viewAny', Task::class);

        if ($user->hasRole('admin')) {
            return response()->json([
                'tasks' => Task::with('project', 'assignee', 'comments')->withCount('comments')->get()
            ]);
        }

        $filters = $request->only('status');
        $tasks = $this->taskService->getTasks($user, $filters);

        return response()->json([
            'tasks' => $tasks
        ]);
    }

    /**
     * Store a newly created task in storage.
     *
     * @param  \App\Http\Requests\Task\StoreTaskRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreTaskRequest $request)
    {
        $user = Auth::user();

        try {
            $task = $this->taskService->createTask($request->validated(), $user->id);

            if ($task->wasRecentlyCreated) {
                Log::info('TaskController: New task created: ' . $task->name);
            }

            return response()->json([
                'message' => 'Task created successfully',
                'task' => $task
            ], 201);

        } catch (\Exception $e) {
            Log::error("TaskController: Task creation failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to create task: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified task.
     *
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Task $task)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing task ID: {$task->id}. Bypassing policy check.");
            return response()->json(['task' => $task->load('project.team', 'assignee', 'comments.user', 'attachments')], 200);
        }

        $this->authorize('view', $task);

        $task = $this->taskService->getTaskById($task);

        return response()->json([
            'task' => $task
        ]);
    }

    /**
     * Update the specified task in storage.
     *
     * @param  \App\Http\Requests\Task\UpdateTaskRequest  $request
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $updater = Auth::user();

        try {
            $updatedTask = $this->taskService->updateTask($task, $request->validated(), $updater->id);

            if ($task->isDirty('status')) {
                Log::info("TaskController: Task status changed to: " . $task->status);
            }

            return response()->json([
                'message' => 'Task updated successfully',
                'task' => $updatedTask
            ]);

        } catch (\Exception $e) {
            Log::error("TaskController: Task update failed: " . $e->getMessage());
            if ($e->getMessage() === 'New assigned user must be a member of the project.') {
                return response()->json(['message' => $e->getMessage()], 403); // Or 422
            }
            return response()->json(['message' => 'Failed to update task: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified task from storage.
     *
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        try {
            $this->taskService->deleteTask($task);

            return response()->json([
                'message' => 'Task deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error("TaskController: Task deletion failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete task: ' . $e->getMessage()], 500);
        }
    }
}
