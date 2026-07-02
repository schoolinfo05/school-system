@extends('layouts.portal', ['title' => 'Students'])

@section('content')
<div class="flex items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-black text-slate-900">Students</h1>
        <p class="text-sm text-slate-500 mt-1">Browse student records, academics, attendance, and fees.</p>
    </div>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-3">
    <input name="search" value="{{ request('search') }}" placeholder="Search name or student ID" class="rounded-lg border-slate-300 text-sm">
    <input name="grade_level" value="{{ request('grade_level') }}" placeholder="Grade / year" class="rounded-lg border-slate-300 text-sm">
    <button class="rounded-lg bg-blue-700 text-white px-5 py-2 text-sm font-bold">Filter</button>
</form>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="text-left px-5 py-3">Student</th>
                <th class="text-left px-5 py-3">Program</th>
                <th class="text-left px-5 py-3">School year</th>
                <th class="text-left px-5 py-3">Status</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($students as $student)
                <tr>
                    <td class="px-5 py-4">
                        <p class="font-bold text-slate-800">{{ $student->first_name }} {{ $student->last_name }}</p>
                        <p class="text-xs text-slate-500">{{ $student->student_id }} · {{ $student->email }}</p>
                    </td>
                    <td class="px-5 py-4 text-slate-600">{{ $student->grade_level }} · {{ $student->section ?: 'TBA' }}</td>
                    <td class="px-5 py-4 text-slate-600">{{ $student->school_year }}</td>
                    <td class="px-5 py-4"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $student->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ ucfirst($student->status) }}</span></td>
                    <td class="px-5 py-4 text-right"><a href="{{ route('admin.students.show', $student) }}" class="text-blue-700 font-bold hover:underline">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No students found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-5 py-4 border-t border-slate-100">{{ $students->links() }}</div>
</div>
@endsection
