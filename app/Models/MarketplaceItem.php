<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceItem extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'price',
        'stock', 'category', 'condition', 'status', 'image', 'location',
        'accepts_cash', 'accepts_gcash', 'accepts_qrph', 'gcash_name', 'gcash_number', 'qrph_image_url','image_urls',
    ];

    protected $casts = [
        'image_urls'   => 'array',
        'accepts_cash' => 'boolean',
        'accepts_gcash' => 'boolean',
        'accepts_qrph' => 'boolean',
        'stock' => 'integer',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(MarketplaceMessage::class, 'item_id');
    }

    public function orders()
    {
        return $this->hasMany(MarketplaceOrder::class, 'marketplace_item_id');
    }
}
