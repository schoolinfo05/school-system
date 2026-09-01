<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use App\Models\Course;
use App\Models\Subject;
use App\Services\ArchiveService;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    use AuthorizesPortal;

    public function index(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $subjects = Subject::query()
            ->with('prerequisites:id,code,name')
            ->when($request->program_type, fn ($query) => $query->where('program_type', $request->program_type))
            ->when($request->search, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('code', 'like', "%{$request->search}%")
                ->orWhere('name', 'like', "%{$request->search}%")))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();
        $courses = Course::query()
            ->where('is_active', true)
            ->where('program_type', 'college')
            ->orderBy('name')
            ->get();
        $allSubjects = Subject::orderBy('code')->get(['id', 'code', 'name', 'program_type']);

        return view('registrar.subjects.index', compact('subjects', 'courses', 'allSubjects'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $data = $this->validated($request);
        $subject = Subject::create($data);

        if ($request->filled('prerequisite_ids')) {
            $subject->prerequisites()->sync($request->input('prerequisite_ids'));
        }

        return back()->with('status', 'Subject created.');
    }

    public function update(Request $request, Subject $subject)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $subject->update($this->validated($request));

        $prerequisiteIds = $request->input('prerequisite_ids', []);
        $prerequisiteIds = array_filter((array) $prerequisiteIds, fn($id) => (int) $id !== $subject->id);
        $subject->prerequisites()->sync($prerequisiteIds);

        return back()->with('status', 'Subject updated.');
    }

    public function destroy(Request $request, Subject $subject)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        ArchiveService::record($subject, $request->user()?->id, 'web.subjects');
        $subject->sectionSubjects()->delete();
        $subject->prerequisites()->detach();
        $subject->requiredBy()->detach();
        $subject->delete();

        return back()->with('status', 'Subject removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'units_lec' => ['required', 'numeric', 'min:0'],
            'units_lab' => ['required', 'numeric', 'min:0'],
            'program_type' => ['required', 'in:shs,college'],
            'course' => ['nullable', 'string', 'max:100'],
            'year_level' => ['nullable', 'string', 'max:5'],
            'strand' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'in:1st,2nd,summer'],
            'is_active' => ['nullable', 'boolean'],
            'prerequisite_ids' => ['nullable', 'array'],
            'prerequisite_ids.*' => ['distinct', 'integer', 'exists:subjects,id'],
        ]);

        return [
            ...$data,
            'course' => $data['program_type'] === 'college' ? ($data['course'] ?: null) : null,
            'strand' => $data['program_type'] === 'shs' ? ($data['strand'] ?: null) : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
