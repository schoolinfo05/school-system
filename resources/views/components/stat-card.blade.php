@props(['label', 'value', 'tone' => 'slate'])
@php
    $colors = [
        'slate' => ['text' => 'text-slate-900', 'dot' => 'bg-slate-400'],
        'emerald' => ['text' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
        'blue' => ['text' => 'text-blue-700', 'dot' => 'bg-blue-500'],
        'red' => ['text' => 'text-red-600', 'dot' => 'bg-red-500'],
    ];
    $color = $colors[$tone] ?? $colors['slate'];
@endphp
<div class="portal-card p-5">
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p>
        <span class="h-2 w-2 rounded-full {{ $color['dot'] }}"></span>
    </div>
    <p class="mt-3 text-2xl font-black {{ $color['text'] }}">{{ $value }}</p>
</div>
