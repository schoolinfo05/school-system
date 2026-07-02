@extends('layouts.portal', ['title' => $student->first_name . ' ' . $student->last_name])

@section('content')
<a href="{{ route('admin.students.index') }}" class="text-sm text-blue-700 font-bold hover:underline">Back to students</a>

<section class="mt-4 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <div class="flex flex-col md:flex-row md:items-center gap-5 justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-900">{{ $student->first_name }} {{ $student->last_name }}</h1>
            <p class="text-sm text-slate-500 mt-1">{{ $student->student_id }} · {{ $student->email }}</p>
            <p class="text-sm text-slate-500">{{ $student->grade_level }} · {{ $student->section ?: 'TBA' }} · {{ $student->school_year }}</p>
        </div>
        <div class="grid grid-cols-2 gap-3 min-w-72">
            <div class="rounded-lg bg-emerald-50 p-4">
                <p class="text-xs font-bold text-emerald-700 uppercase">Attendance</p>
                <p class="text-2xl font-black text-emerald-800">{{ $attendancePct }}%</p>
            </div>
            <div class="rounded-lg bg-blue-50 p-4">
                <p class="text-xs font-bold text-blue-700 uppercase">Status</p>
                <p class="text-lg font-black text-blue-800">{{ ucfirst($student->status) }}</p>
            </div>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-black text-slate-800 mb-4">Grades</h2>
        @forelse($grades as $quarter => $quarterGrades)
            <div class="mb-5">
                <p class="text-xs font-black uppercase text-slate-400 mb-2">Quarter {{ $quarter }}</p>
                <div class="divide-y divide-slate-100">
                    @foreach($quarterGrades as $grade)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $grade->schoolClass?->subject ?: 'Subject' }}</p>
                                <p class="text-xs text-slate-500">{{ $grade->remarks }}</p>
                            </div>
                            <p class="text-xl font-black {{ $grade->score >= 90 ? 'text-emerald-700' : ($grade->score >= 75 ? 'text-blue-700' : 'text-red-600') }}">{{ $grade->score }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">No grades recorded.</p>
        @endforelse
    </section>

    <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h2 class="font-black text-slate-800 mb-4">Fees</h2>
        <div class="divide-y divide-slate-100">
            @forelse($fees as $fee)
                <div class="py-3 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-slate-800">{{ $fee->type }}</p>
                        <p class="text-xs text-slate-500">Due {{ $fee->due_date }} · {{ ucfirst($fee->status) }}</p>
                    </div>
                    <p class="font-black text-slate-900">PHP {{ number_format($fee->amount) }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">No fees recorded.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
