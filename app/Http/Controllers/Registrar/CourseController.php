<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use App\Models\Course;
use App\Services\ArchiveService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    use AuthorizesPortal;

    public function index(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $courses = Course::query()
            ->when($request->program_type, fn ($query) => $query->where('program_type', $request->program_type))
            ->when($request->search, fn ($query) => $query->where('name', 'like', "%{$request->search}%"))
            ->orderBy('program_type')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('registrar.courses.index', compact('courses'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'program_type' => ['required', 'in:shs,college'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Course::create([...$data, 'is_active' => (bool) ($data['is_active'] ?? false)]);

        return back()->with('status', 'Course created.');
    }

    public function update(Request $request, Course $course)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'program_type' => ['required', 'in:shs,college'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $course->update([...$data, 'is_active' => (bool) ($data['is_active'] ?? false)]);

        return back()->with('status', 'Course updated.');
    }

    public function destroy(Request $request, Course $course)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        ArchiveService::record($course, $request->user()?->id, 'web.courses');
        $course->delete();

        return back()->with('status', 'Course archived and removed.');
    }
}
