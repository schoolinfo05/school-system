<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — School System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
    <span class="text-lg font-semibold text-gray-800">🏫 School System</span>
    <div class="flex gap-6 text-sm items-center">
        <a href="{{ route('admin.dashboard') }}" class="text-blue-600 font-medium">Dashboard</a>
        <a href="{{ route('admin.students.index') }}" class="text-gray-600 hover:text-blue-600">Students</a>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button class="text-gray-500 hover:text-red-500 text-sm">Logout</button>
        </form>
    </div>
</nav>

<div class="max-w-6xl mx-auto px-6 py-8">

    <h1 class="text-2xl font-semibold text-gray-800 mb-2">Dashboard</h1>
    <p class="text-sm text-gray-500 mb-6">Welcome back, {{ auth()->user()->name }}</p>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Total students</p>
            <p class="text-3xl font-semibold text-gray-800 mt-1">{{ $stats['total_students'] }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Active</p>
            <p class="text-3xl font-semibold text-green-600 mt-1">{{ $stats['active_students'] }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Fees collected</p>
            <p class="text-3xl font-semibold text-blue-600 mt-1">₱{{ number_format($stats['total_collected']) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Outstanding fees</p>
            <p class="text-3xl font-semibold text-red-500 mt-1">₱{{ number_format($stats['total_fees_due']) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Recent students --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-semibold text-gray-700">Recent students</h2>
                <a href="{{ route('admin.students.index') }}" class="text-sm text-blue-600 hover:underline">View all →</a>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-400 border-b text-xs uppercase">
                        <th class="pb-2">Name</th>
                        <th class="pb-2">Grade</th>
                        <th class="pb-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentStudents as $s)
                    <tr class="border-b last:border-0 hover:bg-gray-50">
                        <td class="py-2.5">
                            <a href="{{ route('admin.students.show', $s) }}" class="text-blue-600 hover:underline font-medium">
                                {{ $s->first_name }} {{ $s->last_name }}
                            </a>
                            <p class="text-xs text-gray-400">{{ $s->student_id }}</p>
                        </td>
                        <td class="py-2.5 text-gray-500">Gr. {{ $s->grade_level }} – {{ $s->section }}</td>
                        <td class="py-2.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $s->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ ucfirst($s->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="py-4 text-center text-gray-400 text-sm">No students yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Outstanding fees --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-700 mb-4">Outstanding fees</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-400 border-b text-xs uppercase">
                        <th class="pb-2">Student</th>
                        <th class="pb-2">Type</th>
                        <th class="pb-2">Amount</th>
                        <th class="pb-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unpaidFees as $fee)
                    <tr class="border-b last:border-0 hover:bg-gray-50">
                        <td class="py-2.5 font-medium text-gray-700">{{ $fee->student->first_name ?? '—' }}</td>
                        <td class="py-2.5 text-gray-500">{{ $fee->type }}</td>
                        <td class="py-2.5 font-medium">₱{{ number_format($fee->amount) }}</td>
                        <td class="py-2.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $fee->status === 'unpaid' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600' }}">
                                {{ ucfirst($fee->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="py-4 text-center text-gray-400 text-sm">No outstanding fees.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
</body>
</html>