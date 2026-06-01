<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceOrder extends Model
{
    protected $fillable = [
        'marketplace_item_id',
        'buyer_id',
        'seller_id',
        'quantity',
        'unit_price',
        'total_amount',
        'payment_method',
        'gcash_reference',
        'paymongo_checkout_id',
        'paymongo_payment_id',
        'paymongo_status',
        'checkout_url',
        'status',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(MarketplaceItem::class, 'marketplace_item_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
