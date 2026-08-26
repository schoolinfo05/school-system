@extends('layouts.portal', ['title' => 'Subjects'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Subjects</h1>
    <p class="mt-1 text-sm text-slate-500">Maintain subject codes, units, program scope, and semester.</p>
</div>

<form method="POST" action="{{ route('registrar.subjects.store') }}" class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-4">
    @csrf
    <input name="code" placeholder="Code" class="rounded-lg border-slate-300 text-sm">
    <input name="name" placeholder="Subject name" class="rounded-lg border-slate-300 text-sm md:col-span-2">
    <select name="program_type" class="rounded-lg border-slate-300 text-sm"><option value="college">College</option><option value="shs">SHS</option></select>
    <input name="course" placeholder="Course" class="rounded-lg border-slate-300 text-sm">
    <input name="strand" placeholder="Strand" class="rounded-lg border-slate-300 text-sm">
    <input name="year_level" placeholder="Year/Grade" class="rounded-lg border-slate-300 text-sm">
    <select name="semester" class="rounded-lg border-slate-300 text-sm"><option value="1st">1st</option><option value="2nd">2nd</option><option value="summer">Summer</option></select>
    <input name="units_lec" value="3" placeholder="Lec units" class="rounded-lg border-slate-300 text-sm">
    <input name="units_lab" value="0" placeholder="Lab units" class="rounded-lg border-slate-300 text-sm">
    <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> Active</label>
    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-bold text-white">Create subject</button>
</form>

<div class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-sm">
    @foreach($subjects as $subject)
        <form method="POST" action="{{ route('registrar.subjects.update', $subject) }}" class="grid grid-cols-1 gap-3 p-4 md:grid-cols-[100px_1fr_120px_100px_100px_auto] md:items-center">
            @csrf
            @method('PUT')
            <input name="code" value="{{ $subject->code }}" class="rounded-lg border-slate-300 text-sm">
            <input name="name" value="{{ $subject->name }}" class="rounded-lg border-slate-300 text-sm">
            <select name="program_type" class="rounded-lg border-slate-300 text-sm"><option value="college" @selected($subject->program_type === 'college')>College</option><option value="shs" @selected($subject->program_type === 'shs')>SHS</option></select>
            <input name="units_lec" value="{{ $subject->units_lec }}" class="rounded-lg border-slate-300 text-sm">
            <input name="units_lab" value="{{ $subject->units_lab }}" class="rounded-lg border-slate-300 text-sm">
            <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Save</button>
            <input name="course" value="{{ $subject->course }}" placeholder="Course" class="rounded-lg border-slate-300 text-sm">
            <input name="strand" value="{{ $subject->strand }}" placeholder="Strand" class="rounded-lg border-slate-300 text-sm">
            <input name="year_level" value="{{ $subject->year_level }}" placeholder="Year/Grade" class="rounded-lg border-slate-300 text-sm">
            <select name="semester" class="rounded-lg border-slate-300 text-sm"><option value="1st" @selected($subject->semester === '1st')>1st</option><option value="2nd" @selected($subject->semester === '2nd')>2nd</option><option value="summer" @selected($subject->semester === 'summer')>Summer</option></select>
            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" @checked($subject->is_active) class="rounded border-slate-300"> Active</label>
        </form>
    @endforeach
    <div class="p-4">{{ $subjects->links() }}</div>
</div>
@endsection
