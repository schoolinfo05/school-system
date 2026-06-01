<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $student->first_name }} {{ $student->last_name }} — School System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
    <span class="text-lg font-semibold text-gray-800">🏫 School System</span>
    <div class="flex gap-6 text-sm">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 hover:text-blue-600">Dashboard</a>
        <a href="{{ route('admin.students.index') }}" class="text-gray-600 hover:text-blue-600">Students</a>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-6 py-8">
    <a href="{{ route('admin.students.index') }}" class="text-sm text-blue-600 hover:underline mb-4 inline-block">← Back to students</a>

    {{-- Profile header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6 flex items-center gap-5">
        <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xl font-semibold flex-shrink-0">
            {{ strtoupper(substr($student->first_name,0,1)) }}{{ strtoupper(substr($student->last_name,0,1)) }}
        </div>
        <div class="flex-1">
            <h1 class="text-xl font-semibold text-gray-800">{{ $student->first_name }} {{ $student->last_name }}</h1>
            <p class="text-sm text-gray-400 mt-0.5">{{ $student->student_id }} · Grade {{ $student->grade_level }} – {{ $student->section }} · {{ $student->school_year }}</p>
            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium
                {{ $student->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                {{ ucfirst($student->status) }}
            </span>
        </div>
        <div class="text-right">
            <p class="text-3xl font-semibold {{ $attendancePct >= 85 ? 'text-green-600' : 'text-red-500' }}">{{ $attendancePct }}%</p>
            <p class="text-xs text-gray-400">Attendance rate</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Grades --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-700 mb-4">Grades by quarter</h2>
            @forelse($grades as $quarter => $quarterGrades)
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Quarter {{ $quarter }}</p>
                @foreach($quarterGrades as $grade)
                <div class="flex justify-between items-center py-2 border-b last:border-0">
                    <span class="text-sm text-gray-700">{{ $grade->schoolClass->subject ?? '—' }}</span>
                    <div class="text-right">
                        <span class="text-sm font-semibold
                            {{ $grade->score >= 90 ? 'text-green-600' : ($grade->score >= 80 ? 'text-blue-600' : ($grade->score >= 75 ? 'text-yellow-600' : 'text-red-500')) }}">
                            {{ $grade->score }}
                        </span>
                        <span class="text-xs text-gray-400 ml-2">{{ $grade->remarks }}</span>
                    </div>
                </div>
                @endforeach
            </div>
            @empty
            <p class="text-sm text-gray-400">No grades recorded yet.</p>
            @endforelse
        </div>

        {{-- Fees --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-700 mb-4">Fees</h2>
            @forelse($fees as $fee)
            <div class="flex justify-between items-center py-2.5 border-b last:border-0">
                <div>
                    <p class="text-sm font-medium text-gray-700">{{ $fee->type }}</p>
                    <p class="text-xs text-gray-400">Q{{ $fee->quarter }} · Due {{ \Carbon\Carbon::parse($fee->due_date)->format('M d, Y') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-800">₱{{ number_format($fee->amount) }}</p>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        {{ $fee->status === 'paid' ? 'bg-green-100 text-green-700' : ($fee->status === 'partial' ? 'bg-yellow-100 text-yellow-600' : 'bg-red-100 text-red-600') }}">
                        {{ ucfirst($fee->status) }}
                    </span>
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400">No fees recorded.</p>
            @endforelse

            @if($fees->count())
            <div class="mt-4 pt-3 border-t flex justify-between text-sm">
                <span class="text-gray-500">Total due</span>
                <span class="font-semibold text-red-500">₱{{ number_format($fees->where('status','!=','paid')->sum('amount')) }}</span>
            </div>
            @endif
        </div>

    </div>
</div>
</body>
</html>