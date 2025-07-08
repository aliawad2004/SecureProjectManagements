<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AttachmentService
{
    /**
     * Get a list of attachments for a specific attachable resource.
     *
     * @param string $attachableType
     * @param int $attachableId
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If attachable resource is not found.
     */
    public function getAttachmentsForResource(string $attachableType, int $attachableId)
    {
        $attachableModel = $this->findAttachableModel($attachableType, $attachableId);

        if (!$attachableModel) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Attachable resource not found.');
        }

        return $attachableModel->attachments()->with('user', 'attachable')->get();
    }

    /**
     * Store a new attachment for a specific resource.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $attachableType
     * @param int $attachableId
     * @param int $userId The ID of the user uploading the file
     * @return \App\Models\Attachment
     * @throws \Exception If file upload fails or resource not found.
     */
    public function createAttachment(UploadedFile $file, string $attachableType, int $attachableId, int $userId): Attachment
    {
        $attachableModel = $this->findAttachableModel($attachableType, $attachableId);

        if (!$attachableModel) {
            throw new \Exception('Attachable resource not found.');
        }

        $originalFileName = $file->getClientOriginalName();
        $fileExtension = $file->getClientOriginalExtension();
        $fileMimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $uniqueFileName = Str::uuid() . '.' . $fileExtension;
        $disk = 'local'; 
        $filePath = $file->storeAs('attachments', $uniqueFileName, $disk);

        if (!$filePath) {
            throw new \Exception('File upload failed.');
        }

        $attachment = $attachableModel->attachments()->create([
            'user_id' => $userId,
            'path' => $filePath,
            'disk' => $disk,
            'file_name' => $originalFileName,
            'file_size' => $fileSize,
            'mime_type' => $fileMimeType,
        ]);

        return $attachment->load('user', 'attachable');
    }

    /**
     * Update the metadata of an existing attachment.
     *
     * @param \App\Models\Attachment $attachment
     * @param array $data
     * @return \App\Models\Attachment
     */
    public function updateAttachment(Attachment $attachment, array $data): Attachment
    {
        $attachment->update($data);
        return $attachment->load('user', 'attachable');
    }

    /**
     * Delete an attachment and its physical file.
     *
     * @param \App\Models\Attachment $attachment
     * @return bool
     */
    public function deleteAttachment(Attachment $attachment): bool
    {
        // The actual file deletion is handled by the AttachmentObserver
        return $attachment->delete();
    }

    /**
     * Find the attachable model based on type and ID.
     *
     * @param string $type
     * @param int $id
     * @return \App\Models\Project|\App\Models\Task|\App\Models\Comment|null
     */
    private function findAttachableModel(string $type, int $id)
    {
        switch ($type) {
            case 'project':
                return Project::find($id);
            case 'task':
                return Task::find($id);
            case 'comment':
                return Comment::find($id);
            default:
                return null;
        }
    }
}
