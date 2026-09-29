<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\GradeChangeRequest;
use App\Models\GradeSubmission;
use App\Services\GradeWorkflowService;
use App\Services\PointsService;
use Illuminate\Http\Request;

class GradeApprovalController extends Controller
{
    public function index(Request $request)
    {
        $showApproved = $request->query('view') === 'approved';
        $submissions = GradeSubmission::query()
            ->where('status', $showApproved
                ? GradeSubmission::STATUS_FINALIZED
                : GradeSubmission::STATUS_REGISTRAR_REVIEW)
            ->with(['schoolClass.teacher', 'teacher', 'events.actor'])
            ->orderByDesc($showApproved ? 'finalized_at' : 'submitted_at')
            ->paginate(25)
            ->withQueryString();
        $changeRequests = $showApproved
            ? null
            : GradeChangeRequest::query()
                ->where('status', GradeChangeRequest::STATUS_REGISTRAR_REVIEW)
                ->with(['submission.schoolClass', 'submission.teacher', 'teacher', 'chairReviewer', 'events.actor'])
                ->orderByDesc('created_at')
                ->paginate(25, ['*'], 'change_page')
                ->withQueryString();

        return view('registrar.grades.index', compact('submissions', 'showApproved', 'changeRequests'));
    }

    public function review(Request $request, GradeSubmission $submission, GradeWorkflowService $workflow, PointsService $points)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,return'],
            'note' => ['nullable', 'required_if:decision,return', 'string', 'max:2000'],
        ]);

        $workflow->registrarDecision(
            $request->user(),
            $submission,
            $data['decision'],
            $data['note'] ?? null,
            $points
        );

        return redirect()->route('registrar.grades.index')->with('status', $data['decision'] === 'approve'
            ? 'Grade sheet finalized and published.'
            : 'Grade sheet returned to the Department Chair.');
    }
}
