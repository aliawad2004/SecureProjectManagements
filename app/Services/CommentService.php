<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User; // For user_id
use Illuminate\Support\Facades\Log;
use Mews\Purifier\Facades\Purifier;
use App\Events\CommentCreated; 

class CommentService
{
    /**
     * Get a list of comments for a specific commentable resource.
     *
     * @param string $commentableType
     * @param int $commentableId
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If commentable resource is not found.
     */
    public function getCommentsForResource(string $commentableType, int $commentableId)
    {
        $commentableModel = $this->findCommentableModel($commentableType, $commentableId);

        if (!$commentableModel) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Commentable resource not found.');
        }

        return $commentableModel->comments()->with('user', 'commentable')->get();
    }

    /**
     * Store a new comment for a specific resource.
     *
     * @param string $content The content of the comment.
     * @param string $commentableType The type of the resource (project or task).
     * @param int $commentableId The ID of the resource.
     * @param int $userId The ID of the user creating the comment.
     * @return \App\Models\Comment
     * @throws \Exception If resource not found.
     */
    public function createComment(string $content, string $commentableType, int $commentableId, int $userId): Comment
    {
        $commentableModel = $this->findCommentableModel($commentableType, $commentableId);

        if (!$commentableModel) {
            throw new \Exception('Commentable resource not found.');
        }

        $comment = $commentableModel->comments()->create([
            'user_id' => $userId,
            'content' => Purifier::clean($content),
        ]);

        // Dispatch the CommentCreated event
        event(new CommentCreated($comment));
        Log::info("CommentService: CommentCreated event dispatched for comment ID: {$comment->id}.");

        return $comment->load('user', 'commentable');
    }

    /**
     * Update the content of an existing comment.
     *
     * @param \App\Models\Comment $comment The comment model instance.
     * @param string $newContent The new content for the comment.
     * @return \App\Models\Comment
     */
    public function updateComment(Comment $comment, string $newContent): Comment
    {
        $comment->update([
            'content' => Purifier::clean($newContent),
        ]);
        return $comment->load('user', 'commentable');
    }

    /**
     * Delete a comment.
     *
     * @param \App\Models\Comment $comment The comment model instance.
     * @return bool
     */
    public function deleteComment(Comment $comment): bool
    {
        return $comment->delete();
    }

    /**
     * Helper to find the commentable model.
     *
     * @param string $type
     * @param int $id
     * @return \App\Models\Project|\App\Models\Task|null
     */
    private function findCommentableModel(string $type, int $id)
    {
        switch ($type) {
            case 'project':
                return Project::find($id);
            case 'task':
                return Task::find($id);
            default:
                return null;
        }
    }
}