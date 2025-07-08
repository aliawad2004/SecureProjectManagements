<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Task;
use App\Models\User;

class TaskCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $task;
    public $assignee;
    public $completer;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, User $assignee = null, User $completer = null)
    {
        $this->task = $task;
        $this->assignee = $assignee;
        $this->completer = $completer;
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
        $completerName = $this->completer ? $this->completer->name : ($this->assignee ? $this->assignee->name : 'Unknown User');
        return [
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'project_id' => $this->task->project_id,
            'project_name' => $this->task->project->name ?? 'N/A',
            'completed_by' => $completerName,
            'message' => "Task '{$this->task->name}' has been completed by {$completerName}.",
        ];
    }
}
