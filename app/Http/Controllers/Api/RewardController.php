<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentReward;
use App\Services\PointsService;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    public function mine(Request $request, PointsService $points)
    {
        $student = Student::where('user_id', $request->user()->id)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        return response()->json($this->rewardPayload($student, $points));
    }

    public function award(Request $request, Student $student, PointsService $points)
    {
        $this->authorizePointsManager($request, $student);

        $data = $request->validate([
            'source' => ['required', 'in:donations,events,early_enrollment,early_payment,manual_adjustment'],
            'source_key' => ['nullable', 'string', 'max:160'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:20'],
            'reference_no' => ['nullable', 'string', 'max:120'],
        ]);

        if ($data['source'] === 'early_enrollment') {
            $data['points'] = 30;
        }

        if ($data['source'] === 'early_payment' && !in_array((int) $data['points'], [25, 40], true)) {
            return response()->json(['message' => 'Early payment points must be 25 or 40.'], 422);
        }

        if ($data['source'] === 'events' && $data['points'] > 25) {
            return response()->json(['message' => 'Event participation points cannot exceed 25 per event.'], 422);
        }

        $sourceKey = $data['source_key'] ?? implode(':', array_filter([
            $data['source'],
            $student->id,
            $data['reference_no'] ?? null,
            now()->timestamp,
        ]));

        $reward = $points->awardVerifiedPoints($student, [
            ...$data,
            'source_key' => $sourceKey,
            'meta' => ['reference_no' => $data['reference_no'] ?? null],
        ], $request->user());

        return response()->json([
            'message' => 'Verified points recorded.',
            'reward' => $reward->load('awardedBy:id,name'),
            'summary' => $points->summaryFor($student, $data['school_year'] ?? null, $data['semester'] ?? null),
        ], 201);
    }

    private function rewardPayload(Student $student, PointsService $points): array
    {
        return [
            'summary' => $points->summaryFor($student),
            'rewards' => StudentReward::where('student_id', $student->id)
                ->with('awardedBy:id,name')
                ->latest()
                ->limit(100)
                ->get(),
            'rules' => [
                'redemption_rate' => '1 point = PHP 0.50',
                'semester_cap' => PointsService::SEMESTER_CAP,
                'grade_cap' => PointsService::GRADE_CAP,
                'attendance_cap' => PointsService::ATTENDANCE_CAP,
                'event_cap' => PointsService::EVENT_CAP,
                'allowed_sources' => [
                    'grades',
                    'attendance',
                    'donations',
                    'events',
                    'early_enrollment',
                    'early_payment',
                ],
            ],
        ];
    }

    private function authorizePointsManager(Request $request, Student $student): void
    {
        $user = $request->user();

        if (!$user || (
            !in_array($user->role, ['admin', 'registrar'], true)
            && !$user->hasAnyRole(['admin', 'registrar'])
        )) {
            abort(response()->json(['message' => 'Admin or registrar access is required.'], 403));
        }

        if (!$student->user_id) {
            abort(response()->json(['message' => 'Student user account not found.'], 422));
        }
    }
}
