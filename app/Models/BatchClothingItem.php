<?php
// app/Models/BatchClothingItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchClothingItem extends Model
{
    use HasFactory;

    protected $table = 'batch_clothing_items';

    protected $fillable = [
        'order_id',
        'clothing_type_id',
        'custom_name',
        'base_price',
        'color',
        'size_distribution',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'size_distribution' => 'array',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'base_price' => 0,
        'size_distribution' => '[]',
        'sort_order' => 0,
    ];

    protected $appends = [
        'item_name',
        'total_quantity',
        'total_price',
        'formatted_size_distribution',
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

    public function getSizeDistributionAttribute($value): array
    {
        return $this->decodeJsonAttribute($value);
    }

    private function decodeJsonAttribute($value): array
    {
        if (is_string($value) && !empty($value)) {
            try {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded ?? [];
                }
            } catch (\Exception $e) {
            }
        }
        
        return is_array($value) ? $value : [];
    }

    public function getTotalQuantityAttribute(): int
    {
        $distribution = $this->size_distribution;
        $total = 0;
        
        foreach ($distribution as $quantity) {
            $total += (int) $quantity;
        }
        
        return $total;
    }

    public function getTotalPriceAttribute(): float
    {
        $distribution = $this->size_distribution;
        $total = 0;
        
        foreach ($distribution as $size => $quantity) {
            if ($quantity > 0) {
                $surcharge = Order::calculateSizeSurcharge($size);
                $pricePerItem = $this->base_price + $surcharge;
                $total += $pricePerItem * $quantity;
            }
        }
        
        return $total;
    }

    public function getFormattedSizeDistributionAttribute(): string
    {
        $distribution = $this->size_distribution;
        if (empty($distribution)) {
            return 'Tidak ada distribusi';
        }
        
        $formatted = [];
        foreach ($distribution as $size => $quantity) {
            if ($quantity > 0) {
                $surcharge = Order::calculateSizeSurcharge($size);
                $sizeLabel = Order::getSizeLabel($size);
                if ($surcharge > 0) {
                    $formatted[] = "{$sizeLabel}: {$quantity} pcs (+" . number_format($surcharge, 0, ',', '.') . ")";
                } else {
                    $formatted[] = "{$sizeLabel}: {$quantity} pcs";
                }
            }
        }
        
        return implode(', ', $formatted);
    }

    // ========== HELPER METHODS ==========
    
    public function getSizeQuantity(string $size): int
    {
        return $this->size_distribution[$size] ?? 0;
    }

    public function setSizeQuantity(string $size, int $quantity): void
    {
        $distribution = $this->size_distribution;
        $distribution[$size] = $quantity;
        $this->size_distribution = $distribution;
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
        });
        
        static::created(function ($item) {
            // Update total price order
            if ($item->order) {
                $item->order->calculateTotalPrice();
                $item->order->save();
            }
        });
        
        static::updated(function ($item) {
            // Update total price order
            if ($item->order) {
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