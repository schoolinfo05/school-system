<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplication;
use App\Models\Course;
use App\Models\SchoolNotification;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    // ── PUBLIC ──────────────────────────────────────────────────

    // POST /api/enrollment
    public function store(Request $request)
    {
        $existingApplication = null;

        // For old students, find their most recent approved application
        if ($request->filled('student_type') && $request->student_type === 'old_student' && $request->filled('id_no')) {
            $existingApplication = EnrollmentApplication::where('id_no', $request->id_no)
                ->where('status', 'approved')
                ->latest()
                ->first();
        }

        $rules = [
            // Account
            'email'                 => ['required', 'email', 'unique:enrollment_applications,email'],
            'password'              => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => 'nullable|string|min:8',

            // Personal
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'middle_name'    => 'nullable|string|max:100',
            'birthdate'      => 'nullable|date',
            'gender'         => 'nullable|string|max:20',
            'religion'       => 'nullable|string|max:100',
            'civil_status'   => 'nullable|string|max:50',
            'place_of_birth' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',

            // Family
            'father_name'        => 'nullable|string|max:255',
            'father_occupation'  => 'nullable|string|max:255',
            'mother_name'        => 'nullable|string|max:255',
            'mother_occupation'  => 'nullable|string|max:255',

            // Previous school
            'prev_school'         => 'nullable|string|max:255',
            'prev_school_address' => 'nullable|string|max:255',

            // Classification
            'student_type'    => 'nullable|in:new_student,old_student,transferee,returnee',
            'academic_status' => 'required|in:Regular,Irregular',
            'shiftee_from'    => 'nullable|string|max:100',
            'shiftee_to'      => 'nullable|string|max:100',
            'id_no'           => 'nullable|string|max:50',

            // Program
            'program_type' => 'required|in:shs,college',
            'grade_level'  => 'required_if:program_type,shs|nullable|in:11,12',
            'strand'       => 'required_if:program_type,shs|nullable|in:STEM,ABM,HUMSS,TVL,GAS',
            'course_id'    => 'nullable|exists:courses,id',
            'course'       => 'required_if:program_type,college|required_without:course_id|nullable|string|max:100',
            'year_level'   => 'required_if:program_type,college|nullable|in:1,2,3,4',

            // Subjects
            'subject_ids'   => $request->academic_status === 'Regular'
                ? 'nullable|array'
                : 'required|array|min:1',
            'subject_ids.*' => 'integer|exists:subjects,id',

            // Enrollment period
            'school_year' => 'required|string|max:20',
            'semester'    => 'required|in:1st,2nd,summer',
        ];

        if ($request->student_type === 'old_student') {
            // id_no must match an approved application
            $rules['id_no'] = [
                'required', 'string', 'max:50',
                function ($attribute, $value, $fail) {
                    $exists = EnrollmentApplication::where('id_no', $value)
                        ->where('status', 'approved')
                        ->exists();
                    if (!$exists) {
                        $fail('No approved student record found with that ID.');
                    }
                },
            ];
            // Password optional — existing account will be reused
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];
        } else {
            // New/transferee/returnee — password required
            $rules['password']              = ['required', 'string', 'min:8', 'confirmed'];
            $rules['password_confirmation'] = 'required|string|min:8';
        }

        // Email uniqueness — ignore old student's existing user email
        if ($existingApplication && $existingApplication->user_id) {
            $rules['email'] = [
                'required', 'email',
                'unique:enrollment_applications,email',
                Rule::unique('users', 'email')->ignore($existingApplication->user_id),
            ];
        } else {
            $rules['email'] = [
                'required', 'email',
                'unique:enrollment_applications,email',
                'unique:users,email',
            ];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Normalize gender
        $genderMap = [
            'm' => 'male', 'f' => 'female',
            'male' => 'male', 'female' => 'female', 'other' => 'other',
        ];
        $gender = $genderMap[strtolower($request->gender ?? '')] ?? 'other';

        // Resolve password — reuse existing user's hashed password for old students
        $hashedPassword = null;
        if ($existingApplication && $existingApplication->user_id) {
            $existingUser   = User::find($existingApplication->user_id);
            $hashedPassword = $existingUser?->password ?? Hash::make($request->password);
        } else {
            $hashedPassword = Hash::make($request->password);
        }

        $subjectIds = $this->subjectIdsForApplication($request);
        if ($request->academic_status === 'Regular' && empty($subjectIds)) {
            return response()->json([
                'message' => 'No regular subjects are available for this program and semester.',
            ], 422);
        }

        $application = EnrollmentApplication::create([
            ...$request->except('password', 'password_confirmation', 'gender', 'course', 'subject_ids'),
            'course'   => $request->filled('course_id')
                ? Course::find($request->course_id)?->name
                : $request->course,
            'subject_ids' => $subjectIds,
            'password' => $hashedPassword,
            'gender'   => $gender,
            'status'   => 'pending',
        ]);

        return response()->json([
            'message'        => 'Application submitted successfully.',
            'application_id' => $application->id,
            'email'          => $application->email,
        ], 201);
    }

    // GET /api/enrollment/status?email=xxx
    public function status(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $app = EnrollmentApplication::where('email', $request->email)
            ->select([
                'id', 'first_name', 'last_name', 'middle_name',
                'program_type', 'grade_level', 'strand',
                'course', 'year_level', 'student_type', 'academic_status',
                'school_year', 'semester',
                'status', 'remarks', 'reviewed_at', 'created_at',
            ])
            ->first();

        if (!$app) {
            return response()->json(['message' => 'No application found for this email.'], 404);
        }

        return response()->json($app);
    }

    // GET /api/enrollment/lookup?id_no=xxx
    // Looks up a student by student_id from the students table first,
    // then falls back to enrollment_applications for extra details.
    public function lookup(Request $request)
    {
        $request->validate(['id_no' => 'required|string|max:50']);

        $idNo = trim($request->id_no);

        // 1. Search students table by student_id (exact match)
        $student = Student::where('student_id', $idNo)->first();

        // 2. Partial match — user typed just the number e.g. "14266"
        if (!$student) {
            $student = Student::where('student_id', 'like', '%' . $idNo)->first();
        }

        // 3. Match by email
        if (!$student && filter_var($idNo, FILTER_VALIDATE_EMAIL)) {
            $student = Student::where('email', $idNo)->first();
        }

        if (!$student) {
            return response()->json(['message' => 'No student found with that ID.'], 404);
        }

        // Pull extra details from their most recent approved application
        $app = EnrollmentApplication::where('user_id', $student->user_id)
            ->where('status', 'approved')
            ->latest()
            ->first();

        return response()->json([
            // From students table
            'student_id'          => $student->student_id,
            'first_name'          => $student->first_name,
            'last_name'           => $student->last_name,
            'email'               => $student->email,
            'phone'               => $student->phone,
            'birthdate'           => $student->birthdate,
            'gender'              => $student->gender,
            'address'             => $student->address,
            'grade_level'         => $student->grade_level,
            'school_year'         => $student->school_year,
            // Extra details from enrollment application (if available)
            'middle_name'         => $app?->middle_name,
            'religion'            => $app?->religion,
            'civil_status'        => $app?->civil_status,
            'place_of_birth'      => $app?->place_of_birth,
            'father_name'         => $app?->father_name,
            'father_occupation'   => $app?->father_occupation,
            'mother_name'         => $app?->mother_name,
            'mother_occupation'   => $app?->mother_occupation,
            'prev_school'         => $app?->prev_school,
            'prev_school_address' => $app?->prev_school_address,
            'student_type'        => $app?->student_type,
            'academic_status'     => $app?->academic_status,
            'program_type'        => $app?->program_type,
            'strand'              => $app?->strand,
            'course_id'           => $app?->course_id,
            'course'              => $app?->course,
            'year_level'          => $app?->year_level,
            'semester'            => $app?->semester,
        ]);
    }

    // ── REGISTRAR ────────────────────────────────────────────────

    // GET /api/registrar/enrollments
    public function index(Request $request)
    {
        $this->authorizeRegistrar($request);

        $query = EnrollmentApplication::latest();

        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('program_type')) $query->where('program_type', $request->program_type);
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name',  'like', "%{$request->search}%")
                  ->orWhere('email',      'like', "%{$request->search}%");
            });
        }

        return response()->json($query->get());
    }

    // GET /api/registrar/enrollments/{id}
    public function show(Request $request, $id)
    {
        $this->authorizeRegistrar($request);
        return response()->json(EnrollmentApplication::findOrFail($id));
    }

    // POST /api/registrar/enrollments/{id}/approve
    public function approve(Request $request, $id)
    {
        $this->authorizeRegistrar($request);
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $app = EnrollmentApplication::findOrFail($id);

        if ($app->status !== 'pending') {
            return response()->json(['message' => 'Application already reviewed.'], 422);
        }

        DB::transaction(function () use ($app, $request) {
            // Reuse existing user account if one already exists for this email
            $user = User::where('email', $app->email)->first();

            if (!$user) {
                $user = User::create([
                    'name'     => trim("{$app->first_name} {$app->last_name}"),
                    'email'    => $app->email,
                    'password' => $app->password, // already hashed
                    'role'     => 'student',
                ]);
                $user->assignRole('student');
            }

            $studentId = $app->id_no ?: self::generateStudentId($app->id);

            $app->update([
                'status'      => 'approved',
                'remarks'     => $request->remarks,
                'user_id'     => $user->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'id_no'       => $studentId,
            ]);

            // Sync to students table so grades/attendance/fees work
            Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_id'  => $studentId,
                    'first_name'  => $app->first_name,
                    'last_name'   => $app->last_name,
                    'email'       => $app->email,
                    'phone'       => $app->contact_number,
                    'birthdate'   => $app->birthdate,
                    'gender'      => in_array($app->gender, ['male','female']) ? $app->gender : 'male',
                    'address'     => $app->address,
                    'grade_level' => $app->program_type === 'college' ? $app->year_level : $app->grade_level,
                    'section'     => 'TBA',
                    'school_year' => $app->school_year,
                    'status'      => 'active',
                ]
            );

            SchoolNotification::create([
                'user_id' => $user->id,
                'type' => 'enrollment_approved',
                'title' => 'Enrollment approved',
                'body' => 'Your enrollment application has been approved. You can now sign in.',
                'channels' => ['in_app'],
                'data' => ['enrollment_application_id' => $app->id],
            ]);
        });

        return response()->json([
            'message' => 'Application approved. Student account and record created.',
        ]);
    }

    // POST /api/registrar/enrollments/{id}/reject
    public function reject(Request $request, $id)
    {
        $this->authorizeRegistrar($request);
        $request->validate(['remarks' => 'required|string|max:500']);

        $app = EnrollmentApplication::findOrFail($id);

        if ($app->status !== 'pending') {
            return response()->json(['message' => 'Application already reviewed.'], 422);
        }

        $app->update([
            'status'      => 'rejected',
            'remarks'     => $request->remarks,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($app->user_id) {
            SchoolNotification::create([
                'user_id' => $app->user_id,
                'type' => 'enrollment_rejected',
                'title' => 'Enrollment rejected',
                'body' => $request->remarks,
                'channels' => ['in_app'],
                'data' => ['enrollment_application_id' => $app->id],
            ]);
        }

        return response()->json(['message' => 'Application rejected.']);
    }

    // POST /api/registrar/students — direct student creation (no approval flow)
    public function storeStudent(Request $request)
    {
        $this->authorizeRegistrar($request);

        $validator = Validator::make($request->all(), [
            'email'          => 'required|email|unique:users,email|unique:enrollment_applications,email',
            'password'       => 'required|string|min:8',
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'middle_name'    => 'nullable|string|max:100',
            'birthdate'      => 'nullable|date',
            'gender'         => 'nullable|in:male,female,other',
            'religion'       => 'nullable|string|max:100',
            'civil_status'   => 'nullable|string|max:50',
            'place_of_birth' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'father_name'        => 'nullable|string|max:255',
            'father_occupation'  => 'nullable|string|max:255',
            'mother_name'        => 'nullable|string|max:255',
            'mother_occupation'  => 'nullable|string|max:255',
            'prev_school'         => 'nullable|string|max:255',
            'prev_school_address' => 'nullable|string|max:255',
            'student_type'    => 'nullable|in:new_student,old_student,transferee,returnee',
            'academic_status' => 'nullable|in:Regular,Irregular',
            'id_no'           => 'nullable|string|max:50',
            'program_type'    => 'required|in:shs,college',
            'course_id'       => 'nullable|exists:courses,id',
            'course'          => 'nullable|string|max:100',
            'year_level'      => 'nullable|in:1,2,3,4',
            'grade_level'     => 'nullable|in:11,12',
            'strand'          => 'nullable|in:STEM,ABM,HUMSS,TVL,GAS',
            'school_year'     => 'required|string|max:20',
            'semester'        => 'required|in:1st,2nd,summer',
            'subject_ids'     => 'nullable|array',
            'subject_ids.*'   => 'integer|exists:subjects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $subjectIds = $this->subjectIdsForApplication($request);
        if ($request->academic_status === 'Regular' && empty($subjectIds)) {
            return response()->json([
                'message' => 'No regular subjects are available for this program and semester.',
            ], 422);
        }

        DB::transaction(function () use ($request, $subjectIds) {
            // Create user account
            $user = User::create([
                'name'     => trim("{$request->first_name} {$request->last_name}"),
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => 'student',
            ]);
            $user->assignRole('student');

            $courseName = $request->filled('course_id')
                ? Course::find($request->course_id)?->name
                : $request->course;

            // Create an already-approved enrollment application so the student
            // appears in all enrollment records and can be looked up by id_no
            $app = EnrollmentApplication::create([
                'email'          => $request->email,
                'password'       => $user->password,
                'first_name'     => $request->first_name,
                'last_name'      => $request->last_name,
                'middle_name'    => $request->middle_name,
                'birthdate'      => $request->birthdate,
                'gender'         => $request->gender,
                'religion'       => $request->religion,
                'civil_status'   => $request->civil_status,
                'place_of_birth' => $request->place_of_birth,
                'contact_number' => $request->contact_number,
                'address'        => $request->address,
                'father_name'        => $request->father_name,
                'father_occupation'  => $request->father_occupation,
                'mother_name'        => $request->mother_name,
                'mother_occupation'  => $request->mother_occupation,
                'prev_school'         => $request->prev_school,
                'prev_school_address' => $request->prev_school_address,
                'student_type'    => $request->student_type ?? 'new_student',
                'academic_status' => $request->academic_status,
                'shiftee_from'    => $request->shiftee_from,
                'shiftee_to'      => $request->shiftee_to,
                'id_no'           => $request->id_no,
                'program_type'    => $request->program_type,
                'course_id'       => $request->course_id,
                'course'          => $courseName,
                'year_level'      => $request->year_level,
                'grade_level'     => $request->grade_level,
                'strand'          => $request->strand,
                'school_year'     => $request->school_year,
                'semester'        => $request->semester,
                'subject_ids'     => $subjectIds,
                // Pre-approved — no pending review needed
                'status'      => 'approved',
                'user_id'     => $user->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // If no id_no was given, auto-generate one from the app id
            $studentId = $app->id_no ?: self::generateStudentId($app->id);
            if (!$app->id_no) {
                $app->update(['id_no' => $studentId]);
            }

            // Sync to students table so grades/attendance/fees work
            Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_id'  => $studentId,
                    'first_name'  => $request->first_name,
                    'last_name'   => $request->last_name,
                    'email'       => $request->email,
                    'phone'       => $request->contact_number,
                    'birthdate'   => $request->birthdate,
                    'gender'      => in_array($request->gender, ['male','female']) ? $request->gender : 'male',
                    'address'     => $request->address,
                    'grade_level' => $request->program_type === 'college' ? $request->year_level : $request->grade_level,
                    'section'     => 'TBA',
                    'school_year' => $request->school_year,
                    'status'      => 'active',
                ]
            );
        });

        return response()->json([
            'message' => 'Student created and enrolled successfully.',
        ], 201);
    }

    // ── PRIVATE ──────────────────────────────────────────────────

    /**
     * Generate a student ID in the format SCC-YY-XXXXXXXX
     * e.g. SCC-25-00014266
     *
     * You can change the prefix ('SCC') and year logic here.
     */
    private static function generateStudentId(int $appId): string
    {
        $prefix = 'SCC';
        $year   = date('y'); // 2-digit year, e.g. 25 for 2025
        $seq    = str_pad($appId, 8, '0', STR_PAD_LEFT); // 8-digit zero-padded
        return "{$prefix}-{$year}-{$seq}";
    }

    private function subjectIdsForApplication(Request $request): array
    {
        if ($request->academic_status !== 'Regular') {
            return collect($request->subject_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $courseName = $request->filled('course_id')
            ? Course::find($request->course_id)?->name
            : $request->course;

        return Subject::query()
            ->where('program_type', $request->program_type)
            ->where('is_active', true)
            ->when($request->semester, fn ($query) => $query->where('semester', strtolower($request->semester)))
            ->when($request->program_type === 'college', function ($query) use ($request, $courseName) {
                $query->where('year_level', $request->year_level)
                    ->where(function ($courseQuery) use ($courseName) {
                        $courseQuery->where('course', $courseName)
                            ->orWhereNull('course')
                            ->orWhere('course', '');
                    });
            })
            ->when($request->program_type === 'shs', function ($query) use ($request) {
                $query->where('year_level', $request->grade_level)
                    ->where(function ($strandQuery) use ($request) {
                        $strandQuery->where('strand', $request->strand)
                            ->orWhereNull('strand')
                            ->orWhere('strand', '');
                    });
            })
            ->orderBy('code')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function authorizeRegistrar(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['registrar', 'admin'])) {
            abort(403, 'Unauthorized.');
        }
    }
}
