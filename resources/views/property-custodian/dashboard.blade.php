@extends('layouts.portal', ['title' => 'Property Custodian Dashboard'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Property Custodian Dashboard</h1>
    <p class="mt-1 text-sm text-slate-500">Asset inventory, assignments, and maintenance overview.</p>
</div>

<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <x-stat-card label="Assets" :value="$stats['total']" />
    <x-stat-card label="Available" :value="$stats['available']" tone="emerald" />
    <x-stat-card label="Assigned" :value="$stats['assigned']" tone="blue" />
    <x-stat-card label="Maintenance" :value="$stats['maintenance']" />
    <x-stat-card label="Needs attention" :value="$stats['needs_attention']" tone="red" />
</div>

<section class="portal-card overflow-hidden">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="font-bold text-slate-800">Recent assets</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-5 py-3">Asset</th>
                    <th class="px-5 py-3">Category</th>
                    <th class="px-5 py-3">Location</th>
                    <th class="px-5 py-3">Condition</th>
                    <th class="px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($assets as $asset)
                    <tr>
                        <td class="px-5 py-4">
                            <p class="font-bold text-slate-900">{{ $asset->name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $asset->asset_tag }}</p>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $asset->category ?: 'Uncategorized' }}</td>
                        <td class="px-5 py-4 text-slate-600">{{ $asset->location ?: 'No location' }}</td>
                        <td class="px-5 py-4">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                {{ str_replace('_', ' ', ucfirst($asset->condition)) }}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $asset->status === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ ucfirst($asset->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">No assets recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="mt-10 mb-6">
    <h2 class="text-xl font-black text-slate-900">Marketplace Control</h2>
    <p class="mt-1 text-sm text-slate-500">Post school-owned items, manage listing status, and verify buyer payments.</p>
</div>

<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-stat-card label="My listings" :value="$marketplaceStats['items']" />
    <x-stat-card label="Available" :value="$marketplaceStats['available']" tone="emerald" />
    <x-stat-card label="Pending sales" :value="$marketplaceStats['pending_sales']" tone="red" />
    <x-stat-card label="Paid sales" :value="$marketplaceStats['paid_sales']" tone="blue" />
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[380px_1fr]">
    <section class="portal-card h-fit p-5">
        <h3 class="font-bold text-slate-800">Post marketplace item</h3>
        <form method="POST" action="{{ route('property-custodian.marketplace.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
            @csrf
            <input name="title" value="{{ old('title') }}" class="portal-field w-full" placeholder="Item title" required>
            <textarea name="description" class="portal-field min-h-24 w-full" placeholder="Description" required>{{ old('description') }}</textarea>

            <div class="grid grid-cols-2 gap-3">
                <input name="price" value="{{ old('price') }}" type="number" min="0" step="0.01" class="portal-field w-full" placeholder="Price" required>
                <input name="stock" value="{{ old('stock', 1) }}" type="number" min="0" class="portal-field w-full" placeholder="Stock" required>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <select name="category" class="portal-field w-full" required>
                    @foreach(['books', 'uniforms', 'electronics', 'supplies', 'other'] as $category)
                        <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
                <select name="condition" class="portal-field w-full" required>
                    @foreach(['new' => 'New', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <input name="location" value="{{ old('location') }}" class="portal-field w-full" placeholder="Location">
            <textarea name="pickup_instructions" class="portal-field min-h-20 w-full" placeholder="Pickup / claim instructions">{{ old('pickup_instructions', 'Pay and claim this item at the Property Custodian Office. Bring your student ID and order number.') }}</textarea>

            <div class="space-y-2 rounded-lg bg-slate-50 p-3 text-sm font-semibold text-slate-700">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="accepts_cash" value="1" checked class="rounded border-slate-300">
                    Cash
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="accepts_gcash" value="1" class="rounded border-slate-300">
                    GCash
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="accepts_qrph" value="1" class="rounded border-slate-300">
                    QRPH
                </label>
            </div>

            <input name="gcash_name" value="{{ old('gcash_name') }}" class="portal-field w-full" placeholder="GCash account name">
            <input name="gcash_number" value="{{ old('gcash_number') }}" class="portal-field w-full" placeholder="GCash number">
            <input name="qrph_image_url" value="{{ old('qrph_image_url') }}" class="portal-field w-full" placeholder="QRPH image URL">
            <div>
                <label class="text-xs font-bold uppercase tracking-wider text-slate-400">Item photos</label>
                <input name="item_images[]" type="file" accept="image/*" multiple class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-bold file:text-white">
                <p class="mt-1 text-xs text-slate-500">Upload up to 3 images.</p>
            </div>

            <button class="portal-button-primary w-full" type="submit">Post item</button>
        </form>
    </section>

    <div class="space-y-6" x-data="{ previewImages: [], previewIndex: 0, previewTitle: '', get previewImage() { return this.previewImages[this.previewIndex] || null } }">
        <section class="portal-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="font-bold text-slate-800">My marketplace listings</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($marketplaceItems as $item)
                    @php
                        $itemImages = collect($item->image_urls ?? [])
                            ->filter()
                            ->when(empty($item->image_urls) && $item->image, fn ($images) => $images->push($item->image))
                            ->values();
                        $itemImage = $itemImages->first();
                    @endphp
                    <div class="grid gap-3 px-5 py-4 lg:grid-cols-[88px_1fr_120px_110px_130px_auto_auto] lg:items-center">
                        <div class="space-y-1">
                            <div class="h-16 w-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                @if($itemImage)
                                    <button type="button" class="relative block h-full w-full" title="Preview images" @click="previewImages = @js($itemImages); previewIndex = 0; previewTitle = @js($item->title)">
                                        <img src="{{ $itemImage }}" alt="{{ $item->title }}" class="h-full w-full object-cover transition hover:scale-105">
                                        @if($itemImages->count() > 1)
                                            <span class="absolute bottom-1 right-1 rounded bg-slate-950/80 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $itemImages->count() }} photos</span>
                                        @endif
                                    </button>
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs font-bold text-slate-400">No image</div>
                                @endif
                            </div>
                            @if($itemImages->count() > 1)
                                <div class="flex gap-1">
                                    @foreach($itemImages->take(3) as $image)
                                        <button type="button" class="h-5 w-6 overflow-hidden rounded border border-slate-200" @click="previewImages = @js($itemImages); previewIndex = {{ $loop->index }}; previewTitle = @js($item->title)">
                                            <img src="{{ $image }}" alt="{{ $item->title }} photo {{ $loop->iteration }}" class="h-full w-full object-cover">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('property-custodian.marketplace.update', $item) }}" enctype="multipart/form-data" class="contents">
                            @csrf
                            @method('PUT')
                            <div>
                                <input name="title" value="{{ $item->title }}" class="portal-field w-full font-bold" required>
                                <input name="description" value="{{ $item->description }}" class="portal-field mt-2 w-full" required>
                                <input name="item_images[]" type="file" accept="image/*" multiple class="mt-2 block w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-2 file:py-1.5 file:text-xs file:font-bold file:text-slate-700">
                                <p class="mt-1 text-xs text-slate-400">Adds new photos until the item has 3 total.</p>
                                <input type="hidden" name="category" value="{{ $item->category }}">
                                <input type="hidden" name="condition" value="{{ $item->condition }}">
                                <input type="hidden" name="location" value="{{ $item->location }}">
                                <input type="hidden" name="pickup_instructions" value="{{ $item->pickup_instructions }}">
                                <input type="hidden" name="accepts_cash" value="{{ $item->accepts_cash ? 1 : 0 }}">
                                <input type="hidden" name="accepts_gcash" value="{{ $item->accepts_gcash ? 1 : 0 }}">
                                <input type="hidden" name="accepts_qrph" value="{{ $item->accepts_qrph ? 1 : 0 }}">
                                <input type="hidden" name="gcash_name" value="{{ $item->gcash_name }}">
                                <input type="hidden" name="gcash_number" value="{{ $item->gcash_number }}">
                                <input type="hidden" name="qrph_image_url" value="{{ $item->qrph_image_url }}">
                                <p class="mt-1 text-xs text-slate-500">{{ ucfirst($item->category) }} / {{ $item->orders_count }} order(s)</p>
                            </div>
                            <input name="price" value="{{ $item->price }}" type="number" min="0" step="0.01" class="portal-field w-full" required>
                            <input name="stock" value="{{ $item->stock }}" type="number" min="0" class="portal-field w-full" required>
                            <select name="status" class="portal-field w-full" required>
                                @foreach(['available', 'reserved', 'sold'] as $status)
                                    <option value="{{ $status }}" @selected($item->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            <button class="portal-button-secondary" type="submit">Save</button>
                        </form>
                        <form method="POST" action="{{ route('property-custodian.marketplace.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button class="portal-button border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="submit">Delete</button>
                        </form>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No marketplace items posted yet.</p>
                @endforelse
            </div>
        </section>

        <div x-show="previewImage" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4" @click.self="previewImages = []">
            <div class="w-full max-w-3xl overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <p class="truncate text-sm font-bold text-slate-900" x-text="previewTitle"></p>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-bold text-slate-500 hover:bg-slate-100 hover:text-slate-900" @click="previewImages = []">
                        Close
                    </button>
                </div>
                <div class="bg-slate-100 p-3">
                    <img :src="previewImage" :alt="previewTitle" class="max-h-[68vh] w-full rounded-lg object-contain">
                    <div class="mt-3 flex items-center justify-center gap-2" x-show="previewImages.length > 1">
                        <button type="button" class="rounded-lg bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-sm" @click="previewIndex = (previewIndex - 1 + previewImages.length) % previewImages.length">
                            Previous
                        </button>
                        <template x-for="(image, index) in previewImages" :key="image">
                            <button type="button" class="h-10 w-12 overflow-hidden rounded border-2" :class="index === previewIndex ? 'border-slate-950' : 'border-white'" @click="previewIndex = index">
                                <img :src="image" :alt="previewTitle" class="h-full w-full object-cover">
                            </button>
                        </template>
                        <button type="button" class="rounded-lg bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-sm" @click="previewIndex = (previewIndex + 1) % previewImages.length">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <section class="portal-card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="font-bold text-slate-800">Marketplace sales</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($marketplaceOrders as $order)
                    @php
                        $paymentMethod = strtoupper((string) $order->payment_method);
                        $referenceLabel = $order->payment_method === 'qrph' ? 'QRPH reference' : 'GCash reference';
                        $isReceived = $order->status === 'completed';
                    @endphp
                    <div class="px-5 py-4">
                        <div class="grid gap-3 lg:grid-cols-[1fr_140px_140px_auto] lg:items-center">
                            <div>
                                <p class="font-bold text-slate-900">{{ $order->item?->title ?? 'Marketplace item' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $order->buyer?->name ?? 'Buyer' }} / {{ $paymentMethod }} / Order #{{ str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) }}</p>
                            </div>
                            <p class="font-black text-slate-900">PHP {{ number_format((float) $order->total_amount, 2) }}</p>
                            <span class="w-fit rounded-full px-2.5 py-1 text-xs font-bold {{ $isReceived ? 'bg-emerald-100 text-emerald-700' : ($order->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700') }}">
                                {{ $isReceived ? 'Received' : str_replace('_', ' ', ucfirst($order->status)) }}
                            </span>
                            @if(!in_array($order->status, ['paid', 'completed', 'cancelled'], true))
                                <form method="POST" action="{{ route('property-custodian.marketplace.orders.mark-paid', $order) }}">
                                    @csrf
                                    <button class="portal-button-primary" type="submit">Mark paid</button>
                                </form>
                            @endif
                        </div>

                        <div class="mt-3 grid gap-3 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <p class="font-bold uppercase tracking-wider text-slate-400">Buyer</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ $order->buyer?->name ?? 'Buyer' }}</p>
                            </div>
                            <div>
                                <p class="font-bold uppercase tracking-wider text-slate-400">Payment</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ $paymentMethod }}</p>
                            </div>
                            <div>
                                <p class="font-bold uppercase tracking-wider text-slate-400">Reference</p>
                                <p class="mt-1 font-semibold text-slate-800">
                                    @if(in_array($order->payment_method, ['gcash', 'qrph'], true))
                                        {{ $order->gcash_reference ?: 'No reference submitted' }}
                                    @else
                                        Cash on pickup
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="font-bold uppercase tracking-wider text-slate-400">Received</p>
                                <p class="mt-1 font-semibold {{ $isReceived ? 'text-emerald-700' : 'text-slate-800' }}">{{ $isReceived ? 'Student received item' : 'Waiting for student confirmation' }}</p>
                            </div>
                            @if(in_array($order->payment_method, ['gcash', 'qrph'], true))
                                <div class="sm:col-span-2 lg:col-span-4">
                                    <p class="font-bold uppercase tracking-wider text-slate-400">{{ $referenceLabel }}</p>
                                    <p class="mt-1 rounded-lg bg-white px-3 py-2 font-mono text-sm font-bold text-slate-900">{{ $order->gcash_reference ?: 'No reference submitted' }}</p>
                                </div>
                            @endif
                            @if(in_array($order->payment_method, ['cash', 'gcash', 'qrph'], true))
                                <div class="sm:col-span-2 lg:col-span-4">
                                    <p class="font-bold uppercase tracking-wider text-slate-400">{{ $order->payment_method === 'cash' ? 'Pickup instructions' : 'Pickup after payment verification' }}</p>
                                    <p class="mt-1 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-800">{{ $order->item?->pickup_instructions ?: 'Pay and claim this item at the Property Custodian Office. Bring your student ID and order number.' }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-500">No marketplace sales yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
