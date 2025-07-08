<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Notification extends DatabaseNotification
{
    use HasFactory,HasUuids;


    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        'user_id'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnreadNotifications($query)
    {
        return $query->whereNull('read_at');
    }
}