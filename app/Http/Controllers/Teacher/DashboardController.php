<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassAssignment;
use App\Models\MarketplaceItem;
use App\Models\SchoolClass;
use App\Models\SectionSubject;
use App\Models\Student;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\TeacherMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index()
    {
        $teacher = auth()->user();

        $classes = SchoolClass::where('teacher_id', $teacher->id)->get();

        $totalStudents = Student::whereIn('section',
            $classes->pluck('section')
        )->where('grade_level', $classes->first()?->grade_level ?? '10')->count();

        $recentGrades = Grade::whereHas('schoolClass', fn($q) =>
            $q->where('teacher_id', $teacher->id)
        )->with(['student', 'schoolClass'])->latest()->take(5)->get();

        $todayAttendance = Attendance::whereHas('schoolClass', fn($q) =>
            $q->where('teacher_id', $teacher->id)
        )->whereDate('date', today())->count();

        return view('teacher.dashboard', compact(
            'classes', 'totalStudents', 'recentGrades', 'todayAttendance'
        ));
    }

    public function classes()
    {
        $classes = $this->teacherClasses();

        return view('teacher.classes', compact('classes'));
    }

    public function assignments()
    {
        $teacher = auth()->user();
        $assignments = ClassAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->with(['sectionSubject.section', 'sectionSubject.subject'])
            ->withCount('submissions')
            ->latest()
            ->get();

        $sectionSubjects = SectionSubject::query()
            ->where('teacher_id', $teacher->id)
            ->with(['section', 'subject'])
            ->orderByDesc('id')
            ->get();

        return view('teacher.assignments', compact('assignments', 'sectionSubjects'));
    }

    public function storeAssignment(Request $request)
    {
        $data = $request->validate([
            'section_subject_id' => ['required', 'integer', Rule::exists('section_subjects', 'id')->where('teacher_id', $request->user()->id)],
            'type' => ['required', Rule::in(['assignment', 'quiz'])],
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'points_possible' => ['required', 'numeric', 'min:1', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'published', 'closed'])],
        ]);

        ClassAssignment::create([
            ...$data,
            'teacher_id' => $request->user()->id,
            'allow_file_upload' => $request->boolean('allow_file_upload'),
        ]);

        return back()->with('status', 'Class work created.');
    }

    public function market()
    {
        $items = MarketplaceItem::query()
            ->with('seller:id,name')
            ->where('approval_status', 'approved')
            ->where('status', 'available')
            ->latest()
            ->paginate(12);

        return view('teacher.market', compact('items'));
    }

    public function chat()
    {
        $teacher = auth()->user();
        $contacts = $this->teacherStudents()
            ->map(fn (Student $student) => $student->user)
            ->filter()
            ->unique('id')
            ->values();

        $messages = TeacherMessage::query()
            ->where('sender_id', $teacher->id)
            ->orWhere('receiver_id', $teacher->id)
            ->with(['sender:id,name', 'receiver:id,name'])
            ->latest()
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        return view('teacher.chat', compact('contacts', 'messages'));
    }

    public function sendChat(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $allowedContactIds = $this->teacherStudents()
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(in_array((int) $data['receiver_id'], $allowedContactIds, true), 403);

        TeacherMessage::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $data['receiver_id'],
            'message' => $data['message'],
        ]);

        return back()->with('status', 'Message sent.');
    }

    public function profile()
    {
        $teacher = auth()->user();
        $classes = $this->teacherClasses();
        $assignmentsCount = ClassAssignment::where('teacher_id', $teacher->id)->count();

        return view('teacher.profile', compact('teacher', 'classes', 'assignmentsCount'));
    }

    public function myClass(SchoolClass $class)
    {
        $students = Student::where('grade_level', $class->grade_level)
            ->where('section', $class->section)
            ->get();

        $grades = Grade::where('school_class_id', $class->id)
            ->with('student')
            ->get()
            ->keyBy('student_id');

        return view('teacher.class', compact('class', 'students', 'grades'));
    }

    private function teacherClasses()
    {
        return SchoolClass::where('teacher_id', auth()->id())
            ->orderBy('grade_level')
            ->orderBy('section')
            ->orderBy('subject')
            ->get();
    }

    private function teacherStudents()
    {
        $classes = $this->teacherClasses();

        if ($classes->isEmpty()) {
            return collect();
        }

        return Student::query()
            ->with('user:id,name,email')
            ->where(function ($query) use ($classes) {
                foreach ($classes as $class) {
                    $query->orWhere(function ($inner) use ($class) {
                        $inner->where('grade_level', $class->grade_level)
                            ->where('section', $class->section);
                    });
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
