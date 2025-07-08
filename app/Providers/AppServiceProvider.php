<?php

namespace App\Providers;

use App\Events\CommentCreated;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Listeners\CreateCommentNotification;
use App\Listeners\CreateTaskAssignedNotification;
use App\Listeners\NotifyTaskCompleted;
use App\Listeners\SendTaskAssignedEmail;
use App\Models\Attachment;
use App\Models\Task;
use App\Observers\AttachmentObserver;
use App\Observers\TaskObserver;
use Event;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Console\Commands\UpdateDailyOverdueTasks;
use Pest\Laravel\PestServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Events\ProjectStatusUpdated;
use App\Listeners\NotifyProjectStatusUpdate;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->isLocal()) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
            $this->app->register(PestServiceProvider::class);

        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        $this->configureRateLimiting();

        $this->app->booted(function (Application $app) {

            $schedule = $app->make(Schedule::class);
            $schedule->command('app:update-overdue-tasks')->daily();
        });

        Gate::before(function (User $user, string $ability) {
            Log::info('Gate::before triggered: User=' . $user->email . ', Role=' . $user->role . ', Ability=' . $ability);
            if ($user->hasRole('admin')) {
                Log::info('Gate::before: Admin detected, granting access for ability: ' . $ability);
                return true;
            }
            Log::info('Gate::before: Not admin, allowing default authorization for ability: ' . $ability);
        });




        Event::listen(
            TaskAssigned::class,
            [SendTaskAssignedEmail::class, 'handle']
        );

        Event::listen(
            TaskAssigned::class,
            [CreateTaskAssignedNotification::class, 'handle']
        );

        Event::listen(
            CommentCreated::class,
            [CreateCommentNotification::class, 'handle']
        );



        Task::observe(TaskObserver::class);

        Event::listen(
            TaskCompleted::class,
            [NotifyTaskCompleted::class, 'handle']
        );
        Attachment::observe(AttachmentObserver::class);

    }


    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email') ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in ' . $headers['Retry-After'] . ' seconds.'
                    ], 429, $headers);
                });
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(2)->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many registration attempts from this IP. Please try again in ' . $headers['Retry-After'] . ' seconds.'
                    ], 429, $headers);
                });
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many file upload attempts. Please try again in ' . $headers['Retry-After'] . ' seconds.'
                    ], 429, $headers);
                });
        });
    }
}
