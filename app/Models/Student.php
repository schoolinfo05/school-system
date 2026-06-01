<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'student_id', 'first_name', 'last_name', 'email',
        'phone', 'birthdate', 'gender', 'address',
        'grade_level', 'section', 'school_year', 'status', 'user_id',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function grades() { return $this->hasMany(Grade::class); }
    public function attendances() { return $this->hasMany(Attendance::class); }
    public function fees() { return $this->hasMany(Fee::class); }
}