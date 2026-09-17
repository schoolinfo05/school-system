@extends('layouts.portal', ['title' => 'Courses'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Courses</h1>
    <p class="mt-1 text-sm text-slate-500">Manage college courses and SHS strands/programs used during enrollment.</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[360px_1fr]">
    <section class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-black text-slate-800">Add Course</h2>
        <form method="POST" action="{{ route('registrar.courses.store') }}" class="mt-4 space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" placeholder="Course or strand name" class="w-full rounded-lg border-slate-300 text-sm">
            <input name="acronym" value="{{ old('acronym') }}" placeholder="Acronym, e.g. BSIT" class="w-full rounded-lg border-slate-300 text-sm uppercase">
            <select name="program_type" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="college">College</option>
                <option value="shs">SHS</option>
            </select>
            <textarea name="description" rows="3" placeholder="Description" class="w-full rounded-lg border-slate-300 text-sm">{{ old('description') }}</textarea>
            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> Active</label>
            <button class="w-full rounded-lg bg-slate-950 py-2 text-sm font-bold text-white">Create course</button>
        </form>
    </section>

    <section>
        <form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_160px_auto]">
            <input name="search" value="{{ request('search') }}" placeholder="Search courses" class="rounded-lg border-slate-300 text-sm">
            <select name="program_type" class="rounded-lg border-slate-300 text-sm">
                <option value="">All programs</option>
                <option value="college" @selected(request('program_type') === 'college')>College</option>
                <option value="shs" @selected(request('program_type') === 'shs')>SHS</option>
            </select>
            <button class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-bold text-white">Filter</button>
        </form>

        <div class="space-y-3">
            @foreach($courses as $course)
                @php
                    $programLabel = $course->program_type === 'shs' ? 'SHS' : 'College';
                    $statusClass = $course->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500';
                @endphp
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-course-card>
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 p-4 text-left transition-colors hover:bg-slate-50"
                        onclick="this.closest('[data-course-card]').querySelector('[data-course-details]').classList.toggle('hidden'); this.querySelector('[data-arrow]').classList.toggle('rotate-180');"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-black text-blue-600">
                                {{ $course->acronym ?: 'COURSE' }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-900">{{ $course->name }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $programLabel }} · {{ $course->description ?: 'No description' }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold {{ $statusClass }}">
                                {{ $course->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <svg data-arrow class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>

                    <div data-course-details class="hidden border-t border-slate-100">
                        <div class="bg-slate-50 px-4 py-3">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">Course details</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Created: {{ optional($course->created_at)->format('M d, Y h:i A') }} · Updated: {{ optional($course->updated_at)->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                                <button
                                    type="submit"
                                    form="delete-course-{{ $course->id }}"
                                    class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-700"
                                    onclick="return confirm('Archive and remove {{ addslashes($course->name) }}?');"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('registrar.courses.update', $course) }}" class="grid grid-cols-1 gap-4 p-4 md:grid-cols-4 md:items-end">
                            @csrf
                            @method('PUT')
                            <label class="text-xs font-bold uppercase text-slate-500 md:col-span-2">
                                Course or strand name
                                <input name="name" value="{{ $course->name }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                            </label>
                            <label class="text-xs font-bold uppercase text-slate-500">
                                Acronym
                                <input name="acronym" value="{{ $course->acronym }}" placeholder="BSIT" class="mt-1 w-full rounded-lg border-slate-300 text-sm uppercase text-slate-900">
                            </label>
                            <label class="text-xs font-bold uppercase text-slate-500">
                                Program type
                                <select name="program_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900">
                                    <option value="college" @selected($course->program_type === 'college')>College</option>
                                    <option value="shs" @selected($course->program_type === 'shs')>SHS</option>
                                </select>
                            </label>
                            <label class="text-xs font-bold uppercase text-slate-500 md:col-span-4">
                                Description
                                <textarea name="description" class="mt-1 w-full rounded-lg border-slate-300 text-sm normal-case text-slate-900" rows="2">{{ $course->description }}</textarea>
                            </label>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700 md:pb-2">
                                <input type="checkbox" name="is_active" value="1" @checked($course->is_active) class="rounded border-slate-300"> Active
                            </label>
                            <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white md:col-start-4">Save changes</button>
                        </form>
                    </div>
                </div>
                <form id="delete-course-{{ $course->id }}" method="POST" action="{{ route('registrar.courses.destroy', $course) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">{{ $courses->links() }}</div>
        </div>
    </section>
</div>
@endsection
