@php
    $user = auth()->user();
    $role = $user?->role;

    $navGroups = [
        'Admin' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'match' => 'admin/dashboard', 'roles' => ['admin']],
            ['label' => 'Students', 'route' => 'admin.students.index', 'match' => 'admin/students*', 'roles' => ['admin']],
            ['label' => 'Users', 'route' => 'admin.users.index', 'match' => 'admin/users*', 'roles' => ['admin']],
            ['label' => 'Controls', 'route' => 'admin.controls.index', 'match' => 'admin/controls*', 'roles' => ['admin']],
            ['label' => 'Activity Logs', 'route' => 'admin.activity.index', 'match' => 'admin/activity*', 'roles' => ['admin']],
        ],
        'Registrar' => [
            ['label' => 'Dashboard', 'route' => 'registrar.dashboard', 'match' => 'registrar/dashboard', 'roles' => ['admin', 'registrar']],
            ['label' => 'Enrollments', 'route' => 'registrar.enrollments.index', 'match' => 'registrar/enrollments*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Students', 'route' => 'registrar.students.index', 'match' => 'registrar/students*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Points', 'route' => 'registrar.points.index', 'match' => 'registrar/points*', 'roles' => ['admin', 'registrar']],
        ],
        'Teacher' => [
            ['label' => 'Dashboard', 'route' => 'teacher.dashboard', 'match' => 'teacher/dashboard', 'roles' => ['admin', 'faculty', 'teacher', 'head_teacher', 'dean']],
        ],
        'Staff' => [
            ['label' => 'Property & Market', 'route' => 'property-custodian.dashboard', 'match' => 'property-custodian*', 'roles' => ['admin', 'property_custodian']],
        ],
    ];

    $visibleGroups = collect($navGroups)
        ->map(fn ($items) => collect($items)->filter(fn ($item) => in_array($role, $item['roles'], true) || in_array($user?->position, $item['roles'], true))->values())
        ->filter(fn ($items) => $items->isNotEmpty());
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'SchoolBuds Portal' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-900 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
        <aside class="hidden border-r border-slate-200 bg-white lg:flex lg:min-h-screen lg:flex-col">
            <div class="border-b border-slate-200 px-6 py-5">
                <p class="text-lg font-black text-slate-950">SchoolBuds</p>
                <p class="mt-1 text-xs font-medium text-slate-500">School management portal</p>
            </div>

            <nav class="flex-1 space-y-6 px-4 py-6">
                @foreach($visibleGroups as $group => $items)
                    <div>
                        <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $group }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach($items as $item)
                                @php($active = request()->is($item['match']))
                                <a href="{{ route($item['route']) }}"
                                    class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition {{ $active ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                                    <span>{{ $item['label'] }}</span>
                                    @if($active)
                                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-300"></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-slate-200 px-4 py-4">
                <div class="rounded-lg bg-slate-50 px-3 py-3">
                    <p class="truncate text-sm font-bold text-slate-900">{{ $user?->name }}</p>
                    <p class="mt-0.5 text-xs font-medium text-slate-500">{{ ucfirst((string) $role) }}</p>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <div class="min-w-0">
                        <p class="text-sm font-black text-slate-950 lg:hidden">SchoolBuds</p>
                        <p class="hidden text-xs font-semibold uppercase tracking-wider text-slate-400 lg:block">Portal</p>
                        <p class="truncate text-sm text-slate-500">{{ $title ?? 'Dashboard' }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-bold text-slate-900">{{ $user?->name }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst((string) $role) }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>

                <nav class="flex gap-2 overflow-x-auto border-t border-slate-100 px-4 py-2 sm:px-6 lg:hidden">
                    @foreach($visibleGroups as $items)
                        @foreach($items as $item)
                            @php($active = request()->is($item['match']))
                            <a href="{{ route($item['route']) }}"
                                class="whitespace-nowrap rounded-lg px-3 py-2 text-xs font-bold transition {{ $active ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </header>

            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
