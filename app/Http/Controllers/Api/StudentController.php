<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Fee;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index()
    {
        return response()->json(Student::with('user')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id'  => 'required|unique:students',
            'first_name'  => 'required',
            'last_name'   => 'required',
            'email'       => 'required|email|unique:students',
            'gender'      => 'required|in:male,female',
            'grade_level' => 'required',
            'section'     => 'required',
            'school_year' => 'required',
            'user_id'     => 'required|exists:users,id',
        ]);

        $student = Student::create($request->all());
        return response()->json($student, 201);
    }

    public function show(Student $student)
    {
        return response()->json($student->load('user'));
    }

    public function update(Request $request, Student $student)
    {
        $student->update($request->all());
        return response()->json($student);
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return response()->json(['message' => 'Student deleted']);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $grades = Grade::where('student_id', $student->id)
            ->with('schoolClass')
            ->get()
            ->groupBy('quarter');

        $attendance = Attendance::where('student_id', $student->id)->get();
        $totalDays = $attendance->count();
        $presentDays = $attendance->where('status', 'present')->count();
        $attendancePct = $totalDays > 0
            ? round(($presentDays / $totalDays) * 100, 1)
            : 0;

        $fees = Fee::where('student_id', $student->id)
            ->where('status', '!=', 'paid')
            ->get();

        return response()->json([
            'student'        => $student,
            'grades'         => $grades,
            'attendance_pct' => $attendancePct,
            'pending_fees'   => $fees,
        ]);
    }
}