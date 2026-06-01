<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $class->subject }} — Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
    <span class="text-lg font-semibold text-gray-800">🏫 School System</span>
    <a href="{{ route('teacher.dashboard') }}" class="text-sm text-blue-600 hover:underline">← Dashboard</a>
</nav>

<div class="max-w-5xl mx-auto px-6 py-8">
    <h1 class="text-xl font-semibold text-gray-800 mb-1">{{ $class->subject }}</h1>
    <p class="text-sm text-gray-500 mb-6">Grade {{ $class->grade_level }} – {{ $class->section }} · {{ $class->room }} · {{ $class->schedule }}</p>

    <div class="flex gap-3 mb-6">
        <a href="{{ route('teacher.grades', $class) }}"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
            📝 Enter grades
        </a>
        <a href="{{ route('teacher.attendance', $class) }}"
            class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">
            ✅ Mark attendance
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Student</th>
                    <th class="px-5 py-3 text-center">Q1</th>
                    <th class="px-5 py-3 text-center">Q2</th>
                    <th class="px-5 py-3 text-center">Q3</th>
                    <th class="px-5 py-3 text-center">Q4</th>
                    <th class="px-5 py-3 text-center">Average</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($students as $student)
                @php
                    $sg  = $grades[$student->id] ?? collect();
                    $avg = $sg->count() ? round($sg->avg('score'), 1) : null;
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-800">{{ $student->first_name }} {{ $student->last_name }}</p>
                        <p class="text-xs text-gray-400">{{ $student->student_id }}</p>
                    </td>
                    @foreach([1,2,3,4] as $q)
                    @php $g = $sg->firstWhere('quarter', $q); @endphp
                    <td class="px-5 py-3 text-center">
                        @if($g)
                        <span class="{{ $g->score >= 85 ? 'text-green-600' : ($g->score >= 75 ? 'text-yellow-600' : 'text-red-500') }} font-medium">
                            {{ $g->score }}
                        </span>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    @endforeach
                    <td class="px-5 py-3 text-center font-semibold
                        {{ $avg >= 85 ? 'text-green-600' : ($avg >= 75 ? 'text-yellow-600' : 'text-gray-400') }}">
                        {{ $avg ?? '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</body>
</html>