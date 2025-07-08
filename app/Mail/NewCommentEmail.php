<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Comment;
use App\Models\User;

class NewCommentEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $comment;
    public $commentable;
    public $commenter;

    /**
     * Create a new message instance.
     */
    public function __construct(Comment $comment, $commentable, User $commenter)
    {
        $this->comment = $comment;
        $this->commentable = $commentable;
        $this->commenter = $commenter;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = "New Comment on " .
            ($this->commentable instanceof \App\Models\Project ? "Project: " : "Task: ") .
            ($this->commentable->name ?? 'N/A');

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.comments.new_comment', 
            with: [
                'comment' => $this->comment,
                'commentable' => $this->commentable,
                'commenter' => $this->commenter,
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