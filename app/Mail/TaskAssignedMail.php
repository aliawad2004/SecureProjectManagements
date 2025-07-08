<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $task;
    public $assignee;

    /**
     * Create a new message instance.
     */
    public function __construct($task, $assignee)
    {
        $this->task = $task;
        $this->assignee = $assignee;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Task Assigned: ' . $this->task->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tasks.assigned', 
            with: [
                'task' => $this->task,
                'assignee' => $this->assignee,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
