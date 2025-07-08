<?php

use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/users', [AuthController::class, 'createUser'])->middleware('can:create-user'); // <--- New Route


    // Team Routes
    Route::apiResource('teams', TeamController::class);
    Route::post('teams/{team}/members', [TeamController::class, 'addMember']);
    Route::put('teams/{team}/members/{member}', [TeamController::class, 'updateMemberRole']);
    Route::delete('teams/{team}/members/{member}', [TeamController::class, 'removeMember']);

    // Project Routes
    Route::apiResource('projects', ProjectController::class);
      Route::post('projects/{project}/members', [ProjectController::class, 'addMember']);
    Route::put('projects/{project}/members/{member}', [ProjectController::class, 'updateMemberRole']);
    Route::delete('projects/{project}/members/{member}', [ProjectController::class, 'removeMember']);

    // Task Routes
    Route::apiResource('tasks', TaskController::class);

    // Comment Routes
    Route::apiResource('comments', CommentController::class);

    // Attachment Routes
    Route::apiResource('attachments', AttachmentController::class);
    Route::apiResource('attachments', AttachmentController::class)->except(['store']);

    Route::post('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
});
