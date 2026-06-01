<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index()
    {
        return response()->json(Attendance::with(['student', 'schoolClass'])->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id'      => 'required|exists:students,id',
            'school_class_id' => 'required',
            'date'            => 'required|date',
            'status'          => 'required|in:present,absent,late,excused',
        ]);

        $attendance = Attendance::updateOrCreate(
            [
                'student_id'      => $request->student_id,
                'school_class_id' => $request->school_class_id,
                'date'            => $request->date,
            ],
            ['status' => $request->status, 'remarks' => $request->remarks]
        );

        return response()->json($attendance, 201);
    }

    public function show(Attendance $attendance)
    {
        return response()->json($attendance->load(['student', 'schoolClass']));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $attendance->update($request->all());
        return response()->json($attendance);
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return response()->json(['message' => 'Attendance deleted']);
    }

    public function byStudent(Student $student)
    {
        $attendance = Attendance::where('student_id', $student->id)
            ->orderBy('date', 'desc')
            ->get();

        $total   = $attendance->count();
        $present = $attendance->where('status', 'present')->count();

        return response()->json([
            'records'        => $attendance,
            'total_days'     => $total,
            'present_days'   => $present,
            'attendance_pct' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        ]);
    }
}