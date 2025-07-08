<?php

namespace App\Http\Controllers\Api;

use App\Events\CommentCreated;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Mews\Purifier\Facades\Purifier;

use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;

use App\Services\CommentService;

class CommentController extends Controller
{
    protected $commentService;

    public function __construct(CommentService $commentService) {
        $this->middleware('auth:sanctum');
        $this->commentService = $commentService;
    }

    /**
     * Display a listing of comments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing all comments.");
            return response()->json(['comments' => Comment::all()->load('user', 'commentable')], 200);
        }

        if ($request->has('commentable_type') && $request->has('commentable_id')) {
            $commentableType = $request->commentable_type;
            $commentableId = (int) $request->commentable_id;


            $commentableModel = null;
            if ($commentableType === 'project') {
                $commentableModel = Project::find($commentableId);
            } elseif ($commentableType === 'task') {
                $commentableModel = Task::find($commentableId);
            }

            if (!$commentableModel) {
                return response()->json(['message' => 'Commentable resource not found or type invalid.'], 404);
            }

            if ($commentableModel instanceof Project) {
                $this->authorize('view', $commentableModel);
            } elseif ($commentableModel instanceof Task) {
                $this->authorize('view', $commentableModel);
            } else {
                return response()->json(['message' => 'Invalid commentable type.'], 400);
            }

            try {
                $comments = $this->commentService->getCommentsForResource($commentableType, $commentableId);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json(['message' => $e->getMessage()], 404);
            }

            return response()->json([
                'comments' => $comments
            ]);

        } else {
            return response()->json([
                'message' => 'Please specify commentable_type and commentable_id to view comments for a specific resource.'
            ], 400);
        }
    }

    /**
     * Store a newly created comment in storage.
     *
     * @param  \App\Http\Requests\Comment\StoreCommentRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCommentRequest $request)
    {
        $user = Auth::user();

        try {
            $comment = $this->commentService->createComment(
                $request->content,
                $request->commentable_type,
                (int) $request->commentable_id,
                                $user->id
            );

            return response()->json([
                'message' => 'Comment created successfully',
                'comment' => $comment
            ], 201);

        } catch (\Exception $e) {
            Log::error("Comment creation failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified comment.
     *
     * @param  \App\Models\Comment  $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Comment $comment)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing comment ID: {$comment->id}. Bypassing policy check.");
            return response()->json(['comment' => $comment->load('user', 'commentable')], 200);
        }

        $this->authorize('view', $comment);

        return response()->json([
            'comment' => $comment->load('user', 'commentable')
        ]);
    }

    /**
     * Update the specified comment in storage.
     *
     * @param  \App\Http\Requests\Comment\UpdateCommentRequest  $request
     * @param  \App\Models\Comment  $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCommentRequest $request, Comment $comment)
    {
        try {
            $updatedComment = $this->commentService->updateComment(
                $comment,
                $request->content
            );

            return response()->json([
                'message' => 'Comment updated successfully',
                'comment' => $updatedComment
            ]);

        } catch (\Exception $e) {
            Log::error("Comment update failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified comment from storage.
     *
     * @param  \App\Models\Comment  $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        try {
            $this->commentService->deleteComment($comment);

            return response()->json([
                'message' => 'Comment deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error(message: "Comment deletion failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}