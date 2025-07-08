<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Requests\Attachment\UpdateAttachmentRequest;
use App\Services\AttachmentService;


class AttachmentController extends Controller
{
    protected $attachmentService;

    public function __construct(AttachmentService $attachmentService)
    {
        $this->middleware('auth:sanctum');
        $this->attachmentService = $attachmentService;
    }

    /**
     * Display a listing of attachments for a specific resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing all attachments.");
            return response()->json(['attachments' => Attachment::all()->load('user', 'attachable')], 200);
        }

        if ($request->has('attachable_type') && $request->has('attachable_id')) {
            $attachableType = $request->attachable_type;
            $attachableId = $request->attachable_id;

            // <--- Authorization check before calling service
            $attachableModel = null;
            if ($attachableType === 'project') {
                $attachableModel = Project::find($attachableId);
            } elseif ($attachableType === 'task') {
                $attachableModel = Task::find($attachableId);
            } elseif ($attachableType === 'comment') {
                $attachableModel = Comment::find($attachableId);
            }

            if (!$attachableModel) {
                return response()->json(['message' => 'Attachable resource not found or type invalid.'], 404);
            }

            if ($attachableModel instanceof Project) {
                $this->authorize('view', $attachableModel);
            } elseif ($attachableModel instanceof Task) {
                $this->authorize('view', $attachableModel);
            } elseif ($attachableModel instanceof Comment) {
                $this->authorize('view', $attachableModel);
            } else {
                return response()->json(['message' => 'Invalid attachable type.'], 400);
            }

            $attachments = $this->attachmentService->getAttachmentsForResource($attachableType, $attachableId);

            return response()->json([
                'attachments' => $attachments
            ]);

        } else {
            return response()->json([
                'message' => 'Please specify attachable_type and attachable_id to view attachments for a specific resource.'
            ], 400);
        }
    }

    /**
     * Store a newly created attachment in storage.
     *
     * @param  \App\Http\Requests\Attachment\StoreAttachmentRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreAttachmentRequest $request)
    {
        $user = Auth::user();

        try {
            $attachment = $this->attachmentService->createAttachment(
                $request->file('file'),
                $request->attachable_type,
                $request->attachable_id,
                $user->id
            );

            return response()->json([
                'message' => 'Attachment uploaded successfully',
                'attachment' => $attachment
            ], 201);

        } catch (\Exception $e) {
            Log::error("Attachment upload failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified attachment.
     *
     * @param  \App\Models\Attachment  $attachment
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function show(Attachment $attachment)
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            Log::info("Admin user ({$user->email}) is viewing attachment ID: {$attachment->id}. Bypassing policy check.");
            return Storage::disk($attachment->disk)->download($attachment->path, $attachment->file_name);
        }

        $this->authorize('view', $attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->file_name);
    }

    /**
     * Update the specified attachment in storage.
     *
     * @param  \App\Http\Requests\Attachment\UpdateAttachmentRequest  $request
     * @param  \App\Models\Attachment  $attachment
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateAttachmentRequest $request, Attachment $attachment)
    {
        try {

            $updatedAttachment = $this->attachmentService->updateAttachment(
                $attachment,
                $request->only('file_name')
            );


            return response()->json([
                'message' => 'Attachment metadata updated successfully',
                'attachment' => $updatedAttachment
            ]);

        } catch (\Exception $e) {
            Log::error("Attachment update failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified attachment from storage.
     *
     * @param  \App\Models\Attachment  $attachment
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Attachment $attachment)
    {
        $this->authorize('delete', $attachment);

        try {

            $this->attachmentService->deleteAttachment($attachment);


            return response()->json([
                'message' => 'Attachment deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error("Attachment deletion failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
