@extends('layouts.portal', ['title' => 'Enrollment Review'])

@section('content')
<a href="{{ route('registrar.enrollments.index') }}" class="text-sm text-emerald-700 font-bold hover:underline">Back to enrollments</a>

<div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 mt-4">
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900">{{ $application->full_name }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ $application->email }} · {{ $application->contact_number }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $application->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($application->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">{{ ucfirst($application->status) }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-xs font-black uppercase text-slate-400">Academic</p>
                <dl class="mt-2 space-y-2">
                    <div><dt class="text-slate-500">Program</dt><dd class="font-bold text-slate-800">{{ strtoupper($application->program_type) }} · {{ $application->course ?: $application->strand }}</dd></div>
                    <div><dt class="text-slate-500">Level</dt><dd class="font-bold text-slate-800">{{ $application->year_level ?: $application->grade_level }}</dd></div>
                    <div><dt class="text-slate-500">Semester</dt><dd class="font-bold text-slate-800">{{ $application->school_year }} · {{ ucfirst($application->semester) }}</dd></div>
                    <div><dt class="text-slate-500">Status</dt><dd class="font-bold text-slate-800">{{ $application->academic_status }}</dd></div>
                </dl>
            </div>
            <div>
                <p class="text-xs font-black uppercase text-slate-400">Profile</p>
                <dl class="mt-2 space-y-2">
                    <div><dt class="text-slate-500">Birthdate</dt><dd class="font-bold text-slate-800">{{ optional($application->birthdate)->format('M d, Y') ?: '-' }}</dd></div>
                    <div><dt class="text-slate-500">Gender</dt><dd class="font-bold text-slate-800">{{ ucfirst($application->gender) }}</dd></div>
                    <div><dt class="text-slate-500">Address</dt><dd class="font-bold text-slate-800">{{ $application->address }}</dd></div>
                    <div><dt class="text-slate-500">Previous school</dt><dd class="font-bold text-slate-800">{{ $application->prev_school ?: '-' }}</dd></div>
                </dl>
            </div>
        </div>
    </section>

    <aside class="space-y-4">
        @if($application->status === 'pending')
            <form method="POST" action="{{ route('registrar.enrollments.approve', $application) }}" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                @csrf
                <h2 class="font-black text-slate-800 mb-2">Approve Application</h2>
                <p class="text-sm text-slate-500 mb-4">Creates or updates the student login and student record.</p>
                <button class="w-full rounded-lg bg-emerald-700 text-white py-2 text-sm font-bold">Approve enrollment</button>
            </form>

            <form method="POST" action="{{ route('registrar.enrollments.reject', $application) }}" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                @csrf
                <h2 class="font-black text-slate-800 mb-2">Reject Application</h2>
                <textarea name="remarks" rows="4" placeholder="Reason for rejection" class="w-full rounded-lg border-slate-300 text-sm mb-3" required></textarea>
                <button class="w-full rounded-lg bg-red-600 text-white py-2 text-sm font-bold">Reject enrollment</button>
            </form>
        @endif

        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h2 class="font-black text-slate-800 mb-3">Review Details</h2>
            <p class="text-sm text-slate-500">Reviewed by: <span class="font-bold text-slate-800">{{ $application->reviewer?->name ?: '-' }}</span></p>
            <p class="text-sm text-slate-500">Reviewed at: <span class="font-bold text-slate-800">{{ optional($application->reviewed_at)->format('M d, Y g:i A') ?: '-' }}</span></p>
            @if($application->remarks)
                <p class="mt-3 text-sm text-slate-700">{{ $application->remarks }}</p>
            @endif
        </section>
    </aside>
</div>
@endsection
