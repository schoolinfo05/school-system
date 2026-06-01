<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'name', 'description', 'program_type', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
