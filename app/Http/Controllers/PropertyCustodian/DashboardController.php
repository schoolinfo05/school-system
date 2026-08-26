<?php

namespace App\Http\Controllers\PropertyCustodian;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceOrder;
use App\Models\PropertyAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $stats = [
            'total' => PropertyAsset::count(),
            'available' => PropertyAsset::where('status', 'available')->count(),
            'assigned' => PropertyAsset::where('status', 'assigned')->count(),
            'maintenance' => PropertyAsset::where('status', 'maintenance')->count(),
            'needs_attention' => PropertyAsset::whereIn('condition', ['needs_repair', 'damaged', 'lost'])->count(),
        ];

        $assets = PropertyAsset::latest()->limit(25)->get();

        $marketplaceItems = MarketplaceItem::withCount('orders')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(12)
            ->get();

        $marketplaceOrders = MarketplaceOrder::with(['item', 'buyer'])
            ->where('seller_id', $user->id)
            ->latest()
            ->limit(12)
            ->get();

        $marketplaceStats = [
            'items' => MarketplaceItem::where('user_id', $user->id)->count(),
            'available' => MarketplaceItem::where('user_id', $user->id)->where('status', 'available')->count(),
            'pending_sales' => MarketplaceOrder::where('seller_id', $user->id)->whereIn('status', ['reserved', 'pending_verification'])->count(),
            'paid_sales' => MarketplaceOrder::where('seller_id', $user->id)->where('status', 'paid')->count(),
        ];

        return view('property-custodian.dashboard', compact(
            'stats',
            'assets',
            'marketplaceItems',
            'marketplaceOrders',
            'marketplaceStats',
        ));
    }

    public function storeMarketplaceItem(Request $request): RedirectResponse
    {
        $data = $request->validate($this->marketplaceRules());
        $this->validatePaymentDetails($request);
        $imageUrls = $this->storeMarketplaceImages($request);
        unset($data['item_images']);

        MarketplaceItem::create([
            ...$data,
            'user_id' => $request->user()->id,
            'image' => $imageUrls[0] ?? null,
            'image_urls' => $imageUrls,
            'pickup_instructions' => $this->pickupInstructionsFor($request),
            'accepts_cash' => $request->boolean('accepts_cash'),
            'accepts_gcash' => $request->boolean('accepts_gcash'),
            'accepts_qrph' => $request->boolean('accepts_qrph'),
        ]);

        return redirect()->route('property-custodian.dashboard')->with('status', 'Marketplace item posted.');
    }

    public function updateMarketplaceItem(Request $request, MarketplaceItem $item): RedirectResponse
    {
        $this->authorizeMarketplaceItem($request, $item);

        $data = $request->validate($this->marketplaceRules(true));
        $this->validatePaymentDetails($request);
        $imageUrls = $item->image_urls ?? [];
        unset($data['item_images']);

        if ($request->hasFile('item_images')) {
            $imageUrls = collect($imageUrls)
                ->merge($this->storeMarketplaceImages($request))
                ->filter()
                ->unique()
                ->take(3)
                ->values()
                ->all();
        }

        $item->update([
            ...$data,
            'image' => $imageUrls[0] ?? $item->image,
            'image_urls' => $imageUrls,
            'pickup_instructions' => $this->pickupInstructionsFor($request),
            'accepts_cash' => $request->boolean('accepts_cash'),
            'accepts_gcash' => $request->boolean('accepts_gcash'),
            'accepts_qrph' => $request->boolean('accepts_qrph'),
        ]);

        return redirect()->route('property-custodian.dashboard')->with('status', 'Marketplace item updated.');
    }

    public function destroyMarketplaceItem(Request $request, MarketplaceItem $item): RedirectResponse
    {
        $this->authorizeMarketplaceItem($request, $item);
        $item->delete();

        return redirect()->route('property-custodian.dashboard')->with('status', 'Marketplace item removed.');
    }

    public function markOrderPaid(Request $request, MarketplaceOrder $order): RedirectResponse
    {
        if ($order->seller_id !== $request->user()->id && $request->user()->role !== 'admin') {
            abort(403);
        }

        if ($order->status !== 'cancelled') {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        return redirect()->route('property-custodian.dashboard')->with('status', 'Marketplace payment verified.');
    }

    private function marketplaceRules(bool $updating = false): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'category' => ['required', Rule::in(['books', 'uniforms', 'electronics', 'supplies', 'other'])],
            'condition' => ['required', Rule::in(['new', 'like_new', 'good', 'fair'])],
            'status' => [$updating ? 'required' : 'nullable', Rule::in(['available', 'reserved', 'sold'])],
            'location' => ['nullable', 'string', 'max:255'],
            'pickup_instructions' => ['nullable', 'string', 'max:1000'],
            'accepts_cash' => ['nullable', 'boolean'],
            'accepts_gcash' => ['nullable', 'boolean'],
            'accepts_qrph' => ['nullable', 'boolean'],
            'gcash_name' => ['nullable', 'string', 'max:100'],
            'gcash_number' => ['nullable', 'string', 'max:30'],
            'qrph_image_url' => ['nullable', 'string', 'max:1000'],
            'item_images' => ['nullable', 'array', 'max:3'],
            'item_images.*' => ['image', 'max:4096'],
        ];
    }

    private function authorizeMarketplaceItem(Request $request, MarketplaceItem $item): void
    {
        if ($item->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            abort(403);
        }
    }

    private function validatePaymentDetails(Request $request): void
    {
        if (!$request->boolean('accepts_cash') && !$request->boolean('accepts_gcash') && !$request->boolean('accepts_qrph')) {
            throw ValidationException::withMessages(['accepts_cash' => 'Select at least one payment method.']);
        }

        if ($request->boolean('accepts_gcash') && (!$request->filled('gcash_name') || !$request->filled('gcash_number'))) {
            throw ValidationException::withMessages(['gcash_name' => 'GCash name and number are required when GCash is enabled.']);
        }

        if ($request->boolean('accepts_qrph') && !$request->filled('qrph_image_url')) {
            throw ValidationException::withMessages(['qrph_image_url' => 'QRPH image URL is required when QRPH is enabled.']);
        }
    }

    private function pickupInstructionsFor(Request $request): ?string
    {
        $instructions = trim((string) $request->input('pickup_instructions', ''));
        if ($instructions !== '') {
            return $instructions;
        }

        if ($request->boolean('accepts_cash') || $request->boolean('accepts_gcash') || $request->boolean('accepts_qrph')) {
            return 'Pay and claim this item at the Property Custodian Office. Bring your student ID and order number.';
        }

        return null;
    }

    private function storeMarketplaceImages(Request $request): array
    {
        if (!$request->hasFile('item_images')) {
            return [];
        }

        return collect($request->file('item_images'))
            ->take(3)
            ->map(fn ($image) => url(Storage::url($image->store('marketplace-images', 'public'))))
            ->values()
            ->all();
    }
}
