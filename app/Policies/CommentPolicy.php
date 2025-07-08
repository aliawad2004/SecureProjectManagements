<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    /**
     * Determine whether the user can view any comments.
     */
    public function viewAny(User $user): bool
    {

        return true;
    }


    public function view(User $user, Comment $comment): bool
    {
        $commentable = $comment->commentable;

        if ($commentable instanceof Project) {
            return $user->belongsToProject($commentable) ||
                $user->ownsTeam($commentable->team) ||
                $user->id === $commentable->created_by_user_id;
        } elseif ($commentable instanceof Task) {
            return $user->belongsToProject($commentable->project) ||
                $user->ownsTeam($commentable->project->team) ||
                $user->id === $commentable->project->created_by_user_id ||
                $user->id === $commentable->assigned_to_user_id;
        }

        return false; 
    }


    public function create(User $user): bool
    {

        return true;
    }

    /**
     * Determine whether the user can create a comment on a specific commentable (Project or Task).
     */
    public function createOnCommentable(User $user, $commentable): bool
    {
        if ($commentable instanceof Project) {
            return $user->belongsToProject($commentable) ||
                $user->ownsTeam($commentable->team) ||
                $user->hasProjectRole($commentable, 'project_manager') ||
                $user->id === $commentable->created_by_user_id;
        } elseif ($commentable instanceof Task) {
            return $user->belongsToProject($commentable->project) ||
                $user->ownsTeam($commentable->project->team) ||
                $user->hasProjectRole($commentable->project, 'project_manager') ||
                $user->id === $commentable->project->created_by_user_id ||
                $user->id === $commentable->assigned_to_user_id;
        }
        return false;
    }



    public function update(User $user, Comment $comment): bool
    {
        if ($user->id === $comment->user_id) {
            return true;
        }

        $commentable = $comment->commentable;

        if ($commentable instanceof Project) {
            return $user->hasRole('project_manager') ||
                $user->ownsTeam($commentable->team) ||
                $user->hasProjectRole($commentable, 'project_manager') ||
                $user->id === $commentable->created_by_user_id;
        } elseif ($commentable instanceof Task) {
            return $user->hasRole('project_manager') ||
                $user->ownsTeam($commentable->project->team) ||
                $user->hasProjectRole($commentable->project, 'project_manager') ||
                $user->id === $commentable->project->created_by_user_id;
        }
        return false;
    }


    public function delete(User $user, Comment $comment): bool
    {
        if ($user->id === $comment->user_id) {
            return true;
        }

        $commentable = $comment->commentable;

        if ($commentable instanceof Project) {
            return $user->hasRole('project_manager') ||
                $user->ownsTeam($commentable->team) ||
                $user->hasProjectRole($commentable, 'project_manager') ||
                $user->id === $commentable->created_by_user_id;
        } elseif ($commentable instanceof Task) {
            return $user->hasRole('project_manager') ||
                $user->ownsTeam($commentable->project->team) ||
                $user->hasProjectRole($commentable->project, 'project_manager') ||
                $user->id === $commentable->project->created_by_user_id;
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Comment $comment): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Comment $comment): bool
    {
        return $user->hasRole('admin');
    }
}
