<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Models\Attachment;
use App\Policies\AttachmentPolicy;
use App\Models\Notification;
use App\Policies\NotificationPolicy;

use App\Policies\CommentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Team::class => TeamPolicy::class,
        Project::class => ProjectPolicy::class,
        Task::class => TaskPolicy::class,
        Comment::class => CommentPolicy::class,
        Attachment::class => AttachmentPolicy::class,
                Notification::class => NotificationPolicy::class,


    ];

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {

            if ($user->hasRole('admin')) { 

                return true;
            }

        });
    }
}
