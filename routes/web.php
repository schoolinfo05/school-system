<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StudentManageController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboard;
use App\Http\Controllers\Teacher\GradeEntryController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendance;

Route::get('/', function () {
    return redirect('/admin/dashboard');
});

Route::get('/dashboard', function () {
    return redirect('/admin/dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/students', [StudentManageController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [StudentManageController::class, 'show'])->name('students.show');
});

Route::middleware(['auth'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard',                    [TeacherDashboard::class, 'index'])->name('dashboard');
    Route::get('/class/{class}',                [TeacherDashboard::class, 'myClass'])->name('class');
    Route::get('/class/{class}/grades',         [GradeEntryController::class, 'index'])->name('grades');
    Route::post('/class/{class}/grades',        [GradeEntryController::class, 'store'])->name('grades.store');
    Route::get('/class/{class}/attendance',     [TeacherAttendance::class, 'index'])->name('attendance');
    Route::post('/class/{class}/attendance',    [TeacherAttendance::class, 'store'])->name('attendance.store');
});

require __DIR__.'/auth.php';