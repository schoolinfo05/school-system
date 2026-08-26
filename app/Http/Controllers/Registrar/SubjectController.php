<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use App\Models\Course;
use App\Models\Subject;
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
        $courses = Course::query()->where('is_active', true)->orderBy('name')->get();

        return view('registrar.subjects.index', compact('subjects', 'courses'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $data = $this->validated($request);
        Subject::create($data);

        return back()->with('status', 'Subject created.');
    }

    public function update(Request $request, Subject $subject)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $subject->update($this->validated($request));

        return back()->with('status', 'Subject updated.');
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
        ]);

        return [
            ...$data,
            'course' => $data['course'] ?: null,
            'strand' => $data['strand'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
