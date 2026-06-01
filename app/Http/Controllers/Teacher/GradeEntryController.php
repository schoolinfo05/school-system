<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Grade;
use Illuminate\Http\Request;

class GradeEntryController extends Controller
{
    public function index(SchoolClass $class)
    {
        $students = Student::where('grade_level', $class->grade_level)
            ->where('section', $class->section)
            ->get();

        $quarters = ['1','2','3','4'];

        $grades = Grade::where('school_class_id', $class->id)
            ->get()
            ->groupBy('student_id');

        return view('teacher.grades', compact('class', 'students', 'grades', 'quarters'));
    }

    public function store(Request $request, SchoolClass $class)
    {
        $request->validate([
            'grades'           => 'required|array',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.quarter'    => 'required|in:1,2,3,4',
            'grades.*.score'      => 'required|numeric|min:0|max:100',
        ]);

        foreach ($request->grades as $gradeData) {
            $remarks = $this->getRemark($gradeData['score']);
            Grade::updateOrCreate(
                [
                    'student_id'      => $gradeData['student_id'],
                    'school_class_id' => $class->id,
                    'quarter'         => $gradeData['quarter'],
                    'school_year'     => $class->school_year,
                ],
                [
                    'score'   => $gradeData['score'],
                    'remarks' => $remarks,
                ]
            );
        }

        return back()->with('success', 'Grades saved successfully!');
    }

    private function getRemark(float $score): string
    {
        if ($score >= 90) return 'Outstanding';
        if ($score >= 85) return 'Very Satisfactory';
        if ($score >= 80) return 'Satisfactory';
        if ($score >= 75) return 'Fairly Satisfactory';
        return 'Did Not Meet Expectations';
    }
}