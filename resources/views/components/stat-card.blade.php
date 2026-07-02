@props(['label', 'value', 'tone' => 'slate'])
@php
    $colors = [
        'slate' => 'text-slate-900',
        'emerald' => 'text-emerald-700',
        'blue' => 'text-blue-700',
        'red' => 'text-red-600',
    ];
@endphp
<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <p class="text-xs uppercase tracking-wide font-bold text-slate-400">{{ $label }}</p>
    <p class="mt-2 text-2xl font-black {{ $colors[$tone] ?? $colors['slate'] }}">{{ $value }}</p>
</div>
