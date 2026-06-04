<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplication;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminStudentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeStudentManager($request);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $students = Student::query()
            ->with('user:id,name,email,role,created_at')
            ->when(in_array($status, ['active', 'inactive', 'graduated'], true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('student_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('grade_level', 'like', "%{$search}%")
                        ->orWhere('section', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json($students->map(fn (Student $student) => $this->studentPayload($student)));
    }

    public function update(Request $request, Student $student)
    {
        $this->authorizeStudentManager($request);

        $data = $request->validate([
            'student_id'  => ['required', 'string', 'max:255', Rule::unique('students', 'student_id')->ignore($student->id)],
            'first_name'  => ['required', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'email'       => [
                'required',
                'email',
                'max:255',
                Rule::unique('students', 'email')->ignore($student->id),
                Rule::unique('users', 'email')->ignore($student->user_id),
            ],
            'phone'       => ['nullable', 'string', 'max:50'],
            'birthdate'   => ['nullable', 'date'],
            'gender'      => ['required', 'in:male,female'],
            'address'     => ['nullable', 'string', 'max:500'],
            'grade_level' => ['required', 'string', 'max:255'],
            'section'     => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
            'status'      => ['required', 'in:active,inactive,graduated'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],
            'prev_school' => ['nullable', 'string', 'max:255'],
            'prev_school_address' => ['nullable', 'string', 'max:255'],
            'student_type' => ['nullable', 'in:new_student,old_student,transferee,returnee'],
            'academic_status' => ['nullable', 'in:Regular,Irregular'],
        ]);

        $student->update(collect($data)->only([
            'student_id', 'first_name', 'last_name', 'email', 'phone', 'birthdate',
            'gender', 'address', 'grade_level', 'section', 'school_year', 'status',
        ])->all());

        $application = EnrollmentApplication::query()
            ->where('user_id', $student->user_id)
            ->latest()
            ->first();

        if ($application) {
            $application->update(collect($data)->only([
                'father_name', 'father_occupation', 'mother_name', 'mother_occupation',
                'prev_school', 'prev_school_address', 'student_type', 'academic_status',
            ])->all());
        }

        if ($student->user) {
            $student->user->update([
                'name'  => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
                'role'  => 'student',
            ]);
            $student->user->syncRoles(['student']);
        }

        return response()->json($this->studentPayload($student->load('user:id,name,email,role,created_at')));
    }

    public function resetPassword(Request $request, Student $student)
    {
        $this->authorizeStudentManager($request);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        if (!$student->user) {
            return response()->json(['message' => 'Student user account not found.'], 404);
        }

        $student->user->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json(['message' => 'Student password updated.']);
    }

    public function destroy(Request $request, Student $student)
    {
        $this->authorizeAdmin($request);

        $user = $student->user;

        if ($user) {
            $user->delete();
        } else {
            $student->delete();
        }

        return response()->json(['message' => 'Student account deleted.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || ($user->role !== 'admin' && !$user->hasRole('admin'))) {
            abort(response()->json(['message' => 'Admin access is required.'], 403));
        }
    }

    private function authorizeStudentManager(Request $request): void
    {
        $user = $request->user();

        if (!$user || (
            !in_array($user->role, ['admin', 'registrar'], true)
            && !$user->hasAnyRole(['admin', 'registrar'])
        )) {
            abort(response()->json(['message' => 'Admin or registrar access is required.'], 403));
        }
    }

    private function studentPayload(Student $student): array
    {
        $application = EnrollmentApplication::query()
            ->where('user_id', $student->user_id)
            ->latest()
            ->first();

        $payload = $student->toArray();
        $payload['enrollment'] = $application ? [
            'id'                  => $application->id,
            'father_name'         => $application->father_name,
            'father_occupation'   => $application->father_occupation,
            'mother_name'         => $application->mother_name,
            'mother_occupation'   => $application->mother_occupation,
            'prev_school'         => $application->prev_school,
            'prev_school_address' => $application->prev_school_address,
            'student_type'        => $application->student_type,
            'academic_status'     => $application->academic_status,
            'program_type'        => $application->program_type,
            'course'              => $application->course,
            'strand'              => $application->strand,
            'semester'            => $application->semester,
            'subject_ids'         => $application->subject_ids ?? [],
        ] : null;
        $payload['subjects'] = $this->subjectsForStudent($student, $application);

        return $payload;
    }

    private function subjectsForStudent(Student $student, ?EnrollmentApplication $application): array
    {
        $sectionSubjects = Section::query()
            ->whereHas('students', function ($query) use ($student) {
                $query->where('users.id', $student->user_id)
                    ->where('section_students.status', 'enrolled');
            })
            ->with(['sectionSubjects.subject', 'sectionSubjects.teacher:id,name'])
            ->get()
            ->flatMap(fn (Section $section) => $section->sectionSubjects->map(fn ($sectionSubject) => [
                'id'         => $sectionSubject->subject?->id,
                'code'       => $sectionSubject->subject?->code,
                'name'       => $sectionSubject->subject?->name,
                'units_lec'  => $sectionSubject->subject?->units_lec,
                'units_lab'  => $sectionSubject->subject?->units_lab,
                'teacher'    => $sectionSubject->teacher?->name,
                'day'        => $sectionSubject->day,
                'time_start' => $sectionSubject->time_start,
                'time_end'   => $sectionSubject->time_end,
                'room'       => $sectionSubject->room,
                'source'     => 'section',
            ]));

        $sectionSubjectIds = $sectionSubjects->pluck('id')->filter()->unique()->all();
        $directSubjectIds = collect($application?->subject_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && !in_array($id, $sectionSubjectIds, true))
            ->unique()
            ->values();

        $directSubjects = Subject::query()
            ->whereIn('id', $directSubjectIds)
            ->get()
            ->map(fn (Subject $subject) => [
                'id'         => $subject->id,
                'code'       => $subject->code,
                'name'       => $subject->name,
                'units_lec'  => $subject->units_lec,
                'units_lab'  => $subject->units_lab,
                'teacher'    => null,
                'day'        => null,
                'time_start' => null,
                'time_end'   => null,
                'room'       => null,
                'source'     => 'enrollment',
            ]);

        return $sectionSubjects
            ->concat($directSubjects)
            ->filter(fn ($subject) => $subject['id'])
            ->values()
            ->all();
    }
}
