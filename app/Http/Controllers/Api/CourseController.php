<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::query()->where('is_active', true);

        if ($request->filled('program_type')) {
            $query->where('program_type', $request->program_type);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $this->authorizeRegistrar($request);

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'program_type' => 'required|in:shs,college',
            'is_active'    => 'nullable|boolean',
        ]);

        $course = Course::create([
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'program_type' => $data['program_type'],
            'is_active'    => $data['is_active'] ?? true,
        ]);

        return response()->json($course, 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $course = Course::findOrFail($id);
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'program_type' => 'required|in:shs,college',
            'is_active'    => 'nullable|boolean',
        ]);

        $course->update([
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'program_type' => $data['program_type'],
            'is_active'    => $data['is_active'] ?? true,
        ]);

        return response()->json($course);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorizeRegistrar($request);

        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json(['message' => 'Course deleted successfully.']);
    }

    private function authorizeRegistrar(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['registrar', 'admin'])) {
            abort(403, 'Unauthorized.');
        }
    }
}
