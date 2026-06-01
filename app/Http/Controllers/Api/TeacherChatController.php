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

        $messages = TeacherMessage::where(fn($q) =>
                $q->where('sender_id', $user->id)->where('receiver_id', $contact->id)
            )
            ->orWhere(fn($q) =>
                $q->where('sender_id', $contact->id)->where('receiver_id', $user->id)
            )
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
        $last = TeacherMessage::where(fn($q) =>
                $q->where('sender_id', $user->id)->where('receiver_id', $contact->id)
            )
            ->orWhere(fn($q) =>
                $q->where('sender_id', $contact->id)->where('receiver_id', $user->id)
            )
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
            'last_message' => $last?->message,
            'last_message_at' => $last?->created_at,
            'unread' => $unread,
        ];
    }

    private function canChat(User $user, int $contactId): bool
    {
        return in_array($contactId, $this->contactIdsFor($user), true);
    }

    private function contactIdsFor(User $user): array
    {
        if ($this->hasRole($user, 'teacher')) {
            return DB::table('section_students')
                ->join('section_subjects', 'section_students.section_id', '=', 'section_subjects.section_id')
                ->where('section_subjects.teacher_id', $user->id)
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
                ->whereNotNull('section_subjects.teacher_id')
                ->pluck('section_subjects.teacher_id')
                ->unique()
                ->values()
                ->map(fn($id) => (int) $id)
                ->all();
        }

        return [];
    }

    private function hasRole(User $user, string $role): bool
    {
        return $user->role === $role || $user->hasRole($role);
    }
}
