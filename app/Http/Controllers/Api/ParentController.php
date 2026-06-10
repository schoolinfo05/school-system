<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function dashboard(Request $request)
    {
        if (!$request->user()->hasRole('parent') && $request->user()->role !== 'parent') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $children = Student::query()
            ->where('parent_user_id', $request->user()->id)
            ->orderBy('last_name')
            ->get()
            ->map(function (Student $student) {
                $grades = Grade::where('student_id', $student->id)
                    ->with('schoolClass')
                    ->get()
                    ->groupBy('quarter');

                $attendance = Attendance::where('student_id', $student->id)->get();
                $totalDays = $attendance->count();
                $presentDays = $attendance->where('status', 'present')->count();

                $fees = Fee::where('student_id', $student->id)->get();

                return [
                    'student' => $student,
                    'grades' => $grades,
                    'attendance_pct' => $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0,
                    'fees' => $fees,
                    'fee_summary' => [
                        'total' => (float) $fees->sum('amount'),
                        'paid' => (float) $fees->sum('paid_amount'),
                        'balance' => (float) $fees->sum(fn (Fee $fee) => max(0, $fee->amount - $fee->paid_amount)),
                    ],
                ];
            });

        return response()->json([
            'children' => $children,
        ]);
    }
}
