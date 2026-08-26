<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Api\EnrollmentController as ApiEnrollmentController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use App\Models\EnrollmentApplication;
use App\Models\Subject;
use Illuminate\Http\Request;

class EnrollmentReviewController extends Controller
{
    use AuthorizesPortal;

    public function index(Request $request)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $applications = EnrollmentApplication::query()
            ->with(['course', 'reviewer:id,name'])
            ->when($request->status, fn ($query) => $query->where('status', $request->status))
            ->when($request->search, function ($query) use ($request) {
                $search = trim($request->search);
                $query->where(fn ($inner) => $inner
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id_no', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('registrar.enrollments.index', compact('applications'));
    }

    public function show(Request $request, EnrollmentApplication $enrollment)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $application = $enrollment->load(['course', 'reviewer:id,name']);
        $selectedSubjects = Subject::query()
            ->whereIn('id', collect($application->subject_ids ?? [])->map(fn ($id) => (int) $id)->filter())
            ->orderBy('code')
            ->get();

        return view('registrar.enrollments.show', compact('application', 'selectedSubjects'));
    }

    public function approve(Request $request, EnrollmentApplication $enrollment, ApiEnrollmentController $controller)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $controller->approve($request, $enrollment->id);

        return redirect()->route('registrar.enrollments.show', $enrollment)->with('status', 'Enrollment approved.');
    }

    public function reject(Request $request, EnrollmentApplication $enrollment, ApiEnrollmentController $controller)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);
        $request->validate(['remarks' => ['required', 'string', 'max:500']]);

        $controller->reject($request, $enrollment->id);

        return redirect()->route('registrar.enrollments.show', $enrollment)->with('status', 'Enrollment rejected.');
    }
}
