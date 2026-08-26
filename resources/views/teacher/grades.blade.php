@extends('layouts.portal', ['title' => 'Grade Entry'])

@section('content')
<a href="{{ route('teacher.classes') }}" class="text-sm font-bold text-violet-700 hover:underline">Back to classes</a>

<div class="mt-4 mb-6">
    <h1 class="text-2xl font-black text-slate-900">Grade Entry</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $class->subject }} · Grade {{ $class->grade_level }} - {{ $class->section }}</p>
</div>

<div class="mb-6 flex flex-wrap gap-2" id="quarter-tabs">
    @foreach([1,2,3,4] as $q)
        <button type="button" onclick="switchQuarter({{ $q }})" id="tab-{{ $q }}"
            class="rounded-xl border px-5 py-2 text-sm font-bold transition {{ $q === 1 ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-slate-600 border-violet-100 hover:bg-violet-50' }}">
            Quarter {{ $q }}
        </button>
    @endforeach
</div>

@foreach([1,2,3,4] as $quarter)
    <div id="quarter-{{ $quarter }}" class="{{ $quarter !== 1 ? 'hidden' : '' }}">
        <form method="POST" action="{{ route('teacher.grades.store', $class) }}">
            @csrf
            <input type="hidden" name="quarter_display" value="{{ $quarter }}">
            <section class="portal-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left">Student</th>
                            <th class="px-5 py-3 text-center">Score</th>
                            <th class="px-5 py-3 text-center">Current</th>
                            <th class="px-5 py-3 text-center">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($students as $i => $student)
                            @php
                                $key = $student->id . '_' . $quarter;
                                $existing = $grades[$key]->first() ?? null;
                            @endphp
                            <tr>
                                <td class="px-5 py-3">
                                    <input type="hidden" name="grades[{{ $i }}][student_id]" value="{{ $student->id }}">
                                    <input type="hidden" name="grades[{{ $i }}][quarter]" value="{{ $quarter }}">
                                    <p class="font-bold text-slate-800">{{ $student->first_name }} {{ $student->last_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $student->student_id }}</p>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <input type="number" name="grades[{{ $i }}][score]" value="{{ $existing?->score ?? '' }}" min="0" max="100" step="0.01" placeholder="-" class="portal-field w-24 text-center">
                                </td>
                                <td class="px-5 py-3 text-center font-black {{ $existing && $existing->score >= 90 ? 'text-emerald-700' : ($existing && $existing->score >= 75 ? 'text-blue-700' : 'text-slate-400') }}">
                                    {{ $existing?->score ?? '-' }}
                                </td>
                                <td class="px-5 py-3 text-center text-xs font-semibold text-slate-500">{{ $existing?->remarks ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="flex justify-end border-t border-violet-100 px-5 py-4">
                    <button class="portal-button-primary">Save Q{{ $quarter }} grades</button>
                </div>
            </section>
        </form>
    </div>
@endforeach

<script>
function switchQuarter(q) {
    [1, 2, 3, 4].forEach((i) => {
        document.getElementById('quarter-' + i).classList.add('hidden');
        document.getElementById('tab-' + i).className = 'rounded-xl border px-5 py-2 text-sm font-bold transition bg-white text-slate-600 border-violet-100 hover:bg-violet-50';
    });
    document.getElementById('quarter-' + q).classList.remove('hidden');
    document.getElementById('tab-' + q).className = 'rounded-xl border px-5 py-2 text-sm font-bold transition bg-violet-600 text-white border-violet-600';
}
</script>
@endsection
