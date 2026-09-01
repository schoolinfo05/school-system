@extends('layouts.portal', ['title' => $student->first_name . ' ' . $student->last_name])

@section('content')
<a href="{{ route(($routePrefix ?? 'admin') . '.students.index') }}" class="text-sm text-blue-700 font-bold hover:underline">Back to students</a>

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
            <a href="#parent-account" class="portal-button-primary col-span-2">
                {{ $student->parent ? 'Change parent link' : 'Link parent' }}
            </a>
        </div>
    </div>
</section>

<section id="parent-account" class="mt-6 scroll-mt-24 bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <div class="mb-4">
        <h2 class="font-black text-slate-800">Parent Account</h2>
        <p class="mt-1 text-sm text-slate-500">Link this student to an existing parent account by email.</p>
    </div>

    @if($student->parent)
        <div class="mb-4 rounded-lg bg-violet-50 px-4 py-3 text-sm text-violet-800">
            Current parent: <span class="font-bold">{{ $student->parent->name }}</span> · {{ $student->parent->email }}
        </div>
    @endif

    <form method="POST" action="{{ route(($routePrefix ?? 'admin') . '.students.parent.update', $student) }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
        @csrf
        @method('PUT')
        <label class="text-xs font-bold uppercase text-slate-500">
            Parent email
            <input name="parent_email" value="{{ old('parent_email', $student->parent?->email) }}" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900" placeholder="parent@example.com">
        </label>
        <div class="flex items-end gap-2">
            <button class="portal-button-primary">Link parent</button>
            @if($student->parent)
                <button name="parent_email" value="" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600" formnovalidate>Unlink</button>
            @endif
        </div>
    </form>
</section>

<section class="mt-6 bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="font-black text-slate-800">Subjects</h2>
        <p class="text-xs font-black uppercase text-slate-400">{{ count($subjects) }} enrolled</p>
    </div>

    <div class="divide-y divide-slate-100">
        @forelse($subjects as $subject)
            @php
                $statusClass = match ($subject['status']) {
                    'completed' => 'bg-emerald-100 text-emerald-700',
                    'dropped' => 'bg-red-100 text-red-700',
                    default => 'bg-blue-100 text-blue-700',
                };
            @endphp
            <div class="py-3 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <p class="font-bold text-slate-900">
                        {{ $subject['code'] }}{{ $subject['code'] ? ' · ' : '' }}{{ $subject['name'] }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $subject['section_name'] }}
                        @if($subject['teacher'])
                            · {{ $subject['teacher'] }}
                        @endif
                        @if($subject['schedule'])
                            · {{ $subject['schedule'] }}
                        @endif
                    </p>
                    @if($subject['status'] === 'dropped' && $subject['drop_reason'])
                        <p class="mt-1 text-xs font-semibold text-red-600">Dropped: {{ $subject['drop_reason'] }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-xs font-black {{ $statusClass }}">{{ ucfirst($subject['status']) }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">{{ $subject['units'] }}u</span>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">No subjects found for this student.</p>
        @endforelse
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
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-black text-slate-800">Fees</h2>
            <p class="text-xs font-black uppercase text-slate-400">Post tuition</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($fees as $fee)
                @php($remaining = max(0, (float) $fee->amount - (float) $fee->paid_amount))
                <div class="py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800">{{ $fee->type }}</p>
                            <p class="text-xs text-slate-500">
                                Due {{ $fee->due_date }} · {{ ucfirst($fee->status) }} · Paid PHP {{ number_format((float) $fee->paid_amount, 2) }}
                            </p>
                        </div>
                        <p class="font-black text-slate-900">PHP {{ number_format($remaining, 2) }}</p>
                    </div>
                    @if($fee->status !== 'paid')
                        <form method="POST" action="{{ route(($routePrefix ?? 'admin') . '.students.fees.pay', [$student, $fee]) }}" class="mt-3 grid grid-cols-1 gap-2 rounded-lg bg-slate-50 p-3 md:grid-cols-[1fr_140px_1fr_auto]">
                            @csrf
                            <input name="amount" value="{{ old('amount', number_format($remaining, 2, '.', '')) }}" type="number" min="1" step="0.01" class="rounded-lg border-slate-300 text-sm" placeholder="Amount">
                            <select name="payment_method" class="rounded-lg border-slate-300 text-sm">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="qrph">QRPH</option>
                                <option value="bank_transfer">Bank transfer</option>
                            </select>
                            <input name="payment_reference" value="{{ old('payment_reference') }}" class="rounded-lg border-slate-300 text-sm" placeholder="Reference optional">
                            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Confirm</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">No fees recorded.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route(($routePrefix ?? 'admin') . '.students.fees.store', $student) }}" class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
            @csrf
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <label class="text-xs font-bold uppercase text-slate-500">
                    Fee type
                    <input name="type" value="{{ old('type', 'Tuition Fee') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">
                    Amount
                    <input name="amount" value="{{ old('amount') }}" type="number" min="1" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">
                    Due date
                    <input name="due_date" value="{{ old('due_date') }}" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">
                    School year
                    <input name="school_year" value="{{ old('school_year', $student->school_year) }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">
                    Semester
                    <select name="semester" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                        <option value="">No semester</option>
                        <option value="1st" @selected(old('semester') === '1st')>1st semester</option>
                        <option value="2nd" @selected(old('semester') === '2nd')>2nd semester</option>
                        <option value="summer" @selected(old('semester') === 'summer')>Summer</option>
                    </select>
                </label>
                <label class="text-xs font-bold uppercase text-slate-500">
                    Quarter
                    <select name="quarter" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                        <option value="">No quarter</option>
                        <option value="1" @selected(old('quarter') === '1')>Quarter 1</option>
                        <option value="2" @selected(old('quarter') === '2')>Quarter 2</option>
                        <option value="3" @selected(old('quarter') === '3')>Quarter 3</option>
                        <option value="4" @selected(old('quarter') === '4')>Quarter 4</option>
                    </select>
                </label>
            </div>
            <label class="mt-3 block text-xs font-bold uppercase text-slate-500">
                Notes
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">{{ old('notes') }}</textarea>
            </label>
            <button class="mt-3 rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Post fee</button>
        </form>
    </section>
</div>
@endsection
