<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Collection;

/**
 * @property \App\Models\Team $team
 */

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'description',
        'status',
        'due_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];





    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function scopeActiveProjects($query)
    {
        return $query->where('status', '!=', 'completed');
    }
}
