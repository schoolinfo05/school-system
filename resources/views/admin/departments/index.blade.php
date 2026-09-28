@extends('layouts.portal', ['title' => 'Departments'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Departments</h1>
    <p class="mt-1 text-sm text-slate-500">Create college departments, assign their chair, and group programs under each department.</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[360px_1fr]">
    <section class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-black text-slate-800">Create Department</h2>
        <form method="POST" action="{{ route('admin.departments.store') }}" class="mt-4 space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" placeholder="Department name, e.g. Computing" class="w-full rounded-lg border-slate-300 text-sm">
            <input name="code" value="{{ old('code') }}" placeholder="Code, e.g. CCIS" class="w-full rounded-lg border-slate-300 text-sm uppercase">
            <select name="chair_user_id" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="">Assign chair later</option>
                @foreach($chairs as $chair)
                    <option value="{{ $chair->id }}" @selected(old('chair_user_id') == $chair->id)>{{ $chair->name }} — {{ $chair->email }}</option>
                @endforeach
            </select>
            <div class="rounded-lg border border-slate-200 p-3">
                <p class="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">Programs in this department</p>
                <div class="max-h-44 space-y-2 overflow-y-auto pr-1">
                    @forelse($courses as $course)
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked(in_array($course->id, old('course_ids', []))) class="rounded border-slate-300"> {{ $course->acronym ?: $course->name }}</label>
                    @empty
                        <p class="text-sm text-slate-500">Create a college program in Registrar → Courses first.</p>
                    @endforelse
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> Active</label>
            <button type="submit" class="portal-button-primary mt-1 w-full">Create Department</button>
        </form>
    </section>

    <section class="space-y-3">
        @forelse($departments as $department)
            <details class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 hover:bg-slate-50">
                    <div class="min-w-0"><p class="text-sm font-black text-slate-900">{{ $department->name }}</p><p class="mt-1 text-xs font-semibold text-slate-500">{{ $department->code }} · {{ $department->courses->count() }} programs · {{ $department->faculty_count }} faculty</p></div>
                    <span class="rounded-full px-3 py-1 text-xs font-black {{ $department->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span>
                </summary>
                <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="space-y-3 border-t border-slate-100 p-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><input name="name" value="{{ $department->name }}" class="rounded-lg border-slate-300 text-sm"><input name="code" value="{{ $department->code }}" class="rounded-lg border-slate-300 text-sm uppercase"></div>
                    <select name="chair_user_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Assign chair later</option>@foreach($chairs as $chair)<option value="{{ $chair->id }}" @selected($department->chair_user_id === $chair->id)>{{ $chair->name }} — {{ $chair->email }}</option>@endforeach</select>
                    <div class="rounded-lg border border-slate-200 p-3"><p class="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">Programs</p><div class="grid gap-2 sm:grid-cols-2">@foreach($courses as $course)<label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked($course->department_id === $department->id) class="rounded border-slate-300"> {{ $course->acronym ?: $course->name }}</label>@endforeach</div></div>
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($department->is_active) class="rounded border-slate-300"> Active</label>
                    <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Save changes</button>
                </form>
            </details>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm font-semibold text-slate-500">No departments yet.</div>
        @endforelse
    </section>
</div>
@endsection
