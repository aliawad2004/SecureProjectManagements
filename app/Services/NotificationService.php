<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User; // For user-specific operations
use Illuminate\Database\Eloquent\Collection; // For type hinting
use Illuminate\Support\Facades\Log; // For logging within the service

class NotificationService
{
    /**
     * Get a list of notifications for a specific user.
     *
     * @param \App\Models\User $user The user whose notifications to retrieve.
     * @param string|null $status Filter by status (e.g., 'unread').
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserNotifications(User $user, ?string $status = null): Collection
    {
        $query = $user->notifications();

        if ($status === 'unread') {
            $query->unreadNotifications();
        }

        return $query->with('user')->get();
    }

    /**
     * Get all notifications in the system (typically for admin).
     *
     * @param string|null $status Filter by status (e.g., 'unread').
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllNotifications(?string $status = null): Collection
    {
        $query = Notification::query();

        if ($status === 'unread') {
            $query->unreadNotifications();
        }

        return $query->with('user')->get();
    }

    /**
     * Mark a specific notification as read.
     *
     * @param \App\Models\Notification $notification The notification model instance.
     * @return \App\Models\Notification
     */
    public function markNotificationAsRead(Notification $notification): Notification
    {
        if (!$notification->read_at) {
            $notification->markAsRead();
            Log::info("NotificationService: Notification ID {$notification->id} marked as read.");
        }
        return $notification->load('user');
    }

    /**
     * Delete a specific notification.
     *
     * @param \App\Models\Notification $notification The notification model instance.
     * @return bool
     */
    public function deleteNotification(Notification $notification): bool
    {
        return $notification->delete();
    }

    /**
     * Mark all unread notifications for a user as read.
     *
     * @param \App\Models\User $user The user whose notifications to mark as read.
     * @return int Count of notifications marked as read.
     */
    public function markAllUserNotificationsAsRead(User $user): int
    {
        $count = $user->unreadNotifications->count();
        $user->unreadNotifications->markAsRead();
        Log::info("NotificationService: All unread notifications marked as read for user ID: {$user->id}. Count: {$count}");
        return $count;
    }

    /**
     * Get the count of unread notifications for a user.
     *
     * @param \App\Models\User $user The user whose unread notifications count to retrieve.
     * @return int
     */
    public function getUnreadNotificationsCount(User $user): int
    {
        return $user->unreadNotifications->count();
    }
}
