<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance — {{ $class->subject }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
    <span class="text-lg font-semibold text-gray-800">🏫 School System</span>
    <a href="{{ route('teacher.dashboard') }}" class="text-sm text-blue-600 hover:underline">← Dashboard</a>
</nav>

<div class="max-w-4xl mx-auto px-6 py-8">

    <h1 class="text-xl font-semibold text-gray-800 mb-1">Mark Attendance</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $class->subject }} · Grade {{ $class->grade_level }} – {{ $class->section }}</p>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 text-sm">
        {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('teacher.attendance.store', $class) }}">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
            <div class="flex items-center gap-4">
                <label class="text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="date" value="{{ $today }}"
                    class="border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                <div class="flex gap-2 ml-auto">
                    <button type="button" onclick="markAll('present')"
                        class="text-xs bg-green-50 text-green-700 border border-green-200 px-3 py-1.5 rounded-lg hover:bg-green-100">
                        ✅ All present
                    </button>
                    <button type="button" onclick="markAll('absent')"
                        class="text-xs bg-red-50 text-red-700 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100">
                        ❌ All absent
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Student</th>
                        <th class="px-5 py-3 text-center">Present</th>
                        <th class="px-5 py-3 text-center">Late</th>
                        <th class="px-5 py-3 text-center">Excused</th>
                        <th class="px-5 py-3 text-center">Absent</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($students as $student)
                    @php $att = $existing[$student->id] ?? null; @endphp
                    <tr class="hover:bg-gray-50" id="row-{{ $student->id }}">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-800">{{ $student->first_name }} {{ $student->last_name }}</p>
                            <p class="text-xs text-gray-400">{{ $student->student_id }}</p>
                        </td>
                        @foreach(['present','late','excused','absent'] as $status)
                        <td class="px-5 py-3 text-center">
                            <input type="radio"
                                name="attendance[{{ $student->id }}][status]"
                                value="{{ $status }}"
                                {{ ($att?->status ?? 'present') === $status ? 'checked' : '' }}
                                onchange="highlightRow({{ $student->id }}, '{{ $status }}')"
                                class="w-4 h-4 accent-blue-600">
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-4 border-t flex justify-between items-center">
                <p class="text-sm text-gray-400" id="summary">
                    {{ $students->count() }} students
                </p>
                <button type="submit"
                    class="bg-green-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-green-700 font-medium">
                    Save attendance
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function markAll(status) {
    document.querySelectorAll(`input[type=radio][value=${status}]`).forEach(r => {
        r.checked = true;
        const sid = r.name.match(/\d+/)[0];
        highlightRow(sid, status);
    });
}

function highlightRow(studentId, status) {
    const row = document.getElementById('row-' + studentId);
    row.classList.remove('bg-green-50','bg-red-50','bg-yellow-50','bg-blue-50');
    if (status === 'present') row.classList.add('bg-green-50');
    else if (status === 'absent') row.classList.add('bg-red-50');
    else if (status === 'late') row.classList.add('bg-yellow-50');
    else if (status === 'excused') row.classList.add('bg-blue-50');
}

document.querySelectorAll('input[type=radio]:checked').forEach(r => {
    const sid = r.name.match(/\d+/)[0];
    highlightRow(sid, r.value);
});
</script>
</body>
</html>