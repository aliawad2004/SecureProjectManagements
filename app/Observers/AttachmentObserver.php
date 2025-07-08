<?php

namespace App\Observers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AttachmentObserver
{

    public function deleting(Attachment $attachment): void
    {
      if (Storage::disk($attachment->disk)->exists($attachment->path)) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            Log::info("Observer: Attachment file '{$attachment->path}' deleted from disk '{$attachment->disk}'.");
        } else {
            Log::warning("Observer: Attempted to delete attachment file '{$attachment->path}' from disk '{$attachment->disk}', but file not found.");
        }
    }

    /**
     * Handle the Attachment "created" event.
     */
    public function created(Attachment $attachment): void
    {
        Log::info("Observer: Attachment '{$attachment->file_name}' (ID: {$attachment->id}) was created.");
    }

    /**
     * Handle the Attachment "updated" event.
     */
    public function updated(Attachment $attachment): void
    {
        Log::info("Observer: Attachment '{$attachment->file_name}' (ID: {$attachment->id}) was updated.");
    }

    /**
     * Handle the Attachment "restored" event.
     */
    public function restored(Attachment $attachment): void
    {
        Log::info("Observer: Attachment '{$attachment->file_name}' (ID: {$attachment->id}) was restored.");
    }

    /**
     * Handle the Attachment "force deleted" event.
     */
    public function forceDeleted(Attachment $attachment): void
    {
        Log::info("Observer: Attachment '{$attachment->file_name}' (ID: {$attachment->id}) was force deleted.");
    }
}
