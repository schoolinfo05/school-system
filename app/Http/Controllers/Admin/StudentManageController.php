<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Fee;
use App\Services\PointsService;
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

        $routePrefix = $request->routeIs('registrar.*') ? 'registrar' : 'admin';

        return view('admin.students.index', compact('students', 'routePrefix'));
    }

    public function show(Request $request, Student $student)
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

        $routePrefix = $request->routeIs('registrar.*') ? 'registrar' : 'admin';

        return view('admin.students.show', compact('student', 'grades', 'fees', 'attendance', 'attendancePct', 'routePrefix'));
    }

    public function storeFee(Request $request, Student $student)
    {
        $data = $request->validate([
            'type'        => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'due_date'    => ['required', 'date'],
            'school_year' => ['required', 'string', 'max:255'],
            'semester'    => ['nullable', 'string', 'max:20'],
            'quarter'     => ['nullable', 'string', 'max:50'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        $student->fees()->create($data);

        return back()->with('status', 'Tuition fee posted.');
    }

    public function markFeePaid(Request $request, Student $student, Fee $fee, PointsService $points)
    {
        abort_unless((int) $fee->student_id === (int) $student->id, 404);

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:cash,gcash,qrph,bank_transfer'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $remaining = max(0, (float) $fee->amount - (float) $fee->paid_amount);
        if ($remaining <= 0) {
            return back()->with('status', 'This fee is already paid.');
        }

        $paidAmount = min((float) ($data['amount'] ?? $remaining), $remaining);
        $newPaidAmount = (float) $fee->paid_amount + $paidAmount;
        $status = $newPaidAmount >= (float) $fee->amount ? 'paid' : 'partial';

        $fee->update([
            'paid_amount' => $newPaidAmount,
            'status' => $status,
            'paid_date' => $status === 'paid' ? now() : $fee->paid_date,
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? $fee->payment_reference,
            'paid_by' => auth()->id(),
            'verified_by' => auth()->id(),
            'payment_verified_at' => now(),
        ]);

        if ($status === 'paid' && $fee->fresh()->paid_date && $fee->fresh()->due_date && $fee->fresh()->paid_date->lte($fee->fresh()->due_date)) {
            $points->awardVerifiedPoints($student, [
                'source' => 'early_payment',
                'source_key' => "early-payment:fee:{$fee->id}",
                'title' => 'Early tuition payment bonus',
                'description' => "Paid {$fee->type} on or before the due date.",
                'points' => 40,
                'school_year' => $fee->school_year,
                'semester' => $fee->semester,
                'meta' => ['fee_id' => $fee->id],
            ], auth()->user());
        }

        return back()->with('status', $status === 'paid' ? 'Fee marked as paid.' : 'Partial payment recorded.');
    }
}
