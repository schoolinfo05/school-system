<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\ClassAssignment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolNotification;
use App\Models\SectionSubject;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function teacherIndex(Request $request)
    {
        $this->authorizeFaculty($request->user());

        $assignments = ClassAssignment::query()
            ->where('teacher_id', $request->user()->id)
            ->with(['sectionSubject.section', 'sectionSubject.subject'])
            ->withCount('submissions')
            ->latest()
            ->get();

        return response()->json($assignments);
    }

    public function store(Request $request)
    {
        $this->authorizeFaculty($request->user());

        $data = $request->validate([
            'section_subject_id' => ['required', 'integer', 'exists:section_subjects,id'],
            'type' => ['required', Rule::in(['assignment', 'quiz'])],
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'points_possible' => ['required', 'numeric', 'min:1', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'allow_file_upload' => ['nullable', 'boolean'],
            'questions' => ['nullable', 'array'],
            'questions.*.question' => ['required_with:questions', 'string', 'max:1000'],
            'questions.*.choices' => ['nullable', 'array'],
            'questions.*.answer' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'closed'])],
        ]);

        $sectionSubject = $this->teacherSectionSubject($request, (int) $data['section_subject_id']);

        $assignment = ClassAssignment::create([
            ...$data,
            'section_subject_id' => $sectionSubject->id,
            'teacher_id' => $request->user()->id,
            'status' => $data['status'] ?? 'published',
        ]);

        $this->notifyEnrolledStudents($assignment, 'New class work', "{$assignment->title} is now available.");

        return response()->json($assignment->load(['sectionSubject.section', 'sectionSubject.subject']), 201);
    }

    public function show(Request $request, ClassAssignment $assignment)
    {
        $this->authorizeAssignmentAccess($request, $assignment);

        $assignment->load(['sectionSubject.section', 'sectionSubject.subject', 'submissions.student']);

        return response()->json($assignment);
    }

    public function update(Request $request, ClassAssignment $assignment)
    {
        $this->authorizeTeacherAssignment($request, $assignment);

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['assignment', 'quiz'])],
            'title' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'points_possible' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'allow_file_upload' => ['nullable', 'boolean'],
            'questions' => ['nullable', 'array'],
            'questions.*.question' => ['required_with:questions', 'string', 'max:1000'],
            'questions.*.choices' => ['nullable', 'array'],
            'questions.*.answer' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'closed'])],
        ]);

        $assignment->update($data);

        return response()->json($assignment->fresh()->load(['sectionSubject.section', 'sectionSubject.subject']));
    }

    public function destroy(Request $request, ClassAssignment $assignment)
    {
        $this->authorizeTeacherAssignment($request, $assignment);

        $assignment->delete();

        return response()->json(['message' => 'Class work deleted.']);
    }

    public function studentIndex(Request $request)
    {
        $student = $this->studentFor($request->user());

        $sectionSubjectIds = DB::table('section_students')
            ->join('section_subjects', 'section_students.section_id', '=', 'section_subjects.section_id')
            ->where('section_students.user_id', $request->user()->id)
            ->where('section_students.status', 'enrolled')
            ->pluck('section_subjects.id')
            ->unique()
            ->values();

        $assignments = ClassAssignment::query()
            ->whereIn('section_subject_id', $sectionSubjectIds)
            ->where('status', 'published')
            ->with(['sectionSubject.section', 'sectionSubject.subject', 'teacher:id,name'])
            ->with(['submissions' => fn ($query) => $query->where('student_id', $student->id)])
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->latest('id')
            ->get()
            ->map(function (ClassAssignment $assignment) {
                $submission = $assignment->submissions->first();
                unset($assignment->submissions);
                $assignment->submission = $submission;
                return $assignment;
            });

        return response()->json($assignments);
    }

    public function submit(Request $request, ClassAssignment $assignment)
    {
        if ($assignment->status !== 'published') {
            return response()->json(['message' => 'This class work is not accepting submissions.'], 422);
        }

        $student = $this->studentFor($request->user());
        if (!$this->studentCanAccess($request->user(), $assignment)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'answer_text' => ['nullable', 'string', 'max:10000'],
            'file_url' => ['nullable', 'string', 'max:1000'],
            'answers' => ['nullable', 'array'],
        ]);

        if (blank($data['answer_text'] ?? null) && blank($data['file_url'] ?? null) && empty($data['answers'] ?? [])) {
            return response()->json(['message' => 'Please add an answer before submitting.'], 422);
        }

        $plagiarismScore = $this->similarityScore($assignment, $student, $data['answer_text'] ?? '');
        $autoScore = $assignment->type === 'quiz'
            ? $this->autoScoreQuiz($assignment, $data['answers'] ?? [])
            : null;

        $submission = AssignmentSubmission::updateOrCreate(
            [
                'class_assignment_id' => $assignment->id,
                'student_id' => $student->id,
            ],
            [
                'user_id' => $request->user()->id,
                'answer_text' => $data['answer_text'] ?? null,
                'file_url' => $data['file_url'] ?? null,
                'answers' => $data['answers'] ?? null,
                'score' => $autoScore,
                'plagiarism_score' => $plagiarismScore,
                'status' => $autoScore === null ? 'submitted' : 'graded',
                'submitted_at' => now(),
                'graded_at' => $autoScore === null ? null : now(),
            ]
        );

        SchoolNotification::create([
            'user_id' => $assignment->teacher_id,
            'type' => 'assignment_submitted',
            'title' => 'Submission received',
            'body' => "{$student->first_name} {$student->last_name} submitted {$assignment->title}.",
            'channels' => ['in_app'],
            'data' => ['assignment_id' => $assignment->id, 'submission_id' => $submission->id],
        ]);

        return response()->json($submission->load('assignment.sectionSubject.subject'), 201);
    }

    public function grade(Request $request, ClassAssignment $assignment, AssignmentSubmission $submission)
    {
        $this->authorizeTeacherAssignment($request, $assignment);

        if ((int) $submission->class_assignment_id !== (int) $assignment->id) {
            return response()->json(['message' => 'Submission does not belong to this class work.'], 422);
        }

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:' . max(1, (float) $assignment->points_possible)],
            'feedback' => ['nullable', 'string', 'max:3000'],
            'sync_to_grades' => ['nullable', 'boolean'],
            'quarter' => ['nullable', Rule::in(['1', '2', '3', '4'])],
        ]);

        $submission->update([
            'score' => $data['score'],
            'feedback' => $data['feedback'] ?? null,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        if ($request->boolean('sync_to_grades')) {
            $this->syncSubmissionToGrades($assignment, $submission, $data['quarter'] ?? '1');
        }

        SchoolNotification::create([
            'user_id' => $submission->user_id,
            'type' => 'assignment_graded',
            'title' => 'Class work graded',
            'body' => "{$assignment->title} was graded: {$submission->score}/{$assignment->points_possible}.",
            'channels' => ['in_app'],
            'data' => ['assignment_id' => $assignment->id, 'submission_id' => $submission->id],
        ]);

        return response()->json($submission->fresh()->load('student'));
    }

    private function teacherSectionSubject(Request $request, int $sectionSubjectId): SectionSubject
    {
        return SectionSubject::query()
            ->where('teacher_id', $request->user()->id)
            ->with(['section', 'subject'])
            ->findOrFail($sectionSubjectId);
    }

    private function authorizeTeacherAssignment(Request $request, ClassAssignment $assignment): void
    {
        $this->authorizeFaculty($request->user());
        if ((int) $assignment->teacher_id !== (int) $request->user()->id) {
            abort(response()->json(['message' => 'Unauthorized.'], 403));
        }
    }

    private function authorizeAssignmentAccess(Request $request, ClassAssignment $assignment): void
    {
        if ($this->isFaculty($request->user()) && (int) $assignment->teacher_id === (int) $request->user()->id) {
            return;
        }

        if ($this->studentCanAccess($request->user(), $assignment)) {
            return;
        }

        abort(response()->json(['message' => 'Unauthorized.'], 403));
    }

    private function notifyEnrolledStudents(ClassAssignment $assignment, string $title, string $body): void
    {
        $userIds = DB::table('section_students')
            ->where('section_id', $assignment->sectionSubject?->section_id ?? $assignment->sectionSubject()->value('section_id'))
            ->where('status', 'enrolled')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            SchoolNotification::create([
                'user_id' => $userId,
                'type' => 'assignment_posted',
                'title' => $title,
                'body' => $body,
                'channels' => ['in_app'],
                'data' => ['assignment_id' => $assignment->id],
            ]);
        }
    }

    private function studentCanAccess(User $user, ClassAssignment $assignment): bool
    {
        return DB::table('section_students')
            ->where('section_id', $assignment->sectionSubject?->section_id ?? $assignment->sectionSubject()->value('section_id'))
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->exists();
    }

    private function studentFor(User $user): Student
    {
        return Student::where('user_id', $user->id)->firstOrFail();
    }

    private function authorizeFaculty(?User $user): void
    {
        if (!$user || !$this->isFaculty($user)) {
            abort(response()->json(['message' => 'Faculty access is required.'], 403));
        }
    }

    private function isFaculty(User $user): bool
    {
        return in_array($user->role, User::FACULTY_ROLES, true) || $user->hasAnyRole(User::FACULTY_ROLES);
    }

    private function similarityScore(ClassAssignment $assignment, Student $student, ?string $answerText): ?float
    {
        $answerText = trim((string) $answerText);
        if ($answerText === '') {
            return null;
        }

        $highest = 0.0;
        $others = AssignmentSubmission::where('class_assignment_id', $assignment->id)
            ->where('student_id', '!=', $student->id)
            ->whereNotNull('answer_text')
            ->pluck('answer_text');

        foreach ($others as $otherText) {
            similar_text(strtolower($answerText), strtolower((string) $otherText), $percent);
            $highest = max($highest, (float) $percent);
        }

        return round($highest, 2);
    }

    private function autoScoreQuiz(ClassAssignment $assignment, array $answers): ?float
    {
        $questions = collect($assignment->questions ?? [])->values();
        if ($questions->isEmpty()) {
            return null;
        }

        $correct = 0;
        foreach ($questions as $index => $question) {
            $expected = trim(strtolower((string) ($question['answer'] ?? '')));
            $actual = trim(strtolower((string) ($answers[$index] ?? '')));
            if ($expected !== '' && $actual === $expected) {
                $correct++;
            }
        }

        return round(($correct / max(1, $questions->count())) * (float) $assignment->points_possible, 2);
    }

    private function syncSubmissionToGrades(ClassAssignment $assignment, AssignmentSubmission $submission, string $quarter): void
    {
        $sectionSubject = $assignment->sectionSubject()->with(['section', 'subject'])->first();
        if (!$sectionSubject || $submission->score === null) {
            return;
        }

        $schoolClass = SchoolClass::firstOrCreate(
            [
                'name' => "{$sectionSubject->section?->name} - {$sectionSubject->subject?->code}",
                'subject' => $sectionSubject->subject?->name ?? 'Subject',
                'grade_level' => $sectionSubject->section?->year_level ?? '',
                'section' => $sectionSubject->section?->name ?? '',
                'school_year' => $sectionSubject->section?->school_year ?? '',
            ],
            [
                'teacher_id' => $sectionSubject->teacher_id,
                'room' => $sectionSubject->room,
                'schedule' => trim(implode(' ', array_filter([
                    $sectionSubject->day,
                    ($sectionSubject->time_start && $sectionSubject->time_end)
                        ? "{$sectionSubject->time_start}-{$sectionSubject->time_end}"
                        : null,
                ]))),
            ]
        );

        $scorePercent = min(100, round(((float) $submission->score / max(1, (float) $assignment->points_possible)) * 100, 2));

        Grade::updateOrCreate(
            [
                'student_id' => $submission->student_id,
                'school_class_id' => $schoolClass->id,
                'quarter' => $quarter,
                'school_year' => $schoolClass->school_year,
            ],
            [
                'score' => $scorePercent,
                'remarks' => $scorePercent >= 75 ? 'Passed' : 'Needs Improvement',
            ]
        );
    }
}
