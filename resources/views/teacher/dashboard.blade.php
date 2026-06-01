<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard — School System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
    <span class="text-lg font-semibold text-gray-800">🏫 School System</span>
    <div class="flex gap-6 text-sm items-center">
        <a href="{{ route('teacher.dashboard') }}" class="text-blue-600 font-medium">Dashboard</a>
        <span class="text-gray-400">|</span>
        <span class="text-gray-600">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button class="text-gray-500 hover:text-red-500">Logout</button>
        </form>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-6 py-8">

    <h1 class="text-2xl font-semibold text-gray-800 mb-2">Good day, {{ auth()->user()->name }}!</h1>
    <p class="text-sm text-gray-500 mb-6">Here are your classes for S.Y. 2025–2026</p>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
        {{ session('success') }}
    </div>
    @endif

    <!-- Stat cards -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs text-gray-400 uppercase tracking-wide">My classes</p>
            <p class="text-3xl font-semibold text-gray-800 mt-1">{{ $classes->count() }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Total students</p>
            <p class="text-3xl font-semibold text-blue-600 mt-1">{{ $totalStudents }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Grades entered</p>
            <p class="text-3xl font-semibold text-green-600 mt-1">{{ $recentGrades->count() }}</p>
        </div>
    </div>

    <!-- My Classes -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <h2 class="font-semibold text-gray-700 mb-4">My classes</h2>
        @forelse($classes as $class)
        <div class="flex items-center justify-between py-3 border-b last:border-0">
            <div>
                <p class="font-medium text-gray-800">{{ $class->subject }}</p>
                <p class="text-sm text-gray-400">Grade {{ $class->grade_level }} – {{ $class->section }} · {{ $class->room }} · {{ $class->schedule }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('teacher.attendance', $class) }}"
                    class="text-xs bg-green-50 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg hover:bg-green-100">
                    ✅ Attendance
                </a>
                <a href="{{ route('teacher.grades', $class) }}"
                    class="text-xs bg-blue-50 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-100">
                    📝 Grades
                </a>
                <a href="{{ route('teacher.class', $class) }}"
                    class="text-xs bg-gray-50 text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-100">
                    👥 Students
                </a>
            </div>
        </div>
        @empty
        <p class="text-sm text-gray-400 py-4 text-center">No classes assigned yet.</p>
        @endforelse
    </div>

    <!-- Recent grades -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Recently entered grades</h2>
        @forelse($recentGrades as $grade)
        <div class="flex justify-between items-center py-2 border-b last:border-0">
            <div>
                <p class="text-sm font-medium text-gray-800">{{ $grade->student->first_name }} {{ $grade->student->last_name }}</p>
                <p class="text-xs text-gray-400">{{ $grade->schoolClass->subject }} · Q{{ $grade->quarter }}</p>
            </div>
            <span class="text-sm font-semibold {{ $grade->score >= 85 ? 'text-green-600' : ($grade->score >= 75 ? 'text-yellow-600' : 'text-red-500') }}">
                {{ $grade->score }}
            </span>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-4">No grades entered yet.</p>
        @endforelse
    </div>

</div>
</body>
</html>