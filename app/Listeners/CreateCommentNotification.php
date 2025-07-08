<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewCommentEmail;
use App\Models\Project;
use App\Models\Task;


class CreateCommentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(CommentCreated $event): void
    {
        Log::info("CreateCommentNotification Listener: handle method started for comment ID: {$event->comment->id}");

        $comment = $event->comment;
        $commenter = $comment->user;

        if ($comment->commentable_type === get_class(new Project())) {
            $comment->load('commentable.creator', 'commentable.members');
        } elseif ($comment->commentable_type === get_class(new Task())) {
            $comment->load('commentable.creator', 'commentable.assignee', 'commentable.project.members', 'commentable.project.creator', 'commentable.project.team.owner');
        }
        $commentable = $comment->commentable;

        Log::info("CreateCommentNotification Listener: Commentable type: " . get_class($commentable) . ", ID: " . $commentable->id . ". Commenter ID: " . $commenter->id . " Email: " . $commenter->email);

        $notifiableUsers = collect();

        if ($commentable->creator && $commentable->creator->id !== $commenter->id) {
            $notifiableUsers->push($commentable->creator);
            Log::info("CreateCommentNotification Listener: Added commentable creator #{$commentable->creator->id} ({$commentable->creator->email}) to notifiable list.");
        }

        if ($commentable instanceof Task && $commentable->assignee && $commentable->assignee->id !== $commenter->id) {
            $notifiableUsers->push($commentable->assignee);
            Log::info("CreateCommentNotification Listener: Added task assignee #{$commentable->assignee->id} ({$commentable->assignee->email}) to notifiable list.");
        }

        if ($commentable instanceof Project || $commentable instanceof Task) {
            $project = ($commentable instanceof Task) ? $commentable->project : $commentable;
            if ($project && $project->members) {
                Log::info("CreateCommentNotification Listener: Processing project members for project ID: {$project->id}. Total members: {$project->members->count()}");
                $project->members->each(function ($member) use ($notifiableUsers, $commenter) {
                    if ($member->id !== $commenter->id && !$notifiableUsers->contains('id', $member->id)) {
                        $notifiableUsers->push($member);
                        Log::info("CreateCommentNotification Listener: Added project member #{$member->id} ({$member->email}) to notifiable list.");
                    }
                });
            } else {
                Log::warning("CreateCommentNotification Listener: Project or its members not loaded for project ID: " . ($project ? $project->id : 'N/A') . ".");
            }
        }

        $notifiableUsers = $notifiableUsers->unique('id')->filter(function ($user) use ($commenter) {
            return $user && $user->id !== $commenter->id && $user->email;
        });

        Log::info("CreateCommentNotification Listener: Final notifiable users count: " . $notifiableUsers->count());
        Log::info("CreateCommentNotification Listener: Notifiable users IDs and Emails: " . $notifiableUsers->map(fn($u) => "{$u->id}:{$u->email}")->implode(', '));

        if ($notifiableUsers->isEmpty()) {
            Log::warning("CreateCommentNotification Listener: No users found to notify for comment ID: {$comment->id}.");
        }

        foreach ($notifiableUsers as $user) {

            $user->notify(new NewCommentNotification($comment, $commentable, $commenter));
            Log::info("New comment database notification created for user #{$user->id} ({$user->email}).");

           
            Mail::to($user->email)->send(new NewCommentEmail($comment, $commentable, $commenter));
            Log::info("New comment email sent (via Mailable's ShouldQueue) for: {$user->email}");
        }
        Log::info("CreateCommentNotification Listener: handle method finished for comment ID: {$comment->id}");
}
}