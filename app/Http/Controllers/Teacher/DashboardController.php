<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Grade;
use App\Models\Attendance;

class DashboardController extends Controller
{
    public function index()
    {
        $teacher = auth()->user();

        $classes = SchoolClass::where('teacher_id', $teacher->id)->get();

        $totalStudents = Student::whereIn('section',
            $classes->pluck('section')
        )->where('grade_level', $classes->first()?->grade_level ?? '10')->count();

        $recentGrades = Grade::whereHas('schoolClass', fn($q) =>
            $q->where('teacher_id', $teacher->id)
        )->with(['student', 'schoolClass'])->latest()->take(5)->get();

        $todayAttendance = Attendance::whereHas('schoolClass', fn($q) =>
            $q->where('teacher_id', $teacher->id)
        )->whereDate('date', today())->count();

        return view('teacher.dashboard', compact(
            'classes', 'totalStudents', 'recentGrades', 'todayAttendance'
        ));
    }

    public function myClass(SchoolClass $class)
    {
        $students = Student::where('grade_level', $class->grade_level)
            ->where('section', $class->section)
            ->get();

        $grades = Grade::where('school_class_id', $class->id)
            ->with('student')
            ->get()
            ->keyBy('student_id');

        return view('teacher.class', compact('class', 'students', 'grades'));
    }
}