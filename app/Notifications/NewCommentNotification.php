<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Comment;
use App\Models\User;

class NewCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $comment;
    public $commentable;
    public $commenter;

    /**
     * Create a new notification instance.
     */
    public function __construct(Comment $comment, $commentable, User $commenter)
    {
        $this->comment = $comment;
        $this->commentable = $commentable;
        $this->commenter = $commenter;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'commenter_id' => $this->commenter->id,
            'commenter_name' => $this->commenter->name,
            'commentable_type' => $this->comment->commentable_type,
            'commentable_id' => $this->comment->commentable_id,
            'commentable_name' => $this->commentable->name ?? 'Unknown',
            'message' => "New comment from {$this->commenter->name} on " .
                ($this->commentable instanceof \App\Models\Project ? "project: " : "task: ") .
                ($this->commentable->name ?? 'N/A'),
        ];
    }
}
