@extends('layouts.portal', ['title' => 'Classes'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Classes</h1>
    <p class="mt-1 text-sm text-slate-500">Open class rosters, attendance, and grade entry.</p>
</div>

<section class="portal-card overflow-hidden">
    <div class="divide-y divide-slate-100">
        @forelse($classes as $class)
            <div class="grid grid-cols-1 gap-3 p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-lg font-black text-slate-900">{{ $class->subject }}</p>
                    <p class="mt-1 text-sm text-slate-500">Grade {{ $class->grade_level }} - {{ $class->section }} · {{ $class->school_year }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-400">{{ $class->room ?: 'No room' }} · {{ $class->schedule ?: 'No schedule' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('teacher.class', $class) }}" class="portal-button-secondary">Students</a>
                    <a href="{{ route('teacher.grades', $class) }}" class="portal-button-secondary">Grades</a>
                    <a href="{{ route('teacher.attendance', $class) }}" class="portal-button-primary">Attendance</a>
                </div>
            </div>
        @empty
            <p class="p-10 text-center text-sm text-slate-500">No classes assigned yet.</p>
        @endforelse
    </div>
</section>
@endsection
