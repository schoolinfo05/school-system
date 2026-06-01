<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $fillable = [
        'student_id', 'type', 'amount', 'paid_amount',
        'status', 'due_date', 'paid_date',
        'school_year', 'quarter', 'notes',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}