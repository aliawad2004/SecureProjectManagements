<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotificationPolicy
{
    /**
     * Determine whether the user can view any notifications.
     */
    public function viewAny(User $user): bool
    {

        return true;
    }


    public function view(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }


    public function create(User $user): bool
    {
        return false;
    }


    public function update(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }


    public function delete(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }


    public function restore(User $user, Notification $notification): bool
    {
        return $user->hasRole('admin');
    }

    
    public function forceDelete(User $user, Notification $notification): bool
    {
        return $user->hasRole('admin');
    }
}
