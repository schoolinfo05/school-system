@extends('layouts.portal', ['title' => 'Market'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Market</h1>
    <p class="mt-1 text-sm text-slate-500">Browse approved school marketplace items.</p>
</div>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse($items as $item)
        <section class="portal-card overflow-hidden">
            <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-black text-slate-900">{{ $item->title }}</p>
                        <p class="mt-1 text-xs font-bold uppercase text-slate-400">{{ ucfirst(str_replace('_', ' ', $item->category)) }} · {{ ucfirst(str_replace('_', ' ', $item->condition)) }}</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">Stock {{ $item->stock }}</span>
                </div>
                <p class="mt-3 text-sm text-slate-600">{{ $item->description }}</p>
                <p class="mt-4 text-xl font-black text-violet-700">PHP {{ number_format((float) $item->price, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">Seller: {{ $item->seller?->name ?? 'School' }}</p>
            </div>
        </section>
    @empty
        <section class="portal-card p-10 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">
            No marketplace items available.
        </section>
    @endforelse
</div>

<div class="mt-5">{{ $items->links() }}</div>
@endsection
