<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use App\Services\PointsService;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function index()
    {
        return response()->json(Grade::with(['student', 'schoolClass'])->get());
    }

    public function store(Request $request, PointsService $points)
    {
        $request->validate([
            'student_id'      => 'required|exists:students,id',
            'school_class_id' => 'required',
            'school_year'     => 'required',
            'quarter'         => 'required|in:1,2,3,4',
            'score'           => 'nullable|numeric|min:0|max:100',
        ]);

        $grade = Grade::updateOrCreate(
            [
                'student_id'      => $request->student_id,
                'school_class_id' => $request->school_class_id,
                'quarter'         => $request->quarter,
                'school_year'     => $request->school_year,
            ],
            [
                'score'   => $request->score,
                'remarks' => $request->remarks,
            ]
        );

        if ($request->score !== null) {
            $student = Student::find($request->student_id);
            if ($student) {
                $points->awardGradePoints(
                    $student,
                    (float) $request->score,
                    "grade:{$student->id}:{$request->school_class_id}:{$request->quarter}:{$request->school_year}",
                    $request->user(),
                    $request->school_year,
                    null,
                    ['grade_id' => $grade->id, 'school_class_id' => $request->school_class_id, 'quarter' => $request->quarter]
                );
            }
        }

        return response()->json($grade, 201);
    }

    public function show(Grade $grade)
    {
        return response()->json($grade->load(['student', 'schoolClass']));
    }

    public function update(Request $request, Grade $grade, PointsService $points)
    {
        $grade->update($request->all());
        if ($grade->score !== null && $grade->student) {
            $points->awardGradePoints(
                $grade->student,
                (float) $grade->score,
                "grade:{$grade->student_id}:{$grade->school_class_id}:{$grade->quarter}:{$grade->school_year}",
                $request->user(),
                $grade->school_year,
                null,
                ['grade_id' => $grade->id, 'school_class_id' => $grade->school_class_id, 'quarter' => $grade->quarter]
            );
        }
        return response()->json($grade);
    }

    public function destroy(Grade $grade)
    {
        $grade->delete();
        return response()->json(['message' => 'Grade deleted']);
    }

    public function byStudent(Student $student)
    {
        $grades = Grade::where('student_id', $student->id)
            ->with('schoolClass')
            ->orderBy('quarter')
            ->get();

        return response()->json($grades);
    }
}
