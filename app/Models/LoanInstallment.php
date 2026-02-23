<?php
// app/Models/LoanInstallment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'loan_installments';
    
    protected $fillable = [
        'loan_id',
        'installment_number',
        'amount',
        'payment_method',
        'payment_date',
        'notes',
        'balance_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payment_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($installment) {
            if (!$installment->created_by && auth()->check()) {
                $installment->created_by = auth()->id();
            }
            
            if (!$installment->payment_date) {
                $installment->payment_date = now();
            }
            
            // Auto-correct amount to nearest 1000
            if ($installment->amount % 1000 !== 0) {
                $installment->amount = (int) round($installment->amount / 1000) * 1000;
            }
        });

        static::created(function ($installment) {
            $loan = $installment->loan;
            
            // Record transaction for the installment
            if ($loan->loan_type === Loan::TYPE_BORROW) {
                // Angsuran pinjaman (uang keluar)
                $balanceTransaction = BalanceTransaction::recordTransaction([
                    'type' => BalanceTransaction::TYPE_OUT,
                    'amount' => $installment->amount,
                    'description' => "Angsuran Pinjaman: " . $loan->borrower_name,
                    'payment_method' => $installment->payment_method,
                    'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                              "Angsuran ke: {$installment->installment_number}\n" .
                              "Peminjam: {$loan->borrower_name}\n" .
                              "Sisa: Rp " . number_format($loan->remaining_amount - $installment->amount, 0, ',', '.'),
                    'reference_type' => self::class,
                    'reference_id' => $installment->id,
                    'transaction_date' => $installment->payment_date ?? now(),
                ]);
            } else {
                // Penerimaan angsuran (uang masuk)
                $balanceTransaction = BalanceTransaction::recordTransaction([
                    'type' => BalanceTransaction::TYPE_IN,
                    'amount' => $installment->amount,
                    'description' => "Penerimaan Angsuran: " . $loan->borrower_name,
                    'payment_method' => $installment->payment_method,
                    'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                              "Angsuran ke: {$installment->installment_number}\n" .
                              "Peminjam: {$loan->borrower_name}\n" .
                              "Sisa: Rp " . number_format($loan->remaining_amount - $installment->amount, 0, ',', '.'),
                    'reference_type' => self::class,
                    'reference_id' => $installment->id,
                    'transaction_date' => $installment->payment_date ?? now(),
                ]);
            }
            
            $installment->balance_transaction_id = $balanceTransaction->id;
            $installment->saveQuietly();
            
            // Update loan remaining amount
            $loan->updateRemainingAmount();
        });

        static::deleted(function ($installment) {
            // Update loan remaining amount when installment is deleted
            if ($installment->loan) {
                $installment->loan->updateRemainingAmount();
            }
            
            // Delete related balance transaction
            if ($installment->balance_transaction_id) {
                BalanceTransaction::find($installment->balance_transaction_id)?->delete();
            }
        });
    }

    // Relationships
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function balanceTransaction(): BelongsTo
    {
        return $this->belongsTo(BalanceTransaction::class, 'balance_transaction_id');
    }
    
    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }
    
    public function getPaymentMethodLabelAttribute(): string
    {
        return BalanceTransaction::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }
    
    // Scopes
    public function scopeByLoan($query, $loanId)
    {
        return $query->where('loan_id', $loanId);
    }
    
    public function scopeByPaymentDate($query, $startDate, $endDate = null)
    {
        if ($endDate) {
            return $query->whereBetween('payment_date', [$startDate, $endDate]);
        }
        return $query->whereDate('payment_date', $startDate);
    }
}