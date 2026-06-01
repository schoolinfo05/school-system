<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Entry — {{ $class->subject }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
    <span class="text-lg font-semibold text-gray-800">🏫 School System</span>
    <a href="{{ route('teacher.dashboard') }}" class="text-sm text-blue-600 hover:underline">← Dashboard</a>
</nav>

<div class="max-w-5xl mx-auto px-6 py-8">

    <h1 class="text-xl font-semibold text-gray-800 mb-1">Grade Entry</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $class->subject }} · Grade {{ $class->grade_level }} – {{ $class->section }}</p>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
        {{ session('success') }}
    </div>
    @endif

    <!-- Quarter tabs -->
    <div class="flex gap-2 mb-6" id="quarter-tabs">
        @foreach([1,2,3,4] as $q)
        <button onclick="switchQuarter({{ $q }})" id="tab-{{ $q }}"
            class="px-5 py-2 rounded-lg text-sm font-medium border transition-all
            {{ $q === 1 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
            Quarter {{ $q }}
        </button>
        @endforeach
    </div>

    @foreach([1,2,3,4] as $quarter)
    <div id="quarter-{{ $quarter }}" class="{{ $quarter !== 1 ? 'hidden' : '' }}">
        <form method="POST" action="{{ route('teacher.grades.store', $class) }}">
            @csrf
            <input type="hidden" name="quarter_display" value="{{ $quarter }}">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                        <tr>
                            <th class="px-5 py-3 text-left">Student</th>
                            <th class="px-5 py-3 text-center">Score (0–100)</th>
                            <th class="px-5 py-3 text-center">Current</th>
                            <th class="px-5 py-3 text-center">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($students as $i => $student)
                        @php
                            $key     = $student->id . '_' . $quarter;
                            $existing = $grades[$key]->first() ?? null;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <input type="hidden" name="grades[{{ $i }}][student_id]" value="{{ $student->id }}">
                                <input type="hidden" name="grades[{{ $i }}][quarter]"    value="{{ $quarter }}">
                                <p class="font-medium text-gray-800">{{ $student->first_name }} {{ $student->last_name }}</p>
                                <p class="text-xs text-gray-400">{{ $student->student_id }}</p>
                            </td>
                            <td class="px-5 py-3 text-center">
                                <input type="number" name="grades[{{ $i }}][score]"
                                    value="{{ $existing?->score ?? '' }}"
                                    min="0" max="100" step="0.01"
                                    placeholder="—"
                                    class="w-24 text-center border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if($existing)
                                <span class="font-semibold {{ $existing->score >= 85 ? 'text-green-600' : ($existing->score >= 75 ? 'text-yellow-600' : 'text-red-500') }}">
                                    {{ $existing->score }}
                                </span>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center text-xs text-gray-400">
                                {{ $existing?->remarks ?? '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
                    <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-blue-700 font-medium">
                        Save Q{{ $quarter }} grades
                    </button>
                </div>
            </div>
        </form>
    </div>
    @endforeach
</div>

<script>
function switchQuarter(q) {
    [1,2,3,4].forEach(i => {
        document.getElementById('quarter-' + i).classList.add('hidden');
        document.getElementById('tab-' + i).className = 'px-5 py-2 rounded-lg text-sm font-medium border transition-all bg-white text-gray-600 border-gray-200 hover:bg-gray-50';
    });
    document.getElementById('quarter-' + q).classList.remove('hidden');
    document.getElementById('tab-' + q).className = 'px-5 py-2 rounded-lg text-sm font-medium border transition-all bg-blue-600 text-white border-blue-600';
}
</script>
</body>
</html>