<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StudentManageController;
use App\Http\Controllers\Admin\UserManageController;
use App\Http\Controllers\Registrar\DashboardController as RegistrarDashboardController;
use App\Http\Controllers\Registrar\EnrollmentReviewController;
use App\Http\Controllers\Registrar\PointsController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\Teacher\GradeEntryController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect('/login');
    }

    if (!in_array(auth()->user()->role, ['admin', 'registrar'], true)) {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/login');
    }

    return match (auth()->user()->role) {
        'registrar' => redirect('/registrar/dashboard'),
        'admin' => redirect('/admin/dashboard'),
    };
});

Route::get('/dashboard', function () {
    if (!in_array(auth()->user()->role, ['admin', 'registrar'], true)) {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/login');
    }

    return match (auth()->user()->role) {
        'registrar' => redirect('/registrar/dashboard'),
        'admin' => redirect('/admin/dashboard'),
    };
})->middleware(['auth', 'web.roles:admin,registrar'])->name('dashboard');

Route::middleware(['auth', 'web.roles:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/students', [StudentManageController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [StudentManageController::class, 'show'])->name('students.show');
    Route::get('/users', [UserManageController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManageController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserManageController::class, 'update'])->name('users.update');
});

Route::middleware(['auth', 'web.roles:admin,registrar'])->prefix('registrar')->name('registrar.')->group(function () {
    Route::get('/dashboard', [RegistrarDashboardController::class, 'index'])->name('dashboard');
    Route::get('/enrollments', [EnrollmentReviewController::class, 'index'])->name('enrollments.index');
    Route::get('/enrollments/{enrollment}', [EnrollmentReviewController::class, 'show'])->name('enrollments.show');
    Route::post('/enrollments/{enrollment}/approve', [EnrollmentReviewController::class, 'approve'])->name('enrollments.approve');
    Route::post('/enrollments/{enrollment}/reject', [EnrollmentReviewController::class, 'reject'])->name('enrollments.reject');
    Route::get('/points', [PointsController::class, 'index'])->name('points.index');
    Route::post('/points', [PointsController::class, 'store'])->name('points.store');
});

Route::middleware(['auth', 'web.roles:admin,registrar'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard',                    [TeacherDashboard::class, 'index'])->name('dashboard');
    Route::get('/class/{class}',                [TeacherDashboard::class, 'myClass'])->name('class');
    Route::get('/class/{class}/grades',         [GradeEntryController::class, 'index'])->name('grades');
    Route::post('/class/{class}/grades',        [GradeEntryController::class, 'store'])->name('grades.store');
    Route::get('/class/{class}/attendance',     [TeacherAttendance::class, 'index'])->name('attendance');
    Route::post('/class/{class}/attendance',    [TeacherAttendance::class, 'store'])->name('attendance.store');
});

require __DIR__.'/auth.php';
