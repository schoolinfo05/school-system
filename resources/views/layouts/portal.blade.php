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
            ['label' => 'Reports', 'route' => 'admin.reports.index', 'match' => 'admin/reports*', 'roles' => ['admin']],
            ['label' => 'Archive', 'route' => 'admin.archive.index', 'match' => 'admin/archive*', 'roles' => ['admin']],
        ],
        'Registrar' => [
            ['label' => 'Dashboard', 'route' => 'registrar.dashboard', 'match' => 'registrar/dashboard', 'roles' => ['admin', 'registrar']],
            ['label' => 'Enrollments', 'route' => 'registrar.enrollments.index', 'match' => 'registrar/enrollments*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Students', 'route' => 'registrar.students.index', 'match' => 'registrar/students*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Courses', 'route' => 'registrar.courses.index', 'match' => 'registrar/courses*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Subjects', 'route' => 'registrar.subjects.index', 'match' => 'registrar/subjects*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Sections', 'route' => 'registrar.sections.index', 'match' => 'registrar/sections*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Points', 'route' => 'registrar.points.index', 'match' => 'registrar/points*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Reports', 'route' => 'registrar.reports.index', 'match' => 'registrar/reports*', 'roles' => ['admin', 'registrar']],
            ['label' => 'Profile', 'route' => 'registrar.profile.show', 'match' => 'registrar/profile*', 'roles' => ['admin', 'registrar']],
        ],
        'Teacher' => [
            ['label' => 'Dashboard', 'route' => 'teacher.dashboard', 'match' => 'teacher/dashboard', 'roles' => ['faculty', 'teacher', 'head_teacher', 'dean']],
            ['label' => 'Classes', 'route' => 'teacher.classes', 'match' => 'teacher/classes', 'roles' => ['faculty', 'teacher', 'head_teacher', 'dean']],
            ['label' => 'Work', 'route' => 'teacher.assignments', 'match' => 'teacher/assignments*', 'roles' => ['faculty', 'teacher', 'head_teacher', 'dean']],
            ['label' => 'Market', 'route' => 'teacher.market', 'match' => 'teacher/market', 'roles' => ['faculty', 'teacher', 'head_teacher', 'dean']],
            ['label' => 'Chat', 'route' => 'teacher.chat', 'match' => 'teacher/chat', 'roles' => ['faculty', 'teacher', 'head_teacher', 'dean']],
            ['label' => 'Profile', 'route' => 'teacher.profile', 'match' => 'teacher/profile', 'roles' => ['faculty', 'teacher', 'head_teacher', 'dean']],
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
    <script>
        (() => {
            const theme = localStorage.getItem('portal-theme') || 'light';
            document.documentElement.dataset.portalTheme = theme;
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-shell min-h-screen bg-[#dcd8f4] text-slate-900 antialiased lg:h-screen lg:overflow-hidden">
    <div id="portal-loading-bar" class="pointer-events-none fixed left-0 top-0 z-50 h-1 w-0 bg-violet-600 opacity-0 shadow-lg shadow-violet-600/30 transition-all duration-300"></div>
    <div class="min-h-screen p-3 lg:h-screen lg:min-h-0 lg:p-5">
        <div class="min-h-[calc(100vh-1.5rem)] overflow-hidden rounded-2xl border border-white/70 bg-white/70 shadow-2xl shadow-violet-900/10 backdrop-blur lg:grid lg:h-[calc(100vh-2.5rem)] lg:min-h-0 lg:grid-cols-[250px_1fr]">
            <aside class="hidden bg-white/95 lg:flex lg:h-full lg:min-h-0 lg:flex-col">
            <div class="px-6 py-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-600 text-sm font-black text-white shadow-md shadow-violet-600/20">SB</span>
                    <div>
                        <p class="text-base font-black text-slate-950">SchoolBuds</p>
                        <p class="mt-0.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">Portal</p>
                    </div>
                </div>
            </div>

            <nav id="portal-sidebar-nav" class="portal-sidebar-scroll min-h-0 flex-1 space-y-6 overflow-y-auto px-3 pb-6">
                @foreach($visibleGroups as $group => $items)
                    <div>
                        <p class="px-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $group }}</p>
                        <div class="mt-2 space-y-1.5">
                            @foreach($items as $item)
                                @php($active = request()->is($item['match']))
                                <a href="{{ route($item['route']) }}"
                                    class="portal-nav-link group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold {{ $active ? 'is-active bg-violet-600 text-white shadow-md shadow-violet-600/20' : 'text-slate-500 hover:bg-violet-50 hover:text-violet-700' }}">
                                    <span class="portal-nav-icon flex h-7 w-7 items-center justify-center rounded-lg text-[11px] font-black {{ $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-400 group-hover:bg-violet-100 group-hover:text-violet-600' }}">{{ strtoupper(substr($item['label'], 0, 2)) }}</span>
                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            </aside>

        <div class="portal-content-panel min-w-0 bg-[#f8f7fc]">
            <header class="sticky top-0 z-20 border-b border-violet-100/80 bg-white/90 backdrop-blur lg:flex-none">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <div class="min-w-0">
                        <p class="text-sm font-black text-slate-950 lg:hidden">SchoolBuds</p>
                        <p class="hidden text-[10px] font-black uppercase tracking-[0.18em] text-violet-400 lg:block">Workspace</p>
                        <p class="truncate text-sm font-bold text-slate-700">{{ $title ?? 'Dashboard' }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-bold text-slate-900">{{ $user?->name }}</p>
                            <p class="text-xs text-slate-500">{{ ucfirst((string) $role) }}</p>
                        </div>
                        <button id="portal-theme-toggle" type="button" class="portal-theme-toggle rounded-xl border border-violet-100 bg-white px-3 py-2 text-sm font-bold text-slate-600 shadow-sm transition hover:bg-violet-50" aria-label="Toggle dark mode">
                            <span data-theme-label>Dark</span>
                        </button>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-violet-100 bg-white px-3 py-2 text-sm font-bold text-slate-600 shadow-sm transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>

                <nav class="flex gap-2 overflow-x-auto border-t border-violet-50 px-4 py-2 sm:px-6 lg:hidden">
                    @foreach($visibleGroups as $items)
                        @foreach($items as $item)
                            @php($active = request()->is($item['match']))
                            <a href="{{ route($item['route']) }}"
                                class="whitespace-nowrap rounded-xl px-3 py-2 text-xs font-bold transition {{ $active ? 'bg-violet-600 text-white' : 'bg-white text-slate-600 shadow-sm' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </header>

            <main class="portal-content-scroll mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
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
    </div>
</body>
</html>
