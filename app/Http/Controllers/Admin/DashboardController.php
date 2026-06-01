<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Fee;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_students'  => Student::count(),
            'active_students' => Student::where('status', 'active')->count(),
            'total_fees_due'  => Fee::where('status', '!=', 'paid')->sum('amount'),
            'total_collected' => Fee::where('status', 'paid')->sum('paid_amount'),
            'total_teachers'  => User::role('teacher')->count(),
        ];

        $recentStudents = Student::latest()->take(5)->get();

        $unpaidFees = Fee::with('student')
            ->where('status', '!=', 'paid')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentStudents', 'unpaidFees'));
    }
}