<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function index()
    {
        return response()->json(Grade::with(['student', 'schoolClass'])->get());
    }

    public function store(Request $request)
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

        return response()->json($grade, 201);
    }

    public function show(Grade $grade)
    {
        return response()->json($grade->load(['student', 'schoolClass']));
    }

    public function update(Request $request, Grade $grade)
    {
        $grade->update($request->all());
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