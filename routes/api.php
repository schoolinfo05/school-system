<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\FeeController;
use App\Http\Controllers\Api\AiStudyController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\SubjectSectionController;
use App\Http\Controllers\Api\TeacherChatController;

// ── Public routes (no login required) ────────────────────────────
Route::post('/login',            [AuthController::class, 'login']);
Route::post('/enrollment',       [EnrollmentController::class, 'store']);
Route::get('/enrollment/status', [EnrollmentController::class, 'status']);
Route::get('/enrollment/lookup', [EnrollmentController::class, 'lookup']);
Route::get('/sections',          [SubjectSectionController::class, 'sectionIndex']);
Route::get('/sections/{id}',     [SubjectSectionController::class, 'sectionShow']);
Route::get('/subjects',          [SubjectSectionController::class, 'subjectIndex']);
Route::get('/courses',           [CourseController::class, 'index']);
Route::post('/paymongo/webhook', [MarketplaceController::class, 'paymongoWebhook']);

// ── Protected routes (login required) ────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // Students
    Route::apiResource('students', StudentController::class);
    Route::apiResource('grades', GradeController::class);
    Route::apiResource('attendances', AttendanceController::class);
    Route::apiResource('fees', FeeController::class);

    Route::get('/students/{student}/grades',     [GradeController::class, 'byStudent']);
    Route::get('/students/{student}/attendance', [AttendanceController::class, 'byStudent']);
    Route::get('/students/{student}/fees',       [FeeController::class, 'byStudent']);
    Route::get('/dashboard/student',             [StudentController::class, 'dashboard']);
    Route::get('/my-subjects', [SubjectSectionController::class, 'mySubjects']);

    Route::post('/courses',        [CourseController::class, 'store']);
    Route::put('/courses/{id}',    [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);

    Route::post('/subjects',           [SubjectSectionController::class, 'subjectStore']);
    Route::put('/subjects/{id}',       [SubjectSectionController::class, 'subjectUpdate']);
    Route::delete('/subjects/{id}',    [SubjectSectionController::class, 'subjectDestroy']);
    Route::get('/subjects/{id}/prerequisites',                          [SubjectSectionController::class, 'subjectPrerequisites']);
    Route::post('/subjects/{id}/prerequisites',                         [SubjectSectionController::class, 'addSubjectPrerequisite']);
    Route::delete('/subjects/{id}/prerequisites/{prerequisiteId}',      [SubjectSectionController::class, 'removeSubjectPrerequisite']);

    // Sections (registrar)
    Route::post('/sections',                             [SubjectSectionController::class, 'sectionStore']);
    Route::put('/sections/{id}',                         [SubjectSectionController::class, 'sectionUpdate']);
    Route::post('/sections/{id}/subjects',               [SubjectSectionController::class, 'assignSubject']);
    Route::delete('/sections/{id}/subjects/{subjectId}', [SubjectSectionController::class, 'removeSubject']);
    Route::post('/sections/{id}/students',               [SubjectSectionController::class, 'enrollStudent']);
    Route::delete('/sections/{id}/students/{userId}',    [SubjectSectionController::class, 'removeStudent']);

    // AI
    Route::post('/ai/chat',       [AiStudyController::class, 'chat']);
    Route::post('/ai/quick-quiz', [AiStudyController::class, 'quickQuiz']);

    // Teacher
    Route::get('/teacher/dashboard',                 [TeacherController::class, 'dashboard']);
    Route::get('/my-classes',                        [SubjectSectionController::class, 'myClasses']);
    Route::get('/teacher/classes',                   [TeacherController::class, 'classes']);
    Route::get('/teacher/class/{class}/students',    [TeacherController::class, 'classStudents']);
    Route::post('/teacher/class/{class}/grades',     [TeacherController::class, 'saveGrades']);
    Route::post('/teacher/class/{class}/attendance', [TeacherController::class, 'saveAttendance']);
    Route::get('/teacher/class/{class}/attendance',  [TeacherController::class, 'getAttendance']);
    Route::get('/teacher/class/{class}/performance', [TeacherController::class, 'classPerformance']);

    // Teacher-student chat
    Route::get('/teacher-chat/contacts',              [TeacherChatController::class, 'contacts']);
    Route::get('/teacher-chat/{contact}/messages',    [TeacherChatController::class, 'messages']);
    Route::post('/teacher-chat/{contact}/messages',   [TeacherChatController::class, 'send']);

    // Marketplace
    Route::get('/marketplace/my-items',        [MarketplaceController::class, 'myItems']);
    Route::get('/marketplace/my-orders',       [MarketplaceController::class, 'myOrders']);
    Route::get('/marketplace/my-chats',        [MarketplaceController::class, 'myChats']);
    Route::get('/marketplace/sales',           [MarketplaceController::class, 'sales']);
    Route::get('/marketplace/payment-options', [MarketplaceController::class, 'paymentOptions']);
    Route::post('/marketplace/orders/{order}/mark-paid', [MarketplaceController::class, 'markOrderPaid']);
    Route::post('/marketplace/orders/{order}/cancel', [MarketplaceController::class, 'cancelOrder']);
    Route::get('/marketplace',                 [MarketplaceController::class, 'index']);
    Route::post('/marketplace',                [MarketplaceController::class, 'store']);
    Route::get('/marketplace/{item}',          [MarketplaceController::class, 'show']);
    Route::post('/marketplace/{item}/buy',     [MarketplaceController::class, 'buy']);
    Route::post('/marketplace/{item}/paymongo-checkout', [MarketplaceController::class, 'paymongoCheckout']);
    Route::put('/marketplace/{item}',          [MarketplaceController::class, 'update']);
    Route::delete('/marketplace/{item}',       [MarketplaceController::class, 'destroy']);
    Route::post('/marketplace/{item}/message', [MarketplaceController::class, 'sendMessage']);
    Route::get('/marketplace/{item}/messages', [MarketplaceController::class, 'getMessages']);

    // Registrar
    Route::get('/registrar/teachers',                   [SubjectSectionController::class, 'teacherIndex']);
    Route::get('/registrar/section-students',           [SubjectSectionController::class, 'studentIndex']);
    Route::get('/registrar/enrollments',                [EnrollmentController::class, 'index']);
    Route::get('/registrar/enrollments/{id}',           [EnrollmentController::class, 'show']);
    Route::post('/registrar/enrollments/{id}/approve',  [EnrollmentController::class, 'approve']);
    Route::post('/registrar/enrollments/{id}/reject',   [EnrollmentController::class, 'reject']);
    Route::post('/registrar/students',                  [EnrollmentController::class, 'storeStudent']);
});
