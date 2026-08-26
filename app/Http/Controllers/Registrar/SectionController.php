<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    use AuthorizesPortal;

    public function index(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $sections = Section::query()
            ->with(['sectionSubjects.subject', 'sectionSubjects.teacher'])
            ->when($request->program_type, fn ($query) => $query->where('program_type', $request->program_type))
            ->when($request->search, fn ($query) => $query->where('name', 'like', "%{$request->search}%"))
            ->orderByDesc('school_year')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        $courses = Course::query()->where('is_active', true)->orderBy('name')->get();

        return view('registrar.sections.index', compact('sections', 'courses'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);
        Section::create($this->validated($request));

        return back()->with('status', 'Section created.');
    }

    public function update(Request $request, Section $section)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);
        $section->update($this->validated($request));

        return back()->with('status', 'Section updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'course' => ['nullable', 'string', 'max:100'],
            'year_level' => ['nullable', 'string', 'max:5'],
            'program_type' => ['required', 'in:shs,college'],
            'strand' => ['nullable', 'string', 'max:20'],
            'school_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'in:1st,2nd,summer'],
            'max_students' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [...$data, 'course' => $data['course'] ?: null, 'strand' => $data['strand'] ?: null, 'is_active' => (bool) ($data['is_active'] ?? false)];
    }
}
