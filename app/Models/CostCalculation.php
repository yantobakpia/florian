<?php
// app/Models/CostCalculation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostCalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'fabric_cost',
        'thread_cost',
        'button_cost',
        'zipper_cost',
        'lining_cost',
        'other_material_cost',
        'sewing_cost',
        'embroidery_cost',
        'printing_cost',
        'ironing_cost',
        'other_service_cost',
        'total_material_cost',
        'total_service_cost',
        'total_cost',
        'order_price',
        'profit',
        'profit_percentage',
        'fabric_details',
        'material_details',
        'service_details',
        'fabric_length',
        'fabric_price_per_meter',
        'sewing_notes',
        'notes',
    ];

    protected $casts = [
        'fabric_cost' => 'decimal:2',
        'thread_cost' => 'decimal:2',
        'button_cost' => 'decimal:2',
        'zipper_cost' => 'decimal:2',
        'lining_cost' => 'decimal:2',
        'other_material_cost' => 'decimal:2',
        'sewing_cost' => 'decimal:2',
        'embroidery_cost' => 'decimal:2',
        'printing_cost' => 'decimal:2',
        'ironing_cost' => 'decimal:2',
        'other_service_cost' => 'decimal:2',
        'total_material_cost' => 'decimal:2',
        'total_service_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'order_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'profit_percentage' => 'decimal:2',
        'fabric_length' => 'decimal:2',
        'fabric_price_per_meter' => 'decimal:2',
        'fabric_details' => 'array',
        'material_details' => 'array',
        'service_details' => 'array',
    ];

    protected $appends = [
        'total_material_cost_calculated',
        'total_service_cost_calculated',
        'total_cost_calculated',
        'profit_calculated',
        'profit_percentage_calculated',
        'cash_in',
        'cash_out',
        'net_cash_flow',
        'summary',
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($calculation) {
            // Auto-calculate totals before saving
            $calculation->calculateTotals();
        });

        static::created(function ($calculation) {
            // Auto-record to balance transactions
            $calculation->recordToBalanceTransactions();
        });

        static::updated(function ($calculation) {
            // Update balance transactions if costs changed
            if ($calculation->isDirty(['total_material_cost', 'total_service_cost'])) {
                $calculation->updateBalanceTransactions();
            }
        });

        static::deleting(function ($calculation) {
            // Delete related balance transactions
            $calculation->balanceTransactions()->delete();
        });
    }

    /**
     * Calculate all totals
     */
    public function calculateTotals(): void
    {
        // Material costs - PASTIKAN SEMUA ANGKA
        $this->total_material_cost = 
            ((float) $this->fabric_cost ?: 0) +
            ((float) $this->thread_cost ?: 0) +
            ((float) $this->button_cost ?: 0) +
            ((float) $this->zipper_cost ?: 0) +
            ((float) $this->lining_cost ?: 0) +
            ((float) $this->other_material_cost ?: 0);

        // Service costs - PASTIKAN SEMUA ANGKA
        $this->total_service_cost = 
            ((float) $this->sewing_cost ?: 0) +
            ((float) $this->embroidery_cost ?: 0) +
            ((float) $this->printing_cost ?: 0) +
            ((float) $this->ironing_cost ?: 0) +
            ((float) $this->other_service_cost ?: 0);

        // Total cost (cash out) - PASTIKAN ANGKA
        $this->total_cost = (float) $this->total_material_cost + (float) $this->total_service_cost;

        // Calculate profit - PASTIKAN ANGKA
        $orderPrice = (float) $this->order_price ?: 0;
        if ($orderPrice > 0) {
            $this->profit = $orderPrice - (float) $this->total_cost;
            if ((float) $this->total_cost > 0) {
                $this->profit_percentage = ((float) $this->profit / (float) $this->total_cost) * 100;
            } else {
                $this->profit_percentage = 0;
            }
        } else {
            $this->profit = 0;
            $this->profit_percentage = 0;
        }

        // Auto-calculate fabric cost if fabric details exist
        if ($this->fabric_details && is_array($this->fabric_details)) {
            $fabricTotal = 0;
            foreach ($this->fabric_details as $fabric) {
                if (isset($fabric['length'], $fabric['price_per_meter'])) {
                    $length = (float) $fabric['length'] ?: 0;
                    $pricePerMeter = (float) $fabric['price_per_meter'] ?: 0;
                    $fabricTotal += $length * $pricePerMeter;
                }
            }
            if ($fabricTotal > 0) {
                $this->fabric_cost = $fabricTotal;
            }
        }
    }

    /**
     * Record to balance transactions
     */
    public function recordToBalanceTransactions(): void
    {
        // PASTIKAN ANGKA UNTUK MATERIAL COST
        $materialCost = (float) $this->total_material_cost ?: 0;
        
        // Record material cost transaction
        if ($materialCost > 0) {
            BalanceTransaction::recordTransaction([
                'type' => BalanceTransaction::TYPE_OUT,
                'amount' => $materialCost,
                'description' => "Pembelian bahan untuk Order #" . ($this->order->order_number ?? 'N/A'),
                'reference_type' => self::class,
                'reference_id' => $this->id,
                'payment_method' => 'cash',
                'notes' => $this->generateMaterialNotes(),
                'transaction_date' => now(),
            ]);
        }

        // PASTIKAN ANGKA UNTUK SERVICE COST
        $serviceCost = (float) $this->total_service_cost ?: 0;
        
        // Record service cost transaction
        if ($serviceCost > 0) {
            BalanceTransaction::recordTransaction([
                'type' => BalanceTransaction::TYPE_OUT,
                'amount' => $serviceCost,
                'description' => "Pembayaran jasa untuk Order #" . ($this->order->order_number ?? 'N/A'),
                'reference_type' => self::class,
                'reference_id' => $this->id,
                'payment_method' => 'cash',
                'notes' => $this->generateServiceNotes(),
                'transaction_date' => now(),
            ]);
        }
    }

    /**
     * Update balance transactions
     */
    public function updateBalanceTransactions(): void
    {
        // Delete old transactions
        $this->balanceTransactions()->delete();
        
        // Create new ones
        $this->recordToBalanceTransactions();
    }

    /**
     * Generate material notes for balance transaction - PERBAIKAN
     */
    public function generateMaterialNotes(): string
    {
        $notes = [];
        
        // ✅ PASTIKAN semua nilai adalah angka
        $fabricCost = (float) ($this->fabric_cost ?? 0);
        $threadCost = (float) ($this->thread_cost ?? 0);
        $buttonCost = (float) ($this->button_cost ?? 0);
        $zipperCost = (float) ($this->zipper_cost ?? 0);
        $liningCost = (float) ($this->lining_cost ?? 0);
        $otherMaterialCost = (float) ($this->other_material_cost ?? 0);
        
        if ($fabricCost > 0) $notes[] = "Kain: Rp " . number_format($fabricCost, 0, ',', '.');
        if ($threadCost > 0) $notes[] = "Benang: Rp " . number_format($threadCost, 0, ',', '.');
        if ($buttonCost > 0) $notes[] = "Kancing: Rp " . number_format($buttonCost, 0, ',', '.');
        if ($zipperCost > 0) $notes[] = "Resleting: Rp " . number_format($zipperCost, 0, ',', '.');
        if ($liningCost > 0) $notes[] = "Furing: Rp " . number_format($liningCost, 0, ',', '.');
        if ($otherMaterialCost > 0) $notes[] = "Lainnya: Rp " . number_format($otherMaterialCost, 0, ',', '.');
        
        $customerName = $this->order->customer->name ?? 'N/A';
        
        return implode("\n", $notes) . "\n\nCustomer: " . $customerName;
    }

    /**
     * Generate service notes for balance transaction - PERBAIKAN
     */
    public function generateServiceNotes(): string
    {
        $notes = [];
        
        // ✅ PASTIKAN semua nilai adalah angka
        $sewingCost = (float) ($this->sewing_cost ?? 0);
        $embroideryCost = (float) ($this->embroidery_cost ?? 0);
        $printingCost = (float) ($this->printing_cost ?? 0);
        $ironingCost = (float) ($this->ironing_cost ?? 0);
        $otherServiceCost = (float) ($this->other_service_cost ?? 0);
        
        if ($sewingCost > 0) $notes[] = "Jahit: Rp " . number_format($sewingCost, 0, ',', '.');
        if ($embroideryCost > 0) $notes[] = "Bordir: Rp " . number_format($embroideryCost, 0, ',', '.');
        if ($printingCost > 0) $notes[] = "Sablon: Rp " . number_format($printingCost, 0, ',', '.');
        if ($ironingCost > 0) $notes[] = "Setrika: Rp " . number_format($ironingCost, 0, ',', '.');
        if ($otherServiceCost > 0) $notes[] = "Lainnya: Rp " . number_format($otherServiceCost, 0, ',', '.');
        
        $customerName = $this->order->customer->name ?? 'N/A';
        
        return implode("\n", $notes) . "\n\nCustomer: " . $customerName;
    }

    // ACCESSORS
    public function getTotalMaterialCostCalculatedAttribute(): float
    {
        return 
            ((float) $this->fabric_cost ?: 0) +
            ((float) $this->thread_cost ?: 0) +
            ((float) $this->button_cost ?: 0) +
            ((float) $this->zipper_cost ?: 0) +
            ((float) $this->lining_cost ?: 0) +
            ((float) $this->other_material_cost ?: 0);
    }

    public function getTotalServiceCostCalculatedAttribute(): float
    {
        return 
            ((float) $this->sewing_cost ?: 0) +
            ((float) $this->embroidery_cost ?: 0) +
            ((float) $this->printing_cost ?: 0) +
            ((float) $this->ironing_cost ?: 0) +
            ((float) $this->other_service_cost ?: 0);
    }

    public function getTotalCostCalculatedAttribute(): float
    {
        return $this->total_material_cost_calculated + $this->total_service_cost_calculated;
    }

    public function getProfitCalculatedAttribute(): float
    {
        $orderPrice = (float) $this->order_price ?: 0;
        if ($orderPrice > 0) {
            return $orderPrice - $this->total_cost_calculated;
        }
        return 0;
    }

    public function getProfitPercentageCalculatedAttribute(): float
    {
        $totalCost = $this->total_cost_calculated;
        $profit = $this->profit_calculated;
        
        if ($totalCost > 0 && $profit > 0) {
            return ($profit / $totalCost) * 100;
        }
        return 0;
    }

    public function getCashInAttribute(): float
    {
        return (float) $this->order_price ?: 0;
    }

    public function getCashOutAttribute(): float
    {
        return $this->total_cost_calculated;
    }

    public function getNetCashFlowAttribute(): float
    {
        return $this->cash_in - $this->cash_out;
    }

    public function getSummaryAttribute(): array
    {
        return [
            'cash_in' => [
                'label' => 'Uang Masuk',
                'amount' => $this->cash_in,
                'formatted' => 'Rp ' . number_format($this->cash_in, 0, ',', '.'),
            ],
            'cash_out' => [
                'material' => $this->total_material_cost_calculated,
                'service' => $this->total_service_cost_calculated,
                'total' => $this->cash_out,
            ],
            'net_flow' => $this->net_cash_flow,
            'profit' => $this->profit_calculated,
            'profit_percentage' => $this->profit_percentage_calculated,
        ];
    }

    // RELATIONSHIPS
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function balanceTransactions()
    {
        return $this->hasMany(BalanceTransaction::class, 'reference_id')
            ->where('reference_type', self::class);
    }
    
    /**
     * Helper untuk mendapatkan info order dengan safety check
     */
    public function getOrderInfo(): array
    {
        if (!$this->order) {
            return [
                'order_number' => 'N/A',
                'customer_name' => 'N/A',
                'total_price' => 0,
            ];
        }
        
        return [
            'order_number' => $this->order->order_number ?? 'N/A',
            'customer_name' => $this->order->customer->name ?? 'N/A',
            'total_price' => (float) $this->order->total_price ?: 0,
        ];
    }
    
    /**
     * Validasi data sebelum save
     */
    public function validateData(): array
    {
        $errors = [];
        
        // Validasi angka positif
        $numericFields = [
            'fabric_cost', 'thread_cost', 'button_cost', 'zipper_cost',
            'lining_cost', 'other_material_cost', 'sewing_cost',
            'embroidery_cost', 'printing_cost', 'ironing_cost',
            'other_service_cost', 'order_price'
        ];
        
        foreach ($numericFields as $field) {
            $value = $this->$field ?? 0;
            if (!is_numeric($value)) {
                $errors[] = "Field {$field} harus berupa angka";
            } elseif ($value < 0) {
                $errors[] = "Field {$field} tidak boleh negatif";
            }
        }
        
        // Validasi order exists
        if (!$this->order_id) {
            $errors[] = "Order ID harus diisi";
        } elseif (!Order::find($this->order_id)) {
            $errors[] = "Order tidak ditemukan";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}