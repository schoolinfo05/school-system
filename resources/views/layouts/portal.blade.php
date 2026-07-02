<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SchoolBuds Portal' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
    <nav class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-lg font-black text-slate-900">SchoolBuds</p>
                <p class="text-xs text-slate-500">{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->is('admin/dashboard') ? 'text-blue-700 font-bold' : 'text-slate-600 hover:text-blue-700' }}">Admin</a>
                <a href="{{ route('admin.students.index') }}" class="{{ request()->is('admin/students*') ? 'text-blue-700 font-bold' : 'text-slate-600 hover:text-blue-700' }}">Students</a>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->is('admin/users*') ? 'text-blue-700 font-bold' : 'text-slate-600 hover:text-blue-700' }}">Users</a>
                <a href="{{ route('registrar.dashboard') }}" class="{{ request()->is('registrar/dashboard') ? 'text-emerald-700 font-bold' : 'text-slate-600 hover:text-emerald-700' }}">Registrar</a>
                <a href="{{ route('registrar.enrollments.index') }}" class="{{ request()->is('registrar/enrollments*') ? 'text-emerald-700 font-bold' : 'text-slate-600 hover:text-emerald-700' }}">Enrollments</a>
                <a href="{{ route('registrar.points.index') }}" class="{{ request()->is('registrar/points*') ? 'text-emerald-700 font-bold' : 'text-slate-600 hover:text-emerald-700' }}">Points</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-red-600">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        @if(session('status'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
