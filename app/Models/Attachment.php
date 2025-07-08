<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'disk',
        'file_name',
        'file_size',
        'mime_type',
        'attachable_id',
        'attachable_type',
        'user_id',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return number_format($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }

    public function getUrlAttribute()
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * The "booted" method of the model.
     * Deletes the physical file when the attachment record is deleted.
     */
    protected static function booted()
    {

    }
}
