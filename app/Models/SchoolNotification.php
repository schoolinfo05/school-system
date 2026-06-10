<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolNotification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'channels',
        'data',
        'read_at',
    ];

    protected $casts = [
        'channels' => 'array',
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
