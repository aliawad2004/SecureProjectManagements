<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \App\Models\Project $project
 */
class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'assigned_to_user_id',
        'name',
        'description',
        'status',
        'priority',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];


    /**
     * Get the project that owns the task.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeOverdueTasks($query)
    {
        return $query->where('due_date', '<', now())->where('status', '!=', 'completed');
    }
}
