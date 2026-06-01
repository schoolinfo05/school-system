<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceMessage;
use App\Models\MarketplaceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MarketplaceController extends Controller
{
    // GET /api/marketplace — public browse (available only)
    public function index(Request $request)
    {
        $items = MarketplaceItem::with('seller')
            ->where('status', 'available')
            ->when($request->category, fn($q) =>
                $q->where('category', $request->category)
            )
            ->when($request->search, fn($q) =>
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
            )
            ->latest()
            ->get();

        return response()->json($items);
    }

    // POST /api/marketplace — create listing
    public function store(Request $request)
    {
        if (!$request->user()->hasAnyRole(['admin', 'registrar', 'school_management'])) {
            return response()->json([
                'message' => 'Only school management can post marketplace items.',
            ], 403);
        }

        $request->validate([
            'title'       => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:1',
            'category'    => 'required|in:books,uniforms,electronics,supplies,other',
            'condition'   => 'required|in:new,like_new,good,fair',
            'location'    => 'nullable|string',
            'accepts_cash' => 'nullable|boolean',
            'accepts_gcash' => 'nullable|boolean',
            'accepts_qrph' => 'nullable|boolean',
            'gcash_name'   => 'nullable|string|max:100',
            'gcash_number' => 'nullable|string|max:30',
            'qrph_image_url' => 'nullable|string|max:1000',
        ]);

        if (!$request->boolean('accepts_cash') && !$request->boolean('accepts_gcash') && !$request->boolean('accepts_qrph')) {
            return response()->json(['message' => 'Select at least one payment method.'], 422);
        }

        if ($request->boolean('accepts_gcash') && (!$request->filled('gcash_name') || !$request->filled('gcash_number'))) {
            return response()->json(['message' => 'GCash name and number are required for online payment.'], 422);
        }

        $item = MarketplaceItem::create([
            ...$request->only([
                'title', 'description', 'price', 'stock', 'category', 'condition',
                'location', 'accepts_cash', 'accepts_gcash', 'accepts_qrph',
                'gcash_name', 'gcash_number', 'qrph_image_url',
            ]),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($item->load('seller'), 201);
    }

    // GET /api/marketplace/{item} — single item
    public function show(MarketplaceItem $item)
    {
        return response()->json($item->load('seller'));
    }

    // POST /api/marketplace/{item}/buy — reserve item and notify seller
    public function buy(Request $request, MarketplaceItem $item)
    {
        $request->validate([
            'payment_method' => 'required|in:cash,gcash,qrph',
            'gcash_reference' => 'required_if:payment_method,gcash|required_if:payment_method,qrph|nullable|string|max:100',
            'quantity' => 'nullable|integer|min:1',
        ]);

        if ($item->user_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot buy your own listing.'], 422);
        }

        $quantity = (int) $request->input('quantity', 1);

        if ($item->status !== 'available' || $item->stock < 1) {
            return response()->json(['message' => 'This item is no longer available.'], 422);
        }

        if ($quantity > $item->stock) {
            return response()->json(['message' => "Only {$item->stock} item(s) are available."], 422);
        }

        if ($request->payment_method === 'cash' && !$item->accepts_cash) {
            return response()->json(['message' => 'This seller does not accept cash for this item.'], 422);
        }

        if ($request->payment_method === 'gcash' && !$item->accepts_gcash) {
            return response()->json(['message' => 'This seller does not accept GCash for this item.'], 422);
        }

        if ($request->payment_method === 'qrph' && !$item->accepts_qrph) {
            return response()->json(['message' => 'This seller does not accept QRPH for this item.'], 422);
        }

        $newStock = max(0, $item->stock - $quantity);
        $item->update([
            'stock' => $newStock,
            'status' => $newStock === 0 ? 'reserved' : 'available',
        ]);

        $paymentText = match ($request->payment_method) {
            'gcash' => 'GCash' . ($request->gcash_reference ? " (reference: {$request->gcash_reference})" : ''),
            'qrph' => 'QRPH' . ($request->gcash_reference ? " (reference: {$request->gcash_reference})" : ''),
            default => 'Cash on meetup',
        };

        MarketplaceMessage::create([
            'item_id'     => $item->id,
            'sender_id'   => $request->user()->id,
            'receiver_id' => $item->user_id,
            'message'     => "I want to buy {$quantity} x {$item->title}. Payment method: {$paymentText}. Please let me know how we can complete the transaction.",
        ]);

        $order = MarketplaceOrder::create([
            'marketplace_item_id' => $item->id,
            'buyer_id' => $request->user()->id,
            'seller_id' => $item->user_id,
            'quantity' => $quantity,
            'unit_price' => $item->price,
            'total_amount' => $item->price * $quantity,
            'payment_method' => $request->payment_method,
            'gcash_reference' => in_array($request->payment_method, ['gcash', 'qrph'], true) ? $request->gcash_reference : null,
            'status' => in_array($request->payment_method, ['gcash', 'qrph'], true) ? 'pending_verification' : 'reserved',
        ]);

        return response()->json([
            'item' => $item->load('seller'),
            'order' => $order->load(['item.seller', 'seller']),
        ]);
    }

    // POST /api/marketplace/{item}/paymongo-checkout — create hosted GCash checkout
    public function paymongoCheckout(Request $request, MarketplaceItem $item)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($item->user_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot buy your own listing.'], 422);
        }

        if (!$item->accepts_gcash) {
            return response()->json(['message' => 'This item does not accept GCash.'], 422);
        }

        if ($item->status !== 'available' || $item->stock < 1) {
            return response()->json(['message' => 'This item is no longer available.'], 422);
        }

        $quantity = (int) $request->quantity;
        if ($quantity > $item->stock) {
            return response()->json(['message' => "Only {$item->stock} item(s) are available."], 422);
        }

        $secretKey = config('services.paymongo.secret_key');
        if (!$secretKey) {
            return response()->json(['message' => 'PayMongo is not configured. Add PAYMONGO_SECRET_KEY to your .env file.'], 503);
        }

        $newStock = max(0, $item->stock - $quantity);
        $item->update([
            'stock' => $newStock,
            'status' => $newStock === 0 ? 'reserved' : 'available',
        ]);

        $order = MarketplaceOrder::create([
            'marketplace_item_id' => $item->id,
            'buyer_id' => $request->user()->id,
            'seller_id' => $item->user_id,
            'quantity' => $quantity,
            'unit_price' => $item->price,
            'total_amount' => $item->price * $quantity,
            'payment_method' => 'gcash',
            'status' => 'reserved',
            'paymongo_status' => 'pending',
        ]);

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'description' => "Marketplace order #{$order->id}",
                        'reference_number' => "MKT-{$order->id}",
                        'line_items' => [[
                            'currency' => 'PHP',
                            'amount' => (int) round($item->price * 100),
                            'name' => $item->title,
                            'quantity' => $quantity,
                        ]],
                        'payment_method_types' => ['gcash'],
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'success_url' => config('services.paymongo.success_url'),
                        'cancel_url' => config('services.paymongo.cancel_url'),
                        'metadata' => [
                            'marketplace_order_id' => (string) $order->id,
                            'marketplace_item_id' => (string) $item->id,
                            'buyer_id' => (string) $request->user()->id,
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $item->update([
                'stock' => $item->stock + $quantity,
                'status' => 'available',
            ]);
            $order->update([
                'status' => 'cancelled',
                'paymongo_status' => 'checkout_failed',
                'notes' => $response->json('errors.0.detail') ?? 'PayMongo checkout session could not be created.',
            ]);

            return response()->json([
                'message' => $order->notes,
            ], 422);
        }

        $session = $response->json('data');
        $attributes = $session['attributes'] ?? [];
        $checkoutUrl = $attributes['checkout_url'] ?? $attributes['url'] ?? null;

        $order->update([
            'paymongo_checkout_id' => $session['id'] ?? null,
            'checkout_url' => $checkoutUrl,
        ]);

        MarketplaceMessage::create([
            'item_id'     => $item->id,
            'sender_id'   => $request->user()->id,
            'receiver_id' => $item->user_id,
            'message'     => "I started GCash checkout for {$quantity} x {$item->title}. Order #{$order->id}.",
        ]);

        return response()->json([
            'checkout_url' => $checkoutUrl,
            'item' => $item->fresh()->load('seller'),
            'order' => $order->fresh()->load(['item.seller', 'seller']),
        ]);
    }

    // PUT /api/marketplace/{item} — full update (title, price, status, etc.)
    public function update(Request $request, MarketplaceItem $item)
    {
        if ($item->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $item->update($request->only([
            'title', 'description', 'price', 'stock', 'category', 'condition', 'status',
            'location', 'accepts_cash', 'accepts_gcash', 'accepts_qrph',
            'gcash_name', 'gcash_number', 'qrph_image_url',
        ]));
        return response()->json($item);
    }

    // DELETE /api/marketplace/{item}
    public function destroy(Request $request, MarketplaceItem $item)
    {
        if ($item->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $item->delete();
        return response()->json(['message' => 'Item deleted']);
    }

    // GET /api/marketplace/my-items — seller's own listings (all statuses)
    public function myItems(Request $request)
    {
        $items = MarketplaceItem::where('user_id', $request->user()->id)
            ->latest()
            ->get();
        return response()->json($items);
    }

    // GET /api/marketplace/my-orders — buyer checkout/reservation portal
    public function myOrders(Request $request)
    {
        $orders = MarketplaceOrder::where('buyer_id', $request->user()->id)
            ->with(['item.seller', 'seller'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // GET /api/marketplace/sales — school management sees marketplace checkouts
    public function sales(Request $request)
    {
        if (!$request->user()->hasAnyRole(['admin', 'registrar', 'school_management'])) {
            return response()->json([
                'message' => 'Only school management can view marketplace sales.',
            ], 403);
        }

        $orders = MarketplaceOrder::with(['item', 'buyer', 'seller'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // GET /api/marketplace/payment-options — app payment instructions
    public function paymentOptions(Request $request)
    {
        return response()->json([
            'qrph' => [
                'account_name' => config('services.qrph.account_name'),
                'account_number' => config('services.qrph.account_number'),
                'image_url' => config('services.qrph.image_url'),
                'instructions' => config('services.qrph.instructions'),
                'enabled' => (bool) (config('services.qrph.image_url') || config('services.qrph.account_number')),
            ],
        ]);
    }

    // POST /api/marketplace/orders/{order}/mark-paid — management verifies manual payment
    public function markOrderPaid(Request $request, MarketplaceOrder $order)
    {
        if (!$request->user()->hasAnyRole(['admin', 'registrar', 'school_management'])) {
            return response()->json(['message' => 'Only school management can verify payments.'], 403);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'Cancelled orders cannot be marked as paid.'], 422);
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        MarketplaceMessage::create([
            'item_id'     => $order->marketplace_item_id,
            'sender_id'   => $request->user()->id,
            'receiver_id' => $order->buyer_id,
            'message'     => "Your payment for {$order->item?->title} has been verified.",
        ]);

        return response()->json($order->load(['item', 'buyer', 'seller']));
    }

    // POST /api/marketplace/orders/{order}/cancel — buyer cancels checkout/reservation
    public function cancelOrder(Request $request, MarketplaceOrder $order)
    {
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['message' => 'This checkout is already cancelled.'], 422);
        }

        if ($order->status === 'completed') {
            return response()->json(['message' => 'Completed orders cannot be cancelled.'], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);

        $item = $order->item;
        if ($item) {
            $item->update([
                'stock' => $item->stock + $order->quantity,
                'status' => 'available',
            ]);
        }

        $order->update([
            'status' => 'cancelled',
            'notes' => $request->reason,
        ]);

        MarketplaceMessage::create([
            'item_id'     => $order->marketplace_item_id,
            'sender_id'   => $request->user()->id,
            'receiver_id' => $order->seller_id,
            'message'     => "I cancelled my checkout for {$order->item?->title}. Reason: {$request->reason}",
        ]);

        return response()->json($order->load(['item.seller', 'seller']));
    }

    // POST /api/paymongo/webhook — PayMongo payment confirmation
    public function paymongoWebhook(Request $request)
    {
        $payload = $request->all();
        $attributes = $payload['data']['attributes'] ?? [];
        $eventType = $attributes['type'] ?? null;
        $eventData = $attributes['data'] ?? [];
        $eventAttributes = $eventData['attributes'] ?? [];
        $metadata = $eventAttributes['metadata'] ?? [];

        $orderId = $metadata['marketplace_order_id'] ?? null;
        $referenceNumber = $eventAttributes['reference_number'] ?? $eventAttributes['external_reference_number'] ?? null;
        $checkoutId = $eventData['id'] ?? null;

        $order = $orderId
            ? MarketplaceOrder::find($orderId)
            : MarketplaceOrder::where('paymongo_checkout_id', $checkoutId)->first();

        if (!$order && is_string($referenceNumber) && str_starts_with($referenceNumber, 'MKT-')) {
            $order = MarketplaceOrder::find((int) str_replace('MKT-', '', $referenceNumber));
        }

        if (!$order) {
            return response()->json(['received' => true]);
        }

        if (in_array($eventType, ['payment.paid', 'checkout_session.payment.paid', 'checkout_session.completed'], true)) {
            $order->update([
                'status' => 'paid',
                'paymongo_status' => 'paid',
                'paymongo_payment_id' => $eventAttributes['payment_intent_id'] ?? $eventData['id'] ?? $order->paymongo_payment_id,
                'paid_at' => now(),
            ]);
        } elseif (in_array($eventType, ['payment.failed', 'checkout_session.payment.failed'], true)) {
            $order->update([
                'paymongo_status' => 'failed',
                'notes' => $eventAttributes['failed_message'] ?? 'PayMongo payment failed.',
            ]);
        }

        return response()->json(['received' => true]);
    }

    // ── MESSAGES ────────────────────────────────────────────────

    public function sendMessage(Request $request, MarketplaceItem $item)
    {
        $request->validate(['message' => 'required|string|max:500']);

        $receiverId = $item->user_id === $request->user()->id
            ? $request->receiver_id
            : $item->user_id;

        $message = MarketplaceMessage::create([
            'item_id'     => $item->id,
            'sender_id'   => $request->user()->id,
            'receiver_id' => $receiverId,
            'message'     => $request->message,
        ]);

        return response()->json($message->load('sender'), 201);
    }

    public function getMessages(Request $request, MarketplaceItem $item)
    {
        $userId = $request->user()->id;

        $messages = MarketplaceMessage::where('item_id', $item->id)
            ->where(fn($q) =>
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId)
            )
            ->with('sender')
            ->orderBy('created_at')
            ->get();

        MarketplaceMessage::where('item_id', $item->id)
            ->where('receiver_id', $userId)
            ->update(['is_read' => true]);

        return response()->json($messages);
    }

    public function myChats(Request $request)
    {
        $userId = $request->user()->id;

        $chats = MarketplaceMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['item', 'sender', 'receiver'])
            ->latest()
            ->get()
            ->groupBy('item_id')
            ->map(fn($msgs) => [
                'item'         => $msgs->first()->item,
                'last_message' => $msgs->last()->message,
                'unread'       => $msgs->where('receiver_id', $userId)->where('is_read', false)->count(),
                'other_user'   => $msgs->first()->sender_id === $userId
                    ? $msgs->first()->receiver
                    : $msgs->first()->sender,
            ])->values();

        return response()->json($chats);
    }
}
