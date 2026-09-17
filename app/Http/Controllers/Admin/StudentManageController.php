<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplication;
use App\Models\Section;
use App\Models\Student;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Services\PointsService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Validation\Rule;

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

        $application = EnrollmentApplication::query()
            ->where('user_id', $student->user_id)
            ->latest()
            ->first();
        $subjects = $this->subjectsForStudent($student, $application);

        $routePrefix = $request->routeIs('registrar.*') ? 'registrar' : 'admin';

        return view('admin.students.show', compact('student', 'grades', 'fees', 'attendance', 'attendancePct', 'routePrefix', 'subjects'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'student_id' => ['required', 'string', 'max:255', Rule::unique('students', 'student_id')->ignore($student->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('students', 'email')->ignore($student->id),
                Rule::unique('users', 'email')->ignore($student->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'birthdate' => ['nullable', 'date'],
            'gender' => ['required', 'in:male,female'],
            'address' => ['nullable', 'string', 'max:500'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],
            'prev_school' => ['nullable', 'string', 'max:255'],
            'prev_school_address' => ['nullable', 'string', 'max:255'],
            'student_type' => ['nullable', 'in:new_student,old_student,transferee,returnee'],
            'academic_status' => ['nullable', 'in:Regular,Irregular'],
            'grade_level' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive,graduated'],
        ]);

        $student->update($data);

        $application = EnrollmentApplication::query()
            ->where('user_id', $student->user_id)
            ->latest()
            ->first();

        if ($application) {
            $application->update(collect($data)->only([
                'father_name',
                'father_occupation',
                'mother_name',
                'mother_occupation',
                'prev_school',
                'prev_school_address',
                'student_type',
                'academic_status',
            ])->all());
        }

        if ($student->user) {
            $student->user->update([
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $data['email'],
            ]);
        }

        return back()->with('status', 'Student information updated.');
    }

    public function updateParent(Request $request, Student $student)
    {
        $data = $request->validate([
            'parent_email' => ['nullable', 'email'],
        ]);

        $parentId = null;
        $parentEmail = trim((string) ($data['parent_email'] ?? ''));

        if ($parentEmail !== '') {
            $parent = User::where('email', $parentEmail)->first();

            if (!$parent || ($parent->role !== User::ROLE_PARENT && !$parent->hasRole(User::ROLE_PARENT))) {
                return back()
                    ->withErrors(['parent_email' => 'Parent email must belong to a parent account.'])
                    ->withInput();
            }

            $parentId = $parent->id;
        }

        $student->update(['parent_user_id' => $parentId]);

        return back()->with('status', $parentId ? 'Parent account linked.' : 'Parent account unlinked.');
    }

    public function storeFee(Request $request, Student $student)
    {
        $data = $request->validate([
            'type'        => ['required', 'string', 'max:255'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'due_date'    => ['required', 'date'],
            'school_year' => ['required', 'string', 'max:255'],
            'semester'    => ['nullable', 'in:1st,2nd,summer'],
            'quarter'     => ['nullable', 'in:1,2,3,4'],
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

    private function subjectsForStudent(Student $student, ?EnrollmentApplication $application): array
    {
        $overrides = StudentSubject::query()
            ->where('user_id', $student->user_id)
            ->get()
            ->keyBy(fn (StudentSubject $record) => $this->subjectOverrideKey($record->section_id, $record->subject_id));
        $droppedSubjectIds = StudentSubject::query()
            ->where('user_id', $student->user_id)
            ->where('status', 'dropped')
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $sectionSubjects = Section::query()
            ->whereHas('students', function ($query) use ($student) {
                $query->where('users.id', $student->user_id)
                    ->where('section_students.status', 'enrolled');
            })
            ->with(['sectionSubjects.subject', 'sectionSubjects.teacher:id,name'])
            ->get()
            ->flatMap(fn (Section $section) => $section->sectionSubjects
                ->filter(fn ($sectionSubject) => ($overrides->get($this->subjectOverrideKey($section->id, $sectionSubject->subject?->id))?->status ?? 'enrolled') !== 'dropped'
                    && ($overrides->get($this->subjectOverrideKey(null, $sectionSubject->subject?->id))?->status ?? 'enrolled') !== 'dropped')
                ->map(function ($sectionSubject) use ($section, $overrides) {
                $override = $overrides->get($this->subjectOverrideKey($section->id, $sectionSubject->subject?->id));

                return [
                    'id' => $sectionSubject->subject?->id,
                    'section_name' => $section->name,
                    'code' => $sectionSubject->subject?->code,
                    'name' => $sectionSubject->subject?->name,
                    'units' => (int) ($sectionSubject->subject?->units_lec ?? 0) + (int) ($sectionSubject->subject?->units_lab ?? 0),
                    'teacher' => $sectionSubject->teacher?->name,
                    'schedule' => trim(collect([$sectionSubject->day, trim(($sectionSubject->time_start ?: '') . '-' . ($sectionSubject->time_end ?: '')), $sectionSubject->room])->filter()->join(' · ')),
                    'source' => 'section',
                    'status' => $override?->status ?? 'enrolled',
                    'drop_reason' => $override?->drop_reason,
                ];
            }));

        $sectionSubjectIds = $sectionSubjects->pluck('id')->filter()->unique()->all();
        $directSubjectIds = collect($application?->subject_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && !in_array($id, $sectionSubjectIds, true))
            ->diff($droppedSubjectIds)
            ->unique()
            ->values();

        $directSubjects = Subject::query()
            ->whereIn('id', $directSubjectIds)
            ->orderBy('code')
            ->get()
            ->map(function (Subject $subject) use ($overrides) {
                $override = $overrides->get($this->subjectOverrideKey(null, $subject->id));

                return [
                    'id' => $subject->id,
                    'section_name' => 'Direct enrollment',
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'units' => (int) ($subject->units_lec ?? 0) + (int) ($subject->units_lab ?? 0),
                    'teacher' => null,
                    'schedule' => '',
                    'source' => 'enrollment',
                    'status' => $override?->status ?? 'enrolled',
                    'drop_reason' => $override?->drop_reason,
                ];
            });

        return $sectionSubjects
            ->concat($directSubjects)
            ->filter(fn ($subject) => $subject['id'])
            ->values()
            ->all();
    }

    private function subjectOverrideKey(?int $sectionId, ?int $subjectId): string
    {
        return ($sectionId ?? 'direct') . ':' . ($subjectId ?? 'none');
    }
}
