<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — School System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
    <span class="text-lg font-semibold text-gray-800">🏫 School System</span>
    <div class="flex gap-6 text-sm">
        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 hover:text-blue-600">Dashboard</a>
        <a href="{{ route('admin.students.index') }}" class="text-blue-600 font-medium">Students</a>
    </div>
</nav>

<div class="max-w-6xl mx-auto px-6 py-8">
    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Students</h1>

    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Search by name or student ID..."
            class="flex-1 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
        <select name="grade_level" class="border border-gray-200 rounded-lg px-4 py-2 text-sm">
            <option value="">All grades</option>
            @foreach(range(7, 12) as $g)
                <option value="{{ $g }}" {{ request('grade_level') == $g ? 'selected' : '' }}>Grade {{ $g }}</option>
            @endforeach
        </select>
        <button class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700">Search</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-400 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Student ID</th>
                    <th class="px-5 py-3 text-left">Name</th>
                    <th class="px-5 py-3 text-left">Grade & Section</th>
                    <th class="px-5 py-3 text-left">School year</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($students as $s)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-gray-400 font-mono text-xs">{{ $s->student_id }}</td>
                    <td class="px-5 py-3 font-medium text-gray-800">{{ $s->first_name }} {{ $s->last_name }}</td>
                    <td class="px-5 py-3 text-gray-500">Grade {{ $s->grade_level }} – {{ $s->section }}</td>
                    <td class="px-5 py-3 text-gray-500">{{ $s->school_year }}</td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $s->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ ucfirst($s->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        <a href="{{ route('admin.students.show', $s) }}" class="text-blue-600 hover:underline text-xs">View →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-gray-400">No students found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 text-sm text-gray-500">
            {{ $students->links() }}
        </div>
    </div>
</div>
</body>
</html>