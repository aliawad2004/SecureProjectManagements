<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{

    public function viewAny(User $user): bool
    {

        return true;
    }


    public function view(User $user, Project $project): bool
    {
        return $user->id === $project->created_by_user_id ||
            $user->ownsTeam($project->team) ||
            $user->belongsToTeam($project->team) ||
            $user->belongsToProject($project);
    }



    public function create(User $user): bool
    {
        return $user->hasRole('admin') ||
            $user->hasRole('project_manager') ||
            $user->ownedTeams()->exists();
    }


    public function update(User $user, Project $project): bool
    {

        return $user->hasRole('project_manager') ||
            $user->ownsTeam($project->team) ||
            $user->hasProjectRole($project, 'project_manager') ||
            $user->id === $project->created_by_user_id;
    }


    public function delete(User $user, Project $project): bool
    {

        return $user->hasRole('project_manager') ||
            $user->ownsTeam($project->team) ||
            $user->hasProjectRole($project, 'project_manager') ||
            $user->id === $project->created_by_user_id;
    }

    /**
     * Determine whether the user can restore the model (if using soft deletes).
     */
    public function restore(User $user, Project $project): bool
    {

        return $user->hasRole('admin') || $user->ownsTeam($project->team);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        // صلاحية للمديرين العامين أو مالكي الفرق.
        return $user->hasRole('admin') || $user->ownsTeam($project->team);
    }


    public function addMember(User $user, Project $project): bool
    {

        return $user->hasRole('project_manager') ||
            $user->ownsTeam($project->team) ||
            $user->hasProjectRole($project, 'project_manager');
    }

    
    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasRole('project_manager') ||
            $user->ownsTeam($project->team) ||
            $user->hasProjectRole($project, 'project_manager');
    }
}
