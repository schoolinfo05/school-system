<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Api\EnrollmentController as ApiEnrollmentController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use App\Models\EnrollmentApplication;
use App\Models\Subject;
use App\Services\PointsService;
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

    public function approve(Request $request, EnrollmentApplication $enrollment, ApiEnrollmentController $controller, PointsService $points)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        $response = $controller->approve($request, $enrollment->id, $points);

        if ($response->getStatusCode() >= 400) {
            return redirect()
                ->route('registrar.enrollments.show', $enrollment)
                ->with('status', $this->responseMessage($response, 'Enrollment could not be approved.'));
        }

        $parentPassword = $this->responseValue($response, 'parent_default_password');
        $status = $parentPassword
            ? "Enrollment approved. Parent default password: {$parentPassword}"
            : 'Enrollment approved.';

        return redirect()->route('registrar.enrollments.show', $enrollment)->with('status', $status);
    }

    public function approveRequiresPost(Request $request, EnrollmentApplication $enrollment)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        return redirect()
            ->route('registrar.enrollments.show', $enrollment)
            ->with('status', 'Use the approve button to submit this enrollment review.');
    }

    public function reject(Request $request, EnrollmentApplication $enrollment, ApiEnrollmentController $controller)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);
        $request->validate(['remarks' => ['required', 'string', 'max:500']]);

        $response = $controller->reject($request, $enrollment->id);

        if ($response->getStatusCode() >= 400) {
            return redirect()
                ->route('registrar.enrollments.show', $enrollment)
                ->with('status', $this->responseMessage($response, 'Enrollment could not be rejected.'));
        }

        return redirect()->route('registrar.enrollments.show', $enrollment)->with('status', 'Enrollment rejected.');
    }

    public function rejectRequiresPost(Request $request, EnrollmentApplication $enrollment)
    {
        $this->requireAnyRole($request, ['admin', 'registrar']);

        return redirect()
            ->route('registrar.enrollments.show', $enrollment)
            ->with('status', 'Use the reject form to submit this enrollment review.');
    }

    private function responseMessage($response, string $fallback): string
    {
        $content = json_decode($response->getContent(), true);

        return is_array($content) && isset($content['message'])
            ? $content['message']
            : $fallback;
    }

    private function responseValue($response, string $key): ?string
    {
        $content = json_decode($response->getContent(), true);

        return is_array($content) && isset($content[$key])
            ? (string) $content[$key]
            : null;
    }
}
