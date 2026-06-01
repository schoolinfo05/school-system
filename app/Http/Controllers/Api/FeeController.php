<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fee;
use App\Models\Student;
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function index()
    {
        return response()->json(Fee::with('student')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id'  => 'required|exists:students,id',
            'type'        => 'required',
            'amount'      => 'required|numeric',
            'due_date'    => 'required|date',
            'school_year' => 'required',
        ]);

        $fee = Fee::create($request->all());
        return response()->json($fee, 201);
    }

    public function show(Fee $fee)
    {
        return response()->json($fee->load('student'));
    }

    public function update(Request $request, Fee $fee)
    {
        $fee->update($request->all());
        if ($request->status === 'paid') {
            $fee->update(['paid_date' => now(), 'paid_amount' => $fee->amount]);
        }
        return response()->json($fee);
    }

    public function destroy(Fee $fee)
    {
        $fee->delete();
        return response()->json(['message' => 'Fee deleted']);
    }

    public function byStudent(Student $student)
    {
        $fees = Fee::where('student_id', $student->id)
            ->orderBy('due_date')
            ->get();

        return response()->json([
            'fees'          => $fees,
            'total_due'     => $fees->where('status', '!=', 'paid')->sum('amount'),
            'total_paid'    => $fees->where('status', 'paid')->sum('paid_amount'),
        ]);
    }
}