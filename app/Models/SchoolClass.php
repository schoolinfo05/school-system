<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
