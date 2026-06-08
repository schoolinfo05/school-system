<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplication;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Section;
use App\Models\SectionSubject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectSectionController extends Controller
{
    // ── SUBJECTS ─────────────────────────────────────────────

    // GET /api/subjects
    public function subjectIndex(Request $request)
    {
        $subjects = Subject::query()
            ->with('prerequisites:id,code,name')
            ->when($request->program_type, fn($q) => $q->where('program_type', $request->program_type))
            ->when($request->course, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('course', $request->course)
                   ->orWhereNull('course')
                   ->orWhere('course', '');
            }))
            ->when($request->year_level, fn($q) => $q->where('year_level', $request->year_level))
            ->when($request->strand, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('strand', $request->strand)
                   ->orWhereNull('strand')
                   ->orWhere('strand', '');
            }))
            ->when($request->semester, fn($q) => $q->where('semester', $request->semester))
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return response()->json($subjects);
    }

    // POST /api/subjects
    public function subjectStore(Request $request)
    {
        $this->authorizeRegistrar($request);

        $request->validate([
            'code'             => 'required|string|max:20',
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'units_lec'        => 'required|numeric|min:0',
            'units_lab'        => 'required|numeric|min:0',
            'program_type'     => 'required|in:shs,college',
            'course'           => 'nullable|string|max:100',
            'year_level'       => 'nullable|string|max:5',
            'strand'           => 'nullable|string|max:20',
            'semester'         => 'nullable|in:1st,2nd,summer',
            'prerequisite_ids' => 'nullable|array',
            'prerequisite_ids.*' => 'distinct|integer|exists:subjects,id',
        ]);

        $data = $request->only([
            'code', 'name', 'description',
            'units_lec', 'units_lab',
            'program_type', 'course', 'year_level', 'strand', 'semester',
            'is_active',
        ]);
        $data['course'] = $data['course'] === '' ? null : $data['course'];
        $data['strand'] = $data['strand'] === '' ? null : $data['strand'];

        $subject = Subject::create($data);

        if ($request->filled('prerequisite_ids')) {
            $subject->prerequisites()->sync($request->prerequisite_ids);
        }

        return response()->json($subject->load('prerequisites:id,code,name'), 201);
    }

    // PUT /api/subjects/{id}
    public function subjectUpdate(Request $request, $id)
    {
        $this->authorizeRegistrar($request);
        $subject = Subject::findOrFail($id);

        $request->validate([
            'code'             => 'nullable|string|max:20',
            'name'             => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'units_lec'        => 'nullable|numeric|min:0',
            'units_lab'        => 'nullable|numeric|min:0',
            'program_type'     => 'nullable|in:shs,college',
            'course'           => 'nullable|string|max:100',
            'year_level'       => 'nullable|string|max:5',
            'strand'           => 'nullable|string|max:20',
            'semester'         => 'nullable|in:1st,2nd,summer',
            'prerequisite_ids' => 'nullable|array',
            'prerequisite_ids.*' => 'distinct|integer|exists:subjects,id',
        ]);

        $data = $request->only([
            'code', 'name', 'description',
            'units_lec', 'units_lab',
            'program_type', 'course', 'year_level', 'strand', 'semester',
            'is_active',
        ]);
        $data['course'] = $data['course'] === '' ? null : $data['course'];
        $data['strand'] = $data['strand'] === '' ? null : $data['strand'];

        $subject->update($data);

        if ($request->filled('prerequisite_ids')) {
            $prerequisiteIds = array_filter((array) $request->prerequisite_ids, fn($idValue) => (int) $idValue !== (int) $subject->id);
            $subject->prerequisites()->sync($prerequisiteIds);
        }

        return response()->json($subject->load('prerequisites:id,code,name'));
    }

    // DELETE /api/subjects/{id}
    public function subjectDestroy(Request $request, $id)
    {
        $this->authorizeRegistrar($request);
        Subject::findOrFail($id)->delete();
        return response()->json(['message' => 'Subject deleted.']);
    }

    // GET /api/subjects/{id}/prerequisites
    public function subjectPrerequisites(Request $request, $id)
    {
        $this->authorizeRegistrar($request);
        $subject = Subject::with('prerequisites:id,code,name')->findOrFail($id);
        return response()->json($subject->prerequisites);
    }

    // POST /api/subjects/{id}/prerequisites
    public function addSubjectPrerequisite(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $request->validate([
            'prerequisite_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        if ($request->prerequisite_id == $id) {
            return response()->json(['message' => 'A subject cannot be its own prerequisite.'], 422);
        }

        $subject = Subject::findOrFail($id);
        $subject->prerequisites()->syncWithoutDetaching([$request->prerequisite_id]);

        return response()->json($subject->prerequisites()->get(['id', 'code', 'name']), 201);
    }

    // DELETE /api/subjects/{id}/prerequisites/{prerequisiteId}
    public function removeSubjectPrerequisite(Request $request, $id, $prerequisiteId)
    {
        $this->authorizeRegistrar($request);

        $subject = Subject::findOrFail($id);
        $subject->prerequisites()->detach($prerequisiteId);

        return response()->json(['message' => 'Prerequisite removed.']);
    }

    // ── SECTIONS ─────────────────────────────────────────────

    // GET /api/registrar/teachers
    public function teacherIndex(Request $request)
    {
        $this->authorizeRegistrar($request);

        $teachers = User::query()
            ->where('role', 'teacher')
            ->orWhereHas('roles', fn($roleQuery) => $roleQuery->where('name', 'teacher'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($teachers);
    }

    // GET /api/registrar/section-students
    public function studentIndex(Request $request)
    {
        $this->authorizeRegistrar($request);

        $students = Student::query()
            ->with('user:id,name,email')
            ->where('status', 'active')
            ->when($request->grade_level, fn($q) => $q->where('grade_level', $request->grade_level))
            ->when($request->school_year, fn($q) => $q->where('school_year', $request->school_year))
            ->when($request->program_type || $request->course || $request->strand, function ($q) use ($request) {
                $approvedUserIds = EnrollmentApplication::query()
                    ->where('status', 'approved')
                    ->pluck('user_id')
                    ->filter()
                    ->unique()
                    ->values();

                $matchingUserIds = EnrollmentApplication::query()
                    ->where('status', 'approved')
                    ->when($request->program_type, fn($appQuery) => $appQuery->where('program_type', $request->program_type))
                    ->when($request->program_type === 'college' && $request->grade_level, fn($appQuery) => $appQuery->where('year_level', $request->grade_level))
                    ->when($request->program_type === 'shs' && $request->grade_level, fn($appQuery) => $appQuery->where('grade_level', $request->grade_level))
                    ->when($request->course, function ($appQuery) use ($request) {
                        $appQuery->where(function ($courseQuery) use ($request) {
                            $courseQuery->where('course', $request->course)
                                ->orWhere('course', 'like', "%{$request->course}%");
                        });
                    })
                    ->when($request->strand, fn($appQuery) => $appQuery->where('strand', $request->strand))
                    ->pluck('user_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($matchingUserIds->isNotEmpty()) {
                    $q->where(function ($studentQuery) use ($matchingUserIds, $approvedUserIds) {
                        $studentQuery->whereIn('user_id', $matchingUserIds)
                            ->orWhereNotIn('user_id', $approvedUserIds);
                    });
                }
            })
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($q2) use ($search) {
                    $q2->where('student_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn($student) => [
                'id'           => $student->id,
                'user_id'      => $student->user_id,
                'student_id'   => $student->student_id,
                'name'         => trim("{$student->first_name} {$student->last_name}") ?: $student->user?->name,
                'email'        => $student->email ?: $student->user?->email,
                'grade_level'  => $student->grade_level,
                'section'      => $student->section,
                'school_year'  => $student->school_year,
            ]);

        return response()->json($students);
    }

    // GET /api/sections
    public function sectionIndex(Request $request)
    {
        $sections = Section::with(['sectionSubjects.subject', 'sectionSubjects.teacher'])
            ->when($request->school_year, fn($q) => $q->where('school_year', $request->school_year))
            ->when($request->semester,    fn($q) => $q->where('semester', $request->semester))
            ->when($request->course,      fn($q) => $q->where('course', $request->course))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($section) {
                return array_merge($section->toArray(), [
                    'student_count' => $section->students()->wherePivot('status', 'enrolled')->count(),
                ]);
            });

        return response()->json($sections);
    }

    // POST /api/sections
    public function sectionStore(Request $request)
    {
        $this->authorizeRegistrar($request);

        $request->validate([
            'name'         => 'required|string|max:100',
            'course'       => 'nullable|string|max:100',
            'year_level'   => 'nullable|string|max:5',
            'program_type' => 'required|in:shs,college',
            'strand'       => 'nullable|string|max:20',
            'school_year'  => 'required|string|max:20',
            'semester'     => 'required|in:1st,2nd,summer',
            'max_students' => 'nullable|integer|min:1',
        ]);

        $section = Section::create($request->all());
        return response()->json($section, 201);
    }

    // GET /api/sections/{id}
    public function sectionShow(Request $request, $id)
    {
        $section = Section::with([
            'sectionSubjects.subject',
            'sectionSubjects.teacher',
            'students',
        ])->findOrFail($id);

        return response()->json($section);
    }

    // PUT /api/sections/{id}
    public function sectionUpdate(Request $request, $id)
    {
        $this->authorizeRegistrar($request);
        $section = Section::findOrFail($id);
        $section->update($request->all());
        return response()->json($section);
    }

    // POST /api/sections/{id}/subjects — assign subject to section
    public function assignSubject(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'nullable|exists:users,id',
            'day'        => 'nullable|string|max:20',
            'time_start' => 'nullable|string|max:10',
            'time_end'   => 'nullable|string|max:10',
            'room'       => 'nullable|string|max:50',
        ]);

        $sectionSubject = SectionSubject::updateOrCreate(
            ['section_id' => $id, 'subject_id' => $request->subject_id],
            $request->only(['teacher_id', 'day', 'time_start', 'time_end', 'room'])
        );

        return response()->json($sectionSubject->load('subject', 'teacher'), 201);
    }

    // DELETE /api/sections/{id}/subjects/{subjectId} — remove subject from section
    public function removeSubject(Request $request, $id, $subjectId)
    {
        $this->authorizeRegistrar($request);
        SectionSubject::where('section_id', $id)
                      ->where('subject_id', $subjectId)
                      ->delete();
        return response()->json(['message' => 'Subject removed from section.']);
    }

    // POST /api/sections/{id}/students — enroll student in section
    public function enrollStudent(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $section = Section::findOrFail($id);
        if ($section->students()->wherePivot('status', 'enrolled')->count() >= $section->max_students) {
            return response()->json(['message' => 'This section has reached its maximum students.'], 422);
        }

        $studentUser = User::findOrFail($request->user_id);
        if ($studentUser->role !== 'student' && !$studentUser->hasRole('student')) {
            return response()->json(['message' => 'Only student accounts can be enrolled in a section.'], 422);
        }

        $subjectIds = $section->sectionSubjects()->pluck('subject_id')->toArray();

        if (!empty($subjectIds)) {
            $requiredPrereqIds = Subject::whereIn('id', $subjectIds)
                ->with('prerequisites:id')
                ->get()
                ->flatMap(fn($subject) => $subject->prerequisites->pluck('id'))
                ->unique()
                ->values()
                ->all();

            if (!empty($requiredPrereqIds)) {
                $completedSubjectIds = SectionSubject::query()
                    ->whereHas('section', fn($q) =>
                        $q->whereHas('students', fn($q2) =>
                            $q2->where('user_id', $request->user_id)
                               ->where('status', 'completed')
                        )
                    )
                    ->pluck('subject_id')
                    ->unique()
                    ->toArray();

                $missing = array_diff($requiredPrereqIds, $completedSubjectIds);
                if (!empty($missing)) {
                    $missingSubjects = Subject::whereIn('id', $missing)
                        ->orderBy('code')
                        ->get(['id', 'code', 'name']);

                    return response()->json([
                        'message' => 'Student cannot enroll because prerequisite subjects are not completed.',
                        'missing_prerequisites' => $missingSubjects,
                    ], 422);
                }
            }
        }

        $section->students()->syncWithoutDetaching([
            $request->user_id => ['status' => 'enrolled']
        ]);

        Student::where('user_id', $request->user_id)->update(['section' => $section->name]);

        return response()->json(['message' => 'Student enrolled in section.']);
    }

    // DELETE /api/sections/{id}/students/{userId} — remove student from section
    public function removeStudent(Request $request, $id, $userId)
    {
        $this->authorizeRegistrar($request);
        $section = Section::findOrFail($id);
        $section->students()->detach($userId);
        Student::where('user_id', $userId)->update(['section' => 'TBA']);
        return response()->json(['message' => 'Student removed from section.']);
    }

    // ── STUDENT VIEW ─────────────────────────────────────────

    // GET /api/my-subjects — student sees their enrolled subjects
    public function mySubjects(Request $request)
    {
        $user = $request->user();

        $sections = Section::whereHas('students', fn($q) =>
                        $q->where('user_id', $user->id)->where('status', 'enrolled')
                    )
                    ->with(['sectionSubjects' => fn($q) =>
                        $q->with(['subject', 'teacher:id,name'])
                    ])
                    ->get();

        $subjects = $sections->flatMap(fn($section) =>
            $section->sectionSubjects->map(fn($ss) => [
                'section_id'   => $section->id,
                'section_name' => $section->name,
                'subject_id'   => $ss->subject->id,
                'code'         => $ss->subject->code,
                'name'         => $ss->subject->name,
                'units_lec'    => $ss->subject->units_lec,
                'units_lab'    => $ss->subject->units_lab,
                'day'          => $ss->day,
                'time_start'   => $ss->time_start,
                'time_end'     => $ss->time_end,
                'room'         => $ss->room,
                'teacher'      => $ss->teacher?->name,
            ])
        );

        $sectionSubjectIds = $subjects->pluck('subject_id')->unique()->all();

        $application = EnrollmentApplication::where('user_id', $user->id)
            ->where('status', 'approved')
            ->latest()
            ->first();

        $selectedSubjectIds = collect($application?->subject_ids ?? [])
            ->map(fn($id) => (int) $id)
            ->filter()
            ->diff($sectionSubjectIds)
            ->values()
            ->all();

        if (!empty($selectedSubjectIds)) {
            $directSubjects = Subject::whereIn('id', $selectedSubjectIds)
                ->orderBy('code')
                ->get()
                ->map(fn($subject) => [
                    'section_id'   => null,
                    'section_name' => 'Direct enrollment',
                    'subject_id'   => $subject->id,
                    'code'         => $subject->code,
                    'name'         => $subject->name,
                    'units_lec'    => $subject->units_lec,
                    'units_lab'    => $subject->units_lab,
                    'day'          => null,
                    'time_start'   => null,
                    'time_end'     => null,
                    'room'         => null,
                    'teacher'      => null,
                ]);

            $subjects = $subjects->concat($directSubjects);
        }

        return response()->json([
            'sections' => $sections->map(fn($s) => ['id' => $s->id, 'name' => $s->name]),
            'subjects' => $subjects->values(),
        ]);
    }

    // ── TEACHER VIEW ─────────────────────────────────────────

    // GET /api/my-classes — teacher sees their assigned sections & subjects
    public function myClasses(Request $request)
    {
        $user = $request->user();

        $sectionSubjects = SectionSubject::where('teacher_id', $user->id)
            ->with(['section', 'subject'])
            ->get()
            ->groupBy('section_id')
            ->map(fn($items) => [
                'section'  => $items->first()->section,
                'subjects' => $items->map(fn($ss) => [
                    'section_subject_id' => $ss->id,
                    'subject_id'         => $ss->subject->id,
                    'code'               => $ss->subject->code,
                    'name'               => $ss->subject->name,
                    'units_lec'          => $ss->subject->units_lec,
                    'units_lab'          => $ss->subject->units_lab,
                    'day'                => $ss->day,
                    'time_start'         => $ss->time_start,
                    'time_end'           => $ss->time_end,
                    'room'               => $ss->room,
                ]),
            ])->values();

        return response()->json($sectionSubjects);
    }

    // ── Helper ───────────────────────────────────────────────
    private function authorizeRegistrar(Request $request)
    {
        $user = $request->user();
        if (!$user || (!in_array($user->role, ['registrar', 'admin']) && !$user->hasAnyRole(['registrar', 'admin']))) {
            abort(403, 'Unauthorized.');
        }
    }
}
