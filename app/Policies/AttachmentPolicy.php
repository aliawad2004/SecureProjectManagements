<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any attachments.
     */
    public function viewAny(User $user): bool
    {

        return true;
    }


    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if (!$attachable) {
            return false;
        }


        if ($attachable instanceof Project) {
            return $user->can('view', $attachable);
        } elseif ($attachable instanceof Task) {
            return $user->can('view', $attachable);
        } elseif ($attachable instanceof Comment) {
            return $user->can('view', $attachable);
        }

        return false;
    }


    public function create(User $user): bool
    {

        return true;
    }

    /**
     * Determine whether the user can create an attachment on a specific attachable (Project, Task, or Comment).
     */
    public function createOnAttachable(User $user, $attachable): bool
    {

        if ($attachable instanceof Project) {
            return $user->can('update', $attachable); // ProjectPolicy@update
        } elseif ($attachable instanceof Task) {
            return $user->can('update', $attachable); // TaskPolicy@update
        } elseif ($attachable instanceof Comment) {
            return $user->can('update', $attachable); // CommentPolicy@update
        }

        return false;
    }



    public function update(User $user, Attachment $attachment): bool
    {
        if ($user->id === $attachment->user_id) {
            return true;
        }

        $attachable = $attachment->attachable;
        if ($attachable instanceof Project) {
            return $user->can('update', $attachable);
        } elseif ($attachable instanceof Task) {
            return $user->can('update', $attachable);
        } elseif ($attachable instanceof Comment) {
            return $user->can('update', $attachable);
        }
        return false;
    }


    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->id === $attachment->user_id) {
            return true;
        }

        $attachable = $attachment->attachable;
        if ($attachable instanceof Project) {
            return $user->can('update', $attachable);
        } elseif ($attachable instanceof Task) {
            return $user->can('update', $attachable);
        } elseif ($attachable instanceof Comment) {
            return $user->can('update', $attachable);
        }
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $user->hasRole('admin');
    }
}