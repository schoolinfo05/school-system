<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherChatController extends Controller
{
    public function contacts(Request $request)
    {
        $user = $request->user();
        $contactIds = $this->contactIdsFor($user);

        $contacts = User::whereIn('id', $contactIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn($contact) => $this->formatContact($user, $contact));

        return response()->json($contacts);
    }

    public function messages(Request $request, User $contact)
    {
        $user = $request->user();
        if (!$this->canChat($user, $contact->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        TeacherMessage::where('sender_id', $contact->id)
            ->where('receiver_id', $user->id)
            ->update(['is_read' => true]);

        $messages = $this->conversationQuery($user->id, $contact->id)
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get();

        return response()->json($messages);
    }

    public function send(Request $request, User $contact)
    {
        $user = $request->user();
        if (!$this->canChat($user, $contact->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $message = TeacherMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $contact->id,
            'message' => $data['message'],
        ]);

        return response()->json($message->load('sender:id,name'), 201);
    }

    private function formatContact(User $user, User $contact): array
    {
        $last = $this->conversationQuery($user->id, $contact->id)
            ->latest()
            ->first();

        $unread = TeacherMessage::where('sender_id', $contact->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'role' => $contact->role,
            'student_info' => $this->studentInfoFor($user, $contact),
            'last_message' => $last?->message,
            'last_message_at' => $last?->created_at,
            'unread' => $unread,
        ];
    }

    private function canChat(User $user, int $contactId): bool
    {
        $contact = User::find($contactId);

        if (!$contact) {
            return false;
        }

        if ($this->hasRole($user, 'student') && !$this->hasRole($contact, 'teacher')) {
            return false;
        }

        if ($this->hasRole($user, 'teacher') && !$this->hasRole($contact, 'student')) {
            return false;
        }

        return in_array($contactId, $this->contactIdsFor($user), true);
    }

    private function conversationQuery(int $userId, int $contactId)
    {
        return TeacherMessage::query()
            ->where(function ($query) use ($userId, $contactId) {
                $query->where('sender_id', $userId)
                    ->where('receiver_id', $contactId);
            })
            ->orWhere(function ($query) use ($userId, $contactId) {
                $query->where('sender_id', $contactId)
                    ->where('receiver_id', $userId);
            });
    }

    private function contactIdsFor(User $user): array
    {
        if ($this->hasRole($user, 'teacher')) {
            return DB::table('section_students')
                ->join('section_subjects', 'section_students.section_id', '=', 'section_subjects.section_id')
                ->where('section_subjects.teacher_id', $user->id)
                ->where('section_students.status', 'enrolled')
                ->pluck('section_students.user_id')
                ->unique()
                ->values()
                ->map(fn($id) => (int) $id)
                ->all();
        }

        if ($this->hasRole($user, 'student')) {
            return DB::table('section_students')
                ->join('section_subjects', 'section_students.section_id', '=', 'section_subjects.section_id')
                ->where('section_students.user_id', $user->id)
                ->where('section_students.status', 'enrolled')
                ->whereNotNull('section_subjects.teacher_id')
                ->pluck('section_subjects.teacher_id')
                ->unique()
                ->values()
                ->map(fn($id) => (int) $id)
                ->all();
        }

        return [];
    }

    private function studentInfoFor(User $user, User $contact): ?array
    {
        if (!$this->hasRole($user, 'teacher') || !$this->hasRole($contact, 'student')) {
            return null;
        }

        $section = DB::table('section_students')
            ->join('section_subjects', 'section_students.section_id', '=', 'section_subjects.section_id')
            ->join('sections', 'sections.id', '=', 'section_students.section_id')
            ->where('section_subjects.teacher_id', $user->id)
            ->where('section_students.user_id', $contact->id)
            ->where('section_students.status', 'enrolled')
            ->select(
                'sections.name',
                'sections.course',
                'sections.year_level',
                'sections.program_type',
                'sections.strand',
                'sections.school_year'
            )
            ->first();

        $profile = DB::table('students')
            ->where('user_id', $contact->id)
            ->select('grade_level', 'section', 'school_year')
            ->first();

        $year = $section?->year_level
            ? 'Year ' . $section->year_level
            : ($profile?->grade_level ? 'Grade ' . $profile->grade_level : null);

        return [
            'section' => $section?->name ?? $profile?->section,
            'year' => $year,
            'program' => $section?->course ?? $section?->strand,
            'school_year' => $section?->school_year ?? $profile?->school_year,
        ];
    }

    private function hasRole(User $user, string $role): bool
    {
        return $user->role === $role || $user->hasRole($role);
    }
}
