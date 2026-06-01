<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Fee;
use Illuminate\Http\Request;

class StudentManageController extends Controller
{
    public function index(Request $request)
    {
        $students = Student::query()
            ->when($request->search, fn($q) =>
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%")
                  ->orWhere('student_id', 'like', "%{$request->search}%")
            )
            ->when($request->grade_level, fn($q) =>
                $q->where('grade_level', $request->grade_level)
            )
            ->latest()
            ->paginate(15);

        return view('admin.students.index', compact('students'));
    }

    public function show(Student $student)
    {
        $grades = Grade::where('student_id', $student->id)
            ->with('schoolClass')
            ->get()
            ->groupBy('quarter');

        $fees = Fee::where('student_id', $student->id)->get();

        $attendance = Attendance::where('student_id', $student->id)->get();

        $attendancePct = $attendance->count() > 0
            ? round(($attendance->where('status', 'present')->count() / $attendance->count()) * 100, 1)
            : 0;

        return view('admin.students.show', compact('student', 'grades', 'fees', 'attendance', 'attendancePct'));
    }
}