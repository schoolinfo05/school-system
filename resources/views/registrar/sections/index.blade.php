@extends('layouts.portal', ['title' => 'Sections'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Sections</h1>
    <p class="mt-1 text-sm text-slate-500">Create class sections and review assigned schedules.</p>
</div>

<form method="POST" action="{{ route('registrar.sections.store') }}" class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
    @csrf
    <input name="name" placeholder="Section name" class="rounded-lg border-slate-300 text-sm">
    <select name="program_type" class="rounded-lg border-slate-300 text-sm"><option value="college">College</option><option value="shs">SHS</option></select>
    <input name="course" placeholder="Course" class="rounded-lg border-slate-300 text-sm">
    <input name="strand" placeholder="Strand" class="rounded-lg border-slate-300 text-sm">
    <input name="year_level" placeholder="Year/Grade" class="rounded-lg border-slate-300 text-sm">
    <input name="school_year" value="{{ date('Y') }}-{{ date('Y') + 1 }}" class="rounded-lg border-slate-300 text-sm">
    <select name="semester" class="rounded-lg border-slate-300 text-sm"><option value="1st">1st</option><option value="2nd">2nd</option><option value="summer">Summer</option></select>
    <input name="max_students" value="40" class="rounded-lg border-slate-300 text-sm">
    <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> Active</label>
    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-bold text-white md:col-span-3">Create section</button>
</form>

<div class="grid grid-cols-1 gap-4">
    @foreach($sections as $section)
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="POST" action="{{ route('registrar.sections.update', $section) }}" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_120px_1fr_100px_120px_auto] md:items-center">
                @csrf
                @method('PUT')
                <input name="name" value="{{ $section->name }}" class="rounded-lg border-slate-300 text-sm">
                <select name="program_type" class="rounded-lg border-slate-300 text-sm"><option value="college" @selected($section->program_type === 'college')>College</option><option value="shs" @selected($section->program_type === 'shs')>SHS</option></select>
                <input name="course" value="{{ $section->course }}" placeholder="Course" class="rounded-lg border-slate-300 text-sm">
                <input name="year_level" value="{{ $section->year_level }}" placeholder="Level" class="rounded-lg border-slate-300 text-sm">
                <input name="max_students" value="{{ $section->max_students }}" class="rounded-lg border-slate-300 text-sm">
                <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Save</button>
                <input name="strand" value="{{ $section->strand }}" placeholder="Strand" class="rounded-lg border-slate-300 text-sm">
                <input name="school_year" value="{{ $section->school_year }}" class="rounded-lg border-slate-300 text-sm">
                <select name="semester" class="rounded-lg border-slate-300 text-sm"><option value="1st" @selected($section->semester === '1st')>1st</option><option value="2nd" @selected($section->semester === '2nd')>2nd</option><option value="summer" @selected($section->semester === 'summer')>Summer</option></select>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($section->is_active) class="rounded border-slate-300"> Active</label>
            </form>
            <div class="mt-4 rounded-lg bg-slate-50 p-3">
                <p class="text-xs font-black uppercase text-slate-400">Assigned subjects</p>
                <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                    @forelse($section->sectionSubjects as $assignment)
                        <p class="text-sm font-semibold text-slate-700">{{ $assignment->subject?->code }} - {{ $assignment->subject?->name }} <span class="text-slate-400">({{ $assignment->teacher?->name ?? 'No teacher' }})</span></p>
                    @empty
                        <p class="text-sm text-slate-500">No subjects assigned yet.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endforeach
    {{ $sections->links() }}
</div>
@endsection
