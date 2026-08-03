<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\SchoolClass;
use App\Models\SchoolNotification;
use App\Models\SectionSubject;
use App\Models\Student;
use App\Models\StudentSubject;
use App\Models\StudentReward;
use App\Models\Grade;
use App\Models\Attendance;
use App\Services\PointsService;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function dashboard(Request $request)
    {
        $teacher = $request->user();
        $sectionSubjects = SectionSubject::where('teacher_id', $teacher->id)
            ->with(['section.students', 'subject'])
            ->get();
        $classes = $sectionSubjects->map(fn($sectionSubject) => $this->sectionSubjectClassPayload($sectionSubject));

        $totalStudents = $sectionSubjects
            ->flatMap(fn($sectionSubject) => $sectionSubject->section?->students ?? collect())
            ->pluck('id')
            ->unique()
            ->count();

        $schoolClassIds = $sectionSubjects
            ->map(fn($sectionSubject) => $this->schoolClassForSectionSubject($sectionSubject)->id)
            ->values();

        $todayAttendance = Attendance::whereIn('school_class_id', $schoolClassIds)
            ->whereDate('date', today())
            ->count();

        $recentGrades = Grade::whereIn('school_class_id', $schoolClassIds)
            ->with(['student', 'schoolClass'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'teacher'          => $teacher->name,
            'total_classes'    => $classes->count(),
            'total_students'   => $totalStudents,
            'today_attendance' => $todayAttendance,
            'recent_grades'    => $recentGrades,
            'classes'          => $classes,
        ]);
    }

    public function classes(Request $request)
    {
        $classes = SectionSubject::where('teacher_id', $request->user()->id)
            ->with(['section.students', 'subject'])
            ->get()
            ->map(fn($sectionSubject) => $this->sectionSubjectClassPayload($sectionSubject));

        return response()->json($classes);
    }

    public function classStudents(Request $request, $class)
    {
        $sectionSubject = $this->teacherSectionSubject($request, $class);
        $schoolClass = $this->schoolClassForSectionSubject($sectionSubject);
        $userIds = $this->enrolledUserIdsForSectionSubject($sectionSubject);

        $students = Student::whereIn('user_id', $userIds)
            ->orderBy('last_name')
            ->get()
            ->map(function (Student $student) {
                $student->reward_points = (int) StudentReward::where('student_id', $student->id)->sum('points');
                return $student;
            });

        $grades = Grade::where('school_class_id', $schoolClass->id)
            ->get()
            ->groupBy('student_id');

        return response()->json([
            'class'    => $this->sectionSubjectClassPayload($sectionSubject),
            'students' => $students,
            'grades'   => $grades,
        ]);
    }

    public function saveGrades(Request $request, $class, PointsService $points)
    {
        $sectionSubject = $this->teacherSectionSubject($request, $class);
        $schoolClass = $this->schoolClassForSectionSubject($sectionSubject);

        $request->validate([
            'grades'              => 'required|array',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.quarter'    => 'required|in:1,2,3,4',
            'grades.*.score'      => 'required|numeric|min:0|max:100',
        ]);

        if ($message = $this->gradeDeadlineMessage($schoolClass->school_year, $sectionSubject->section?->semester)) {
            return response()->json(['message' => $message], 422);
        }

        foreach ($request->grades as $g) {
            $remarks = $this->getRemark($g['score']);
            $grade = Grade::updateOrCreate(
                [
                    'student_id'      => $g['student_id'],
                    'school_class_id' => $schoolClass->id,
                    'quarter'         => $g['quarter'],
                    'school_year'     => $schoolClass->school_year,
                ],
                ['score' => $g['score'], 'remarks' => $remarks]
            );

            $student = Student::find($g['student_id']);
            if ($student) {
                $points->awardGradePoints(
                    $student,
                    (float) $g['score'],
                    "grade:{$student->id}:{$schoolClass->id}:{$g['quarter']}:{$schoolClass->school_year}",
                    $request->user(),
                    $schoolClass->school_year,
                    $sectionSubject->section?->semester,
                    ['grade_id' => $grade->id, 'school_class_id' => $schoolClass->id, 'quarter' => $g['quarter']]
                );
                $this->notifyParentGradePosted($student, $grade);
            }
        }

        return response()->json(['message' => 'Grades saved successfully!']);
    }

    public function saveAttendance(Request $request, $class, PointsService $points)
    {
        $sectionSubject = $this->teacherSectionSubject($request, $class);
        $schoolClass = $this->schoolClassForSectionSubject($sectionSubject);

        $request->validate([
            'date'                       => 'required|date',
            'attendance'                 => 'required|array',
            'attendance.*.student_id'    => 'required|exists:students,id',
            'attendance.*.status'        => 'required|in:present,absent,late,excused',
        ]);

        foreach ($request->attendance as $a) {
            $attendance = Attendance::updateOrCreate(
                [
                    'student_id'      => $a['student_id'],
                    'school_class_id' => $schoolClass->id,
                    'date'            => $request->date,
                ],
                ['status' => $a['status']]
            );

            $student = Student::find($a['student_id']);
            if ($student) {
                $points->awardDailyAttendancePoints(
                    $student,
                    $a['status'],
                    "attendance-daily:{$student->id}:{$schoolClass->id}:{$request->date}",
                    $request->user(),
                    $schoolClass->school_year,
                    $sectionSubject->section?->semester,
                    ['attendance_id' => $attendance->id, 'school_class_id' => $schoolClass->id, 'date' => $request->date]
                );
                $points->syncMonthlyPerfectAttendance(
                    $student,
                    $request->date,
                    $request->user(),
                    $schoolClass->school_year,
                    $sectionSubject->section?->semester
                );
                $this->notifyParentAttendance($student, $attendance);
            }
        }

        return response()->json(['message' => 'Attendance saved!']);
    }

    public function getAttendance(Request $request, $class)
    {
        $sectionSubject = $this->teacherSectionSubject($request, $class);
        $schoolClass = $this->schoolClassForSectionSubject($sectionSubject);
        $date = $request->date ?? today()->toDateString();

        $attendance = Attendance::where('school_class_id', $schoolClass->id)
            ->whereDate('date', $date)
            ->with('student')
            ->get();

        return response()->json([
            'date'       => $date,
            'attendance' => $attendance,
        ]);
    }

    public function classPerformance(Request $request, $class)
    {
        $sectionSubject = $this->teacherSectionSubject($request, $class);
        $schoolClass = $this->schoolClassForSectionSubject($sectionSubject);
        $userIds = $this->enrolledUserIdsForSectionSubject($sectionSubject);

        $students = Student::whereIn('user_id', $userIds)->orderBy('last_name')->get();

        $performance = $students->map(function ($student) use ($schoolClass) {
            $grades = Grade::where('student_id', $student->id)
                ->where('school_class_id', $schoolClass->id)
                ->get();

            $avg = $grades->count() > 0
                ? round($grades->avg('score'), 1)
                : null;

            $attendance = Attendance::where('student_id', $student->id)
                ->where('school_class_id', $schoolClass->id)
                ->get();

            $attPct = $attendance->count() > 0
                ? round(($attendance->where('status','present')->count() / $attendance->count()) * 100, 1)
                : 0;

            return [
                'student'        => $student,
                'average'        => $avg,
                'attendance_pct' => $attPct,
                'grades'         => $grades,
            ];
        });

        return response()->json([
            'class'       => $this->sectionSubjectClassPayload($sectionSubject),
            'performance' => $performance,
        ]);
    }

    private function teacherSectionSubject(Request $request, $id): SectionSubject
    {
        return SectionSubject::where('teacher_id', $request->user()->id)
            ->with(['section.students', 'subject'])
            ->findOrFail($id);
    }

    private function sectionSubjectClassPayload(SectionSubject $sectionSubject): array
    {
        $section = $sectionSubject->section;
        $subject = $sectionSubject->subject;

        return [
            'id' => $sectionSubject->id,
            'subject' => trim(($subject?->code ? "{$subject->code} - " : '') . ($subject?->name ?? 'Subject')),
            'section' => $section?->name,
            'section_id' => $section?->id,
            'grade_level' => $section?->year_level,
            'course' => $section?->course,
            'strand' => $section?->strand,
            'school_year' => $section?->school_year,
            'semester' => $section?->semester,
            'room' => $sectionSubject->room,
            'schedule' => trim(implode(' ', array_filter([
                $sectionSubject->day,
                ($sectionSubject->time_start && $sectionSubject->time_end)
                    ? "{$sectionSubject->time_start}-{$sectionSubject->time_end}"
                    : null,
            ]))) ?: 'No schedule',
            'students_count' => $this->enrolledUserIdsForSectionSubject($sectionSubject)->count(),
        ];
    }

    private function enrolledUserIdsForSectionSubject(SectionSubject $sectionSubject)
    {
        $userIds = $sectionSubject->section->students()
            ->wherePivot('status', 'enrolled')
            ->pluck('users.id');

        $droppedUserIds = StudentSubject::query()
            ->where('section_id', $sectionSubject->section_id)
            ->where('subject_id', $sectionSubject->subject_id)
            ->where('status', 'dropped')
            ->pluck('user_id');

        return $userIds->diff($droppedUserIds)->values();
    }

    private function schoolClassForSectionSubject(SectionSubject $sectionSubject): SchoolClass
    {
        $section = $sectionSubject->section;
        $subject = $sectionSubject->subject;

        $schoolClass = SchoolClass::where('name', "{$section?->name} - {$subject?->code}")
            ->where('subject', $subject?->name ?? 'Subject')
            ->where('grade_level', $section?->year_level ?? '')
            ->where('section', $section?->name ?? '')
            ->where('school_year', $section?->school_year ?? '')
            ->first() ?? new SchoolClass();

        $schoolClass->name = "{$section?->name} - {$subject?->code}";
        $schoolClass->subject = $subject?->name ?? 'Subject';
        $schoolClass->grade_level = $section?->year_level ?? '';
        $schoolClass->section = $section?->name ?? '';
        $schoolClass->school_year = $section?->school_year ?? '';
        $schoolClass->teacher_id = $sectionSubject->teacher_id;
        $schoolClass->room = $sectionSubject->room;
        $schoolClass->schedule = trim(implode(' ', array_filter([
            $sectionSubject->day,
            ($sectionSubject->time_start && $sectionSubject->time_end)
                ? "{$sectionSubject->time_start}-{$sectionSubject->time_end}"
                : null,
        ])));
        $schoolClass->save();

        return $schoolClass;
    }

    private function getRemark(float $score): string
    {
        if ($score >= 90) return 'Outstanding';
        if ($score >= 85) return 'Very Satisfactory';
        if ($score >= 80) return 'Satisfactory';
        if ($score >= 75) return 'Fairly Satisfactory';
        return 'Did Not Meet Expectations';
    }

    private function gradeDeadlineMessage(string $schoolYear, ?string $semester): ?string
    {
        if (!$semester) {
            return null;
        }

        $term = AcademicTerm::where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->first();

        if ($term?->grade_finalization_deadline && now()->gt($term->grade_finalization_deadline)) {
            return 'Grade entry is closed for this term. Finalization deadline was ' . $term->grade_finalization_deadline->toDateTimeString() . '.';
        }

        return null;
    }

    private function notifyParentGradePosted(Student $student, Grade $grade): void
    {
        if (!$student->parent_user_id) {
            return;
        }

        SchoolNotification::create([
            'user_id' => $student->parent_user_id,
            'type' => ((float) $grade->score < 75) ? 'low_grade_alert' : 'grade_posted_parent',
            'title' => ((float) $grade->score < 75) ? 'Low grade alert' : 'Grade posted',
            'body' => "{$student->first_name} {$student->last_name} received {$grade->score} for Q{$grade->quarter}.",
            'channels' => ['in_app'],
            'data' => ['student_id' => $student->id, 'grade_id' => $grade->id],
        ]);
    }

    private function notifyParentAttendance(Student $student, Attendance $attendance): void
    {
        if (!$student->parent_user_id || $attendance->status === 'present') {
            return;
        }

        SchoolNotification::create([
            'user_id' => $student->parent_user_id,
            'type' => 'attendance_alert_parent',
            'title' => 'Attendance alert',
            'body' => "{$student->first_name} {$student->last_name} was marked {$attendance->status} on {$attendance->date}.",
            'channels' => ['in_app'],
            'data' => ['student_id' => $student->id, 'attendance_id' => $attendance->id],
        ]);
    }
}
