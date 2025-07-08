<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Task;
use App\Models\User;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $task;
    public $assigner;

    /**
     * Create a new notification instance.
     */
    public function __construct(Task $task, User $assigner = null)
    {
        $this->task = $task;
        $this->assigner = $assigner;
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
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'project_id' => $this->task->project_id,
            'project_name' => $this->task->project->name ?? 'N/A',
            'assigned_by' => $this->assigner ? $this->assigner->name : 'System',
            'message' => "You have been assigned a new task: " . $this->task->name,
        ];
    }
}
