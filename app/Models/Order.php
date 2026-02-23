<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'clothing_type_id',
        'custom_clothing_type',
        'size',
        'size_surcharge',
        'color',
        'quantity',
        'material_needed',
        'base_price',
        'additional_fees_items',
        'additional_fees_total',
        'discount',
        'total_price',
        'dp_paid',
        'payment_status',
        'payment_method',
        'order_status',
        'priority',
        'order_date',
        'due_date',
        'start_date',
        'completion_date',
        'tailor_id',
        'measurement_notes',
        'production_notes',
        'customer_notes',
        'internal_notes',
        'payment_notes',
        'reference_image',
        'mockup_image',
        'is_batch',
        'is_batch_multi_item',
        'batch_items_data',
        'batch_additional_fees_data',
        'group_name',
        'batch_color',
    ];

    protected $casts = [
        'order_date' => 'date',
        'due_date' => 'date',
        'start_date' => 'date',
        'completion_date' => 'date',
        'base_price' => 'decimal:2',
        'size_surcharge' => 'decimal:2',
        'additional_fees_items' => 'array',
        'additional_fees_total' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'dp_paid' => 'decimal:2',
        'material_needed' => 'decimal:2',
        'is_batch' => 'boolean',
        'is_batch_multi_item' => 'boolean',
        'batch_items_data' => 'array',
        'batch_additional_fees_data' => 'array',
    ];

    protected $attributes = [
        'material_needed' => 0,
        'base_price' => 0,
        'size_surcharge' => 0,
        'additional_fees_total' => 0,
        'discount' => 0,
        'total_price' => 0,
        'dp_paid' => 0,
        'quantity' => 1,
        'is_batch' => false,
        'is_batch_multi_item' => false,
        'additional_fees_items' => '[]',
        'batch_items_data' => '[]',
        'batch_additional_fees_data' => '[]',
    ];

    protected $appends = [
        'clothing_type_display',
        'remaining_payment',
        'is_overdue',
        'days_remaining',
        'total_paid',
        'total_refund',
        'net_paid',
        'is_fully_paid',
        'has_down_payment',
        'payment_status_label',
        'order_status_label',
        'payment_status_color',
        'order_status_color',
        'formatted_size_surcharge',
        'formatted_additional_fees',
        'batch_summary',
        'batch_type_label',
    ];

    // ========== MUTATORS ==========
    
    public function setAdditionalFeesItemsAttribute($value): void
    {
        $this->attributes['additional_fees_items'] = is_array($value) 
            ? json_encode($value) 
            : $value;
    }

    public function setBatchItemsDataAttribute($value): void
    {
        $this->attributes['batch_items_data'] = is_array($value) 
            ? json_encode($value) 
            : $value;
    }

    public function setBatchAdditionalFeesDataAttribute($value): void
    {
        $this->attributes['batch_additional_fees_data'] = is_array($value) 
            ? json_encode($value) 
            : $value;
    }

    // ========== ACCESSORS ==========
    
    public function getAdditionalFeesItemsAttribute($value): array
    {
        return $this->decodeJsonAttribute($value);
    }

    public function getAdditionalFeesAttribute($value): array
    {
        return $this->additional_fees_items;
    }

    public function getBatchItemsDataAttribute($value): array
    {
        return $this->decodeJsonAttribute($value);
    }

    public function getBatchAdditionalFeesDataAttribute($value): array
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

    public function getClothingTypeDisplayAttribute(): string
    {
        // Null check
        if (!$this) {
            return 'N/A';
        }
        
        if ($this->is_batch) {
            if ($this->is_batch_multi_item) {
                return "Batch Multi-Jenis ({$this->group_name})";
            }
            return $this->batch_summary;
        }
        
        if ($this->custom_clothing_type) {
            return $this->custom_clothing_type;
        }
        
        if ($this->clothingType) {
            return $this->clothingType->name;
        }
        
        return 'Custom';
    }

    public function getBatchTypeLabelAttribute(): string
    {
        // Null check
        if (!$this) {
            return 'Unknown';
        }
        
        if (!$this->is_batch) {
            return 'Single Order';
        }
        
        return $this->is_batch_multi_item ? 'Batch Multi-Jenis' : 'Batch Single Jenis';
    }

    public function getBatchSummaryAttribute(): string
    {
        // Null check
        if (!$this) {
            return 'N/A';
        }
        
        if (!$this->is_batch) {
            return 'Single Order';
        }
        
        $totalQty = $this->quantity ?? 0;
        $groupName = $this->group_name ?? 'Batch Order';
        
        if ($this->is_batch_multi_item) {
            $itemsCount = $this->batchClothingItems()->count();
            return "{$groupName} - {$itemsCount} jenis, {$totalQty} pcs";
        }
        
        return "{$groupName} - {$totalQty} pcs";
    }

    public function getRemainingPaymentAttribute(): float
    {
        return $this ? max(0, (float) $this->total_price - (float) $this->net_paid) : 0;
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this || !$this->due_date) {
            return false;
        }
        
        $isActive = !in_array($this->order_status, ['completed', 'cancelled']);
        return $isActive && $this->due_date->isPast();
    }

    public function getDaysRemainingAttribute(): int
    {
        if (!$this || !$this->due_date) {
            return 0;
        }
        
        return max(0, now()->diffInDays($this->due_date, false));
    }

    public function getTotalPaidAttribute(): float
    {
        if (!$this) return 0;
        
        $payments = $this->payments ?? collect();
        return $payments->sum('amount');
    }

    public function getTotalRefundAttribute(): float
    {
        if (!$this) return 0;
        
        $refunds = $this->refunds ?? collect();
        return $refunds->sum('amount');
    }

    public function getNetPaidAttribute(): float
    {
        if (!$this) return 0;
        
        return max(0, (float) $this->total_paid - (float) $this->total_refund);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        if (!$this) return false;
        
        return $this->net_paid >= (float) $this->total_price;
    }

    public function getHasDownPaymentAttribute(): bool
    {
        return $this && (float) $this->dp_paid > 0;
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        if (!$this) return 'Unknown';
        
        return match($this->payment_status) {
            'paid' => 'LUNAS',
            'dp' => 'DP',
            'partial' => 'CICILAN',
            'unpaid' => 'BELUM BAYAR',
            default => strtoupper($this->payment_status),
        };
    }

    public function getOrderStatusLabelAttribute(): string
    {
        if (!$this) return 'Unknown';
        
        return match($this->order_status) {
            'pending' => 'PENDING',
            'design_review' => 'REVIEW DESAIN',
            'measurement' => 'PENGUKURAN',
            'cutting' => 'PEMOTONGAN',
            'sewing' => 'PENJAHITAN',
            'finishing' => 'FINISHING',
            'ready' => 'SIAP DIAMBIL',
            'completed' => 'SELESAI',
            'cancelled' => 'DIBATALKAN',
            default => strtoupper($this->order_status),
        };
    }

    public function getPaymentStatusColorAttribute(): string
    {
        if (!$this) return 'gray';
        
        return match($this->payment_status) {
            'paid' => 'success',
            'dp' => 'warning',
            'partial' => 'info',
            'unpaid' => 'danger',
            default => 'gray',
        };
    }

    public function getOrderStatusColorAttribute(): string
    {
        if (!$this) return 'gray';
        
        return match($this->order_status) {
            'completed' => 'success',
            'ready' => 'info',
            'cancelled' => 'danger',
            'pending' => 'secondary',
            default => 'warning',
        };
    }

    public function getFormattedSizeSurchargeAttribute(): string
    {
        if (!$this || !$this->size_surcharge) {
            return 'Rp 0';
        }
        
        return 'Rp ' . number_format($this->size_surcharge, 0, ',', '.');
    }

    public function getFormattedAdditionalFeesAttribute(): array
    {
        if (!$this) return [];
        
        $formatted = [];
        $additionalFees = $this->additional_fees_items ?? [];
        
        foreach ($additionalFees as $fee) {
            $formatted[] = [
                'name' => $fee['name'] ?? 'Biaya Tambahan',
                'amount' => 'Rp ' . number_format($fee['amount'] ?? 0, 0, ',', '.'),
                'type' => $fee['type'] ?? 'other',
            ];
        }
        
        return $formatted;
    }

    // ========== RELATIONSHIPS ==========
    
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function clothingType(): BelongsTo
    {
        return $this->belongsTo(ClothingType::class);
    }

    /**
     * Relationship dengan batch clothing items (UNTUK BATCH MULTI-ITEM)
     */
    public function batchClothingItems(): HasMany
    {
        return $this->hasMany(BatchClothingItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('payment_date');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('refund_date');
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function tailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tailor_id');
    }

    public function costCalculations(): HasMany
    {
        return $this->hasMany(CostCalculation::class);
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    // ========== BUSINESS LOGIC ==========
    
    public static function calculateSizeSurcharge(string $size): float
    {
        return match(strtoupper($size)) {
            'XXL' => 5000,
            'XXXL', '3XL' => 10000,
            '4XL' => 15000,
            '5XL' => 20000,
            '6XL' => 25000,
            '7XL' => 30000,
            default => 0,
        };
    }

    public static function calculateSizePrice(float $basePrice, string $size): float
    {
        $surcharge = self::calculateSizeSurcharge($size);
        return $basePrice + $surcharge;
    }

    public static function getSizeLabel(string $size): string
    {
        $labels = [
            'XS' => 'XS',
            'S' => 'S',
            'M' => 'M',
            'L' => 'L',
            'XL' => 'XL',
            'XXL' => 'XXL (+5,000)',
            'XXXL' => 'XXXL/3XL (+10,000)',
            '3XL' => 'XXXL/3XL (+10,000)',
            '4XL' => '4XL (+15,000)',
            '5XL' => '5XL (+20,000)',
            '6XL' => '6XL (+25,000)',
            '7XL' => '7XL (+30,000)',
        ];
        
        return $labels[$size] ?? $size;
    }

    public static function getSizeColor(string $size): string
    {
        return match(strtoupper($size)) {
            'XS', 'S' => 'gray',
            'M', 'L' => 'success',
            'XL', 'XXL' => 'warning',
            'XXXL', '3XL', '4XL', '5XL', '6XL', '7XL' => 'danger',
            default => 'gray',
        };
    }

    public function calculateTotalPrice(): float
    {
        if (!$this) return 0;
        
        if ($this->is_batch) {
            if ($this->is_batch_multi_item) {
                return $this->calculateBatchMultiItemTotalPrice();
            }
            return $this->calculateBatchSingleItemTotalPrice();
        }
        
        // Single order calculation
        $sizeSurcharge = self::calculateSizeSurcharge($this->size);
        $this->size_surcharge = $sizeSurcharge;
        
        $additionalFeesTotal = $this->additional_fees_total;
        
        $basePrice = (float) $this->base_price;
        $quantity = (int) $this->quantity;
        $discount = (float) $this->discount;
        
        $pricePerItem = $basePrice + $sizeSurcharge;
        $subtotal = $pricePerItem * $quantity;
        $total = $subtotal + $additionalFeesTotal - $discount;
        
        $this->total_price = max(0, round($total, 2));
        
        return $this->total_price;
    }
    
    /**
     * Hitung total untuk batch dengan SATU jenis pakaian
     */
    private function calculateBatchSingleItemTotalPrice(): float
    {
        if (!$this) return 0;
        
        $batchItems = $this->batch_items_data;
        $batchAdditionalFees = $this->batch_additional_fees_data;
        
        $totalQuantity = 0;
        $totalPrice = 0;
        $additionalTotal = 0;
        
        foreach ($batchItems as $item) {
            $size = $item['size'] ?? 'M';
            $quantity = intval($item['quantity'] ?? 0);
            $pricePerItem = self::calculateSizePrice($this->base_price, $size);
            
            $totalQuantity += $quantity;
            $totalPrice += ($pricePerItem * $quantity);
        }
        
        foreach ($batchAdditionalFees as $fee) {
            $additionalTotal += floatval($fee['amount'] ?? 0);
        }
        
        $this->quantity = $totalQuantity;
        $this->additional_fees_total = $additionalTotal;
        
        $total = max(0, $totalPrice + $additionalTotal - (float) $this->discount);
        $this->total_price = round($total, 2);
        
        return $this->total_price;
    }
    
    /**
     * Hitung total untuk batch dengan MULTI jenis pakaian
     */
    private function calculateBatchMultiItemTotalPrice(): float
    {
        if (!$this) return 0;
        
        $batchClothingItems = $this->batchClothingItems;
        $batchAdditionalFees = $this->batch_additional_fees_data;
        
        $totalPrice = 0;
        $totalQuantity = 0;
        $additionalTotal = 0;
        
        // Hitung per jenis pakaian dalam batch
        foreach ($batchClothingItems as $item) {
            $basePrice = $item->base_price;
            $sizeDistribution = $item->size_distribution ?? [];
            
            foreach ($sizeDistribution as $size => $quantity) {
                if ($quantity > 0) {
                    $surcharge = self::calculateSizeSurcharge($size);
                    $pricePerItem = $basePrice + $surcharge;
                    $subtotal = $pricePerItem * $quantity;
                    
                    $totalPrice += $subtotal;
                    $totalQuantity += $quantity;
                }
            }
        }
        
        // Hitung biaya tambahan
        foreach ($batchAdditionalFees as $fee) {
            $additionalTotal += floatval($fee['amount'] ?? 0);
        }
        
        $this->quantity = $totalQuantity;
        $this->additional_fees_total = $additionalTotal;
        
        $total = max(0, $totalPrice + $additionalTotal - (float) $this->discount);
        $this->total_price = round($total, 2);
        
        return $this->total_price;
    }

    /**
     * Get batch items summary untuk display
     */
    public function getBatchMultiItemSummary(): array
    {
        if (!$this || !$this->is_batch || !$this->is_batch_multi_item) {
            return [];
        }
        
        $summary = [];
        $items = $this->batchClothingItems;
        
        foreach ($items as $item) {
            $itemSummary = [
                'name' => $item->item_name,
                'color' => $item->color ?? $this->batch_color,
                'base_price' => $item->base_price,
                'total_quantity' => 0,
                'size_distribution' => [],
                'total_price' => 0,
            ];
            
            $sizeDistribution = $item->size_distribution ?? [];
            foreach ($sizeDistribution as $size => $quantity) {
                if ($quantity > 0) {
                    $surcharge = self::calculateSizeSurcharge($size);
                    $pricePerItem = $item->base_price + $surcharge;
                    $subtotal = $pricePerItem * $quantity;
                    
                    $itemSummary['total_quantity'] += $quantity;
                    $itemSummary['size_distribution'][$size] = $quantity;
                    $itemSummary['total_price'] += $subtotal;
                }
            }
            
            $summary[] = $itemSummary;
        }
        
        return $summary;
    }

    // ========== BOOT METHOD ==========
    
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($order) {
            // Null check
            if (!$order) {
                return;
            }
            
            // SET DEFAULT VALUES JIKA NULL
            $defaultValues = [
                'material_needed' => 0,
                'base_price' => 0,
                'size_surcharge' => 0,
                'additional_fees_total' => 0,
                'discount' => 0,
                'total_price' => 0,
                'dp_paid' => 0,
                'quantity' => 1,
                'is_batch' => false,
                'is_batch_multi_item' => false,
            ];
            
            foreach ($defaultValues as $field => $default) {
                if (is_null($order->{$field})) {
                    $order->{$field} = $default;
                }
            }
            
            // Generate order number jika baru dibuat
            if (!$order->order_number && !$order->exists) {
                $yearMonth = now()->format('Ym');
                $lastOrder = self::withTrashed()
                    ->where('order_number', 'LIKE', "ORD-{$yearMonth}-%")
                    ->orderBy('order_number', 'desc')
                    ->first();
                
                if ($lastOrder) {
                    $lastNumber = (int) substr($lastOrder->order_number, -3);
                    $number = $lastNumber + 1;
                } else {
                    $number = 1;
                }
                
                $order->order_number = sprintf('ORD-%s-%03d', $yearMonth, $number);
            }
            
            // Set default dates jika tidak ada
            if (!$order->order_date) {
                $order->order_date = now();
            }
            
            if (!$order->due_date) {
                $order->due_date = now()->addDays(7);
            }
            
            // Hitung total price
            $order->calculateTotalPrice();
            
            // Untuk batch multi-item, update quantity total
            if ($order->is_batch && $order->is_batch_multi_item) {
                $totalQuantity = 0;
                foreach ($order->batchClothingItems as $item) {
                    $sizeDistribution = $item->size_distribution ?? [];
                    foreach ($sizeDistribution as $quantity) {
                        $totalQuantity += $quantity;
                    }
                }
                $order->quantity = $totalQuantity;
            }
        });
        
        static::deleting(function ($order) {
            // Null check
            if (!$order) {
                return;
            }
            
            // Hapus batch clothing items
            $order->batchClothingItems()->delete();
        });
        
        static::restored(function ($order) {
            // Null check
            if (!$order) {
                return;
            }
            
            // Restore batch clothing items
            $order->batchClothingItems()->restore();
        });
    }
}