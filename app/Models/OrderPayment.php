<?php
// app/Models/OrderPayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'type',
        'amount',
        'method',
        'reference_number',
        'notes',
        'payment_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    protected $appends = [
        'type_label',
        'method_label',
        'formatted_amount',
        'payment_date_formatted',
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

    public function balanceTransaction()
    {
        return $this->morphOne(BalanceTransaction::class, 'reference');
    }

    // Accessors
    public function getTypeLabelAttribute(): string
    {
        $types = [
            'dp' => 'DP',
            'partial' => 'Cicilan',
            'full' => 'Pelunasan',
            'refund' => 'Refund',
        ];
        return $types[$this->type] ?? ucfirst($this->type);
    }

    public function getMethodLabelAttribute(): string
    {
        $methods = [
            'cash' => 'Cash',
            'transfer' => 'Transfer',
            'qris' => 'QRIS',
            'debit' => 'Kartu Debit',
            'credit' => 'Kartu Kredit',
            'other' => 'Lainnya',
        ];
        return $methods[$this->method] ?? ucfirst($this->method);
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->type === 'refund' ? '-' : '';
        return $sign . 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function getPaymentDateFormattedAttribute(): string
    {
        return $this->payment_date->format('d/m/Y H:i');
    }

    // Boot
    protected static function booted()
    {
        static::creating(function ($payment) {
            if (!$payment->payment_date) {
                $payment->payment_date = now();
            }
            if (!$payment->created_by && auth()->check()) {
                $payment->created_by = auth()->id();
            }
        });

        static::created(function ($payment) {
            // **OTOMATIS BUAT BALANCE TRANSACTION SETIAP PEMBAYARAN**
            $transactionData = [
                'type' => $payment->type === 'refund' ? BalanceTransaction::TYPE_OUT : BalanceTransaction::TYPE_IN,
                'amount' => $payment->amount,
                'description' => ($payment->type === 'refund' ? 'Refund' : 'Pembayaran') . 
                                " Order #{$payment->order->order_number} - {$payment->order->customer->name}",
                'reference_type' => self::class,
                'reference_id' => $payment->id,
                'payment_method' => $payment->method,
                'notes' => "{$payment->type_label} | Order: {$payment->order->order_number}" . 
                          ($payment->notes ? " | {$payment->notes}" : ''),
                'transaction_date' => $payment->payment_date,
                'created_by' => $payment->created_by,
            ];

            BalanceTransaction::recordTransaction($transactionData);

            // Update order payment status
            $payment->order->updatePaymentStatus();
        });

        static::updated(function ($payment) {
            // Update order payment status jika amount atau type berubah
            if ($payment->isDirty(['amount', 'type'])) {
                $payment->order->updatePaymentStatus();
            }
        });

        static::deleted(function ($payment) {
            // Hapus balance transaction terkait
            if ($payment->balanceTransaction) {
                $payment->balanceTransaction->delete();
            }
            
            // Update order payment status
            $payment->order->updatePaymentStatus();
        });

        static::restored(function ($payment) {
            // Pulihkan balance transaction terkait
            if ($payment->balanceTransaction) {
                $payment->balanceTransaction->restore();
            }
            
            // Update order payment status
            $payment->order->updatePaymentStatus();
        });
    }
}