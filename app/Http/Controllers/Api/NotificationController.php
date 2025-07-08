<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->middleware('auth:sanctum');
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of the user's notifications.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $status = $request->input('status');

        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing all notifications.");
            $notifications = $this->notificationService->getAllNotifications($status);
            return response()->json([
                'notifications' => $notifications
            ]);
        }

        $this->authorize('viewAny', Notification::class);

        $notifications = $this->notificationService->getUserNotifications($user, $status);

        return response()->json([
            'notifications' => $notifications
        ]);
    }

    /**
     * Display the specified notification.
     *
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Notification $notification)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing notification ID: {$notification->id}. Bypassing policy check.");
            return response()->json(['notification' => $notification->load('user')], 200);
        }

        $this->authorize('view', $notification);

        return response()->json([
            'notification' => $notification->load('user')
        ]);
    }

    /**
     * Mark a notification as read.
     * (Using PUT/PATCH for update)
     *
     * @param  \App\Http\Requests\Notification\UpdateNotificationRequest  $request
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateNotificationRequest $request, Notification $notification)
    {
        try {
            $updatedNotification = $this->notificationService->markNotificationAsRead($notification);
            return response()->json([
                'message' => 'Notification marked as read',
                'notification' => $updatedNotification
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to mark notification {$notification->id} as read: " . $e->getMessage());
            return response()->json(['message' => 'Failed to mark notification as read.'], 500);
        }
    }

    /**
     * Remove the specified notification from storage.
     *
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Notification $notification)
    {
        $this->authorize('delete', $notification);

        try {
            $this->notificationService->deleteNotification($notification);
            return response()->json([
                'message' => 'Notification deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to delete notification {$notification->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete notification.'], 500);
        }
    }

    /**
     * Mark all user's notifications as read.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();
        try {
            $count = $this->notificationService->markAllUserNotificationsAsRead($user);
            return response()->json([
                'message' => 'All notifications marked as read',
                'unread_count' => 0
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to mark all notifications as read for user {$user->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to mark all notifications as read.'], 500);
        }
    }

    /**
     * Get count of unread notifications for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount(Request $request)
    {
        $user = Auth::user();
        $count = $this->notificationService->getUnreadNotificationsCount($user);
        return response()->json([
            'unread_count' => $count
        ]);
    }
}
