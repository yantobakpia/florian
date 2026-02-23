<?php
// app/Models/OrderClothingItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderClothingItem extends Model
{
    use HasFactory;

    protected $table = 'order_clothing_items';

    protected $fillable = [
        'order_id',
        'clothing_type_id',
        'custom_name',
        'quantity',
        'size',
        'color',
        'base_price',
        'size_surcharge',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'size_surcharge' => 'decimal:2',
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'base_price' => 0,
        'size_surcharge' => 0,
        'quantity' => 1,
        'sort_order' => 0,
    ];

    protected $appends = [
        'item_name',
        'price_per_item',
        'subtotal',
        'formatted_base_price',
        'formatted_size_surcharge',
        'formatted_subtotal',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function clothingType(): BelongsTo
    {
        return $this->belongsTo(ClothingType::class);
    }

    // ========== ACCESSORS ==========
    
    public function getItemNameAttribute(): string
    {
        return $this->custom_name ?? ($this->clothingType->name ?? 'Item');
    }

    public function getPricePerItemAttribute(): float
    {
        return $this->base_price + $this->size_surcharge;
    }

    public function getSubtotalAttribute(): float
    {
        return $this->price_per_item * $this->quantity;
    }

    public function getFormattedBasePriceAttribute(): string
    {
        return 'Rp ' . number_format($this->base_price, 0, ',', '.');
    }

    public function getFormattedSizeSurchargeAttribute(): string
    {
        if ($this->size_surcharge > 0) {
            return '+ Rp ' . number_format($this->size_surcharge, 0, ',', '.');
        }
        return '-';
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    // ========== SCOPES ==========
    
    public function scopeBySize($query, $size)
    {
        return $query->where('size', $size);
    }
    
    // ========== BOOT ==========
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($item) {
            // Set sort_order jika tidak ada
            if (!$item->sort_order) {
                $maxOrder = self::where('order_id', $item->order_id)->max('sort_order');
                $item->sort_order = $maxOrder + 1;
            }
            
            // Hitung size_surcharge jika size ada
            if ($item->size && !$item->size_surcharge) {
                $item->size_surcharge = Order::calculateSizeSurcharge($item->size);
            }
        });
        
        static::created(function ($item) {
            // Update total price order
            if ($item->order) {
                $item->order->calculateTotalPrice();
                $item->order->save();
            }
        });
        
        static::updated(function ($item) {
            // Update total price order jika ada perubahan harga atau quantity
            if ($item->order && ($item->isDirty(['base_price', 'size_surcharge', 'quantity']))) {
                $item->order->calculateTotalPrice();
                $item->order->save();
            }
        });
        
        static::deleted(function ($item) {
            // Update total price order
            if ($item->order) {
                $item->order->calculateTotalPrice();
                $item->order->save();
            }
        });
    }
}