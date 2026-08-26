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

        <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-sm">
            @foreach($courses as $course)
                <form method="POST" action="{{ route('registrar.courses.update', $course) }}" class="grid grid-cols-1 gap-3 p-4 md:grid-cols-[1fr_140px_120px_auto] md:items-center">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $course->name }}" class="rounded-lg border-slate-300 text-sm">
                    <select name="program_type" class="rounded-lg border-slate-300 text-sm">
                        <option value="college" @selected($course->program_type === 'college')>College</option>
                        <option value="shs" @selected($course->program_type === 'shs')>SHS</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($course->is_active) class="rounded border-slate-300"> Active</label>
                    <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Save</button>
                    <textarea name="description" class="rounded-lg border-slate-300 text-sm md:col-span-4" rows="2">{{ $course->description }}</textarea>
                </form>
            @endforeach
            <div class="p-4">{{ $courses->links() }}</div>
        </div>
    </section>
</div>
@endsection
