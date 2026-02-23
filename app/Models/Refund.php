<?php
// app/Models/Refund.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'amount',
        'method',
        'reference_number',
        'notes',
        'refund_date',
        'created_by',
        'type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_date' => 'datetime',
    ];

    protected $appends = [
        'formatted_amount',
        'refund_date_formatted',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function balanceTransaction(): MorphOne
    {
        return $this->morphOne(BalanceTransaction::class, 'reference');
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return '-Rp ' . number_format($this->amount ?? 0, 0, ',', '.');
    }

    public function getRefundDateFormattedAttribute(): ?string
    {
        return $this->refund_date ? $this->refund_date->format('d/m/Y H:i') : null;
    }

    // Boot hooks
    protected static function booted()
    {
        static::creating(function ($refund) {
            if (!$refund->refund_date) {
                $refund->refund_date = now();
            }
            if (!$refund->created_by && auth()->check()) {
                $refund->created_by = auth()->id();
            }
        });

        static::created(function ($refund) {
            // Record a balance transaction for the refund (money out)
            $transactionData = [
                'type' => BalanceTransaction::TYPE_OUT,
                'amount' => $refund->amount,
                'description' => 'Refund | Order #' . ($refund->order->order_number ?? $refund->order_id),
                'reference_type' => self::class,
                'reference_id' => $refund->id,
                'payment_method' => $refund->method,
                'notes' => ($refund->notes ?? ''),
                'transaction_date' => $refund->refund_date,
                'created_by' => $refund->created_by,
            ];

            BalanceTransaction::recordTransaction($transactionData);

            // Update order payment/financial status if necessary
            if ($refund->order) {
                $refund->order->updatePaymentStatus();
            }
        });

        static::updated(function ($refund) {
            if ($refund->isDirty(['amount'])) {
                if ($refund->order) {
                    $refund->order->updatePaymentStatus();
                }
            }
        });

        static::deleted(function ($refund) {
            if ($refund->balanceTransaction) {
                $refund->balanceTransaction->delete();
            }
            if ($refund->order) {
                $refund->order->updatePaymentStatus();
            }
        });

        static::restored(function ($refund) {
            if ($refund->balanceTransaction) {
                $refund->balanceTransaction->restore();
            }
            if ($refund->order) {
                $refund->order->updatePaymentStatus();
            }
        });
    }
}
