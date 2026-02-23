<?php
// app/Models/MaterialPurchase.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'material_id',
        'supplier',
        'invoice_number',
        'quantity',
        'unit_price',
        'total_price',
        'payment_method',
        'status',
        'notes',
        'purchase_date',
        'received_date',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:0',
        'total_price' => 'decimal:0',
        'purchase_date' => 'date',
        'received_date' => 'date',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ORDERED = 'ordered';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Transfer',
        'qris' => 'QRIS',
        'credit' => 'Kredit',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Event: setelah pembelian
    protected static function booted()
    {
        static::created(function ($purchase) {
            if ($purchase->status === 'received' && $purchase->payment_method !== 'credit') {
                $purchase->recordBalanceTransaction();
            }
            
            // Update stok bahan
            if ($purchase->status === 'received') {
                $material = $purchase->material;
                $material->stock += $purchase->quantity;
                $material->save();
            }
        });

        static::updated(function ($purchase) {
            // Jika status berubah menjadi received
            if ($purchase->isDirty('status') && $purchase->status === 'received') {
                if ($purchase->payment_method !== 'credit') {
                    $purchase->recordBalanceTransaction();
                }
                
                // Update stok bahan
                $material = $purchase->material;
                $material->stock += $purchase->quantity;
                $material->save();
            }
        });
    }

    // Method untuk mencatat transaksi balance
    public function recordBalanceTransaction(): void
    {
        BalanceTransaction::recordTransaction([
            'type' => BalanceTransaction::TYPE_OUT,
            'amount' => $this->total_price,
            'description' => "Pembelian bahan: {$this->material->name} - {$this->supplier}",
            'reference_type' => MaterialPurchase::class,
            'reference_id' => $this->id,
            'payment_method' => $this->payment_method,
            'notes' => "Invoice: {$this->invoice_number}\nQuantity: {$this->quantity} {$this->material->unit}",
        ]);
    }

    // Format total price
    public function getFormattedTotalPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->total_price, 0, ',', '.');
    }
}