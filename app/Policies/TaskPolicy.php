<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Models\Project; // تأكد من استيراد موديل Project
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }


    public function view(User $user, Task $task): bool
    {
        return $user->belongsToProject($task->project) ||
            $user->ownsTeam($task->project->team) ||
            $user->id === $task->project->created_by_user_id ||
            $user->id === $task->assigned_to_user_id; 
    }

        public function create(User $user, Project $project): bool
    {
        return $user->hasRole('admin') ||
            $user->hasRole('project_manager') ||
            $user->ownsTeam($project->team) ||
            $user->hasProjectRole($project, 'project_manager') ||
            $user->belongsToProject($project);
    }


    public function update(User $user, Task $task): bool
    {
        return $user->hasRole('project_manager') ||
            $user->ownsTeam($task->project->team) ||
            $user->hasProjectRole($task->project, 'project_manager') ||
            $user->belongsToProject($task->project) ||
            $user->id === $task->assigned_to_user_id;
    }


    public function delete(User $user, Task $task): bool
    {
        return $user->hasRole('project_manager') ||
            $user->ownsTeam($task->project->team) ||
            $user->hasProjectRole($task->project, 'project_manager') ||
            $user->id === $task->project->created_by_user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Task $task): bool
    {
        return $user->hasRole('admin') || $user->ownsTeam($task->project->team);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $user->hasRole('admin') || $user->ownsTeam($task->project->team);
    }
}