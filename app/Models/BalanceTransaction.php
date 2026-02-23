<?php
// app/Models/BalanceTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class BalanceTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'balance_transactions';
    
    protected $fillable = [
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'payment_method',
        'notes',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'transaction_date' => 'datetime',
    ];

    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';
    const TYPE_LOAN_BORROW = 'loan_borrow';
    const TYPE_LOAN_LEND = 'loan_lend';

    const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'transfer' => 'Transfer Bank',
        'qris' => 'QRIS',
        'debit' => 'Kartu Debit',
        'credit' => 'Kartu Kredit',
        'other' => 'Lainnya',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            // Set default values
            if (!$transaction->transaction_date) {
                $transaction->transaction_date = now();
            }
            
            if (!$transaction->created_by && auth()->check()) {
                $transaction->created_by = auth()->id();
            }
            
            // Format amount ke kelipatan 1000
            if (isset($transaction->amount)) {
                $amount = (int) round($transaction->amount);
                if ($amount % 1000 !== 0) {
                    $amount = (int) round($amount / 1000) * 1000;
                }
                if ($amount < 1000) {
                    $amount = 1000;
                }
                $transaction->amount = $amount;
            }
        });

        static::created(function ($transaction) {
            // Recalculate semua saldo setelah transaksi dibuat
            self::recalculateAllBalances();
        });

        static::updated(function ($transaction) {
            // Jika ada perubahan yang mempengaruhi saldo
            if ($transaction->isDirty(['amount', 'type', 'transaction_date'])) {
                self::recalculateAllBalances();
            }
        });

        static::deleted(function ($transaction) {
            // Recalculate semua saldo setelah transaksi dihapus
            self::recalculateAllBalances();
        });
    }

    /**
     * Recalculate semua saldo dari awal
     */
    public static function recalculateAllBalances(): void
    {
        DB::transaction(function () {
            // Ambil semua transaksi diurutkan berdasarkan tanggal ASC, lalu ID ASC
            $transactions = self::query()
                ->orderBy('transaction_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            $currentBalance = self::getOpeningBalance();
            
            foreach ($transactions as $transaction) {
                // Update balance_before
                $transaction->balance_before = $currentBalance;
                
                // Hitung balance_after berdasarkan type
                $amount = (int) $transaction->amount;
                
                if (in_array($transaction->type, [self::TYPE_IN, self::TYPE_LOAN_BORROW])) {
                    $transaction->balance_after = $currentBalance + $amount;
                } elseif (in_array($transaction->type, [self::TYPE_OUT, self::TYPE_LOAN_LEND])) {
                    $transaction->balance_after = $currentBalance - $amount;
                } else {
                    $transaction->balance_after = $currentBalance;
                }
                
                // Simpan tanpa trigger event untuk menghindari loop
                $transaction->saveQuietly();
                
                // Update current balance untuk transaksi berikutnya
                $currentBalance = $transaction->balance_after;
            }
            
            // Log untuk debugging
            \Log::info('Saldo berhasil di-recalculate. Saldo akhir: ' . $currentBalance);
        });
    }

    /**
     * Get current balance (saldo terakhir)
     */
    public static function getCurrentBalance(): int
    {
        $latest = self::query()
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        return $latest ? (int) $latest->balance_after : self::getOpeningBalance();
    }

    /**
     * Get opening balance from config
     */
    public static function getOpeningBalance(): int
    {
        return (int) config('app.opening_balance', 0);
    }

    /**
     * Rekam transaksi kas
     */
    public static function recordTransaction(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $transaction = new self();
            $transaction->fill([
                'type' => $data['type'] ?? self::TYPE_IN,
                'amount' => isset($data['amount']) ? (int) round($data['amount']) : 0,
                'description' => $data['description'] ?? null,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'created_by' => auth()->id() ?? null,
            ]);
            
            $transaction->save();
            
            return $transaction;
        });
    }

    /**
     * Rekam transaksi dari Order
     */
    public static function recordFromOrder(Order $order, string $paymentType, float $amount): self
    {
        $description = match($paymentType) {
            'dp' => "DP Awal Order #{$order->order_number}",
            'full' => "Pelunasan Order #{$order->order_number}",
            'partial' => "Cicilan Order #{$order->order_number}",
            default => "Pembayaran Order #{$order->order_number}"
        };

        $amountInt = (int) round($amount);
        
        return self::recordTransaction([
            'type' => self::TYPE_IN,
            'amount' => $amountInt,
            'description' => $description,
            'payment_method' => $order->payment_method ?? 'cash',
            'notes' => "Customer: " . ($order->customer->name ?? 'N/A') . "\nOrder: #{$order->order_number}",
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'transaction_date' => now(),
        ]);
    }

    /**
     * Rekam transaksi peminjaman (pinjam uang dari pihak lain)
     */
    public static function recordLoanBorrow(Loan $loan): self
    {
        return self::recordTransaction([
            'type' => self::TYPE_LOAN_BORROW,
            'amount' => (int) round($loan->amount),
            'description' => "Pinjaman dari: " . $loan->borrower_name,
            'payment_method' => $loan->payment_method ?? 'cash',
            'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                      "Peminjam: {$loan->borrower_name}\n" .
                      "Jangka Waktu: {$loan->loan_date->format('d/m/Y')} - {$loan->due_date->format('d/m/Y')}",
            'reference_type' => Loan::class,
            'reference_id' => $loan->id,
            'transaction_date' => $loan->loan_date ?? now(),
        ]);
    }

    /**
     * Rekam transaksi meminjamkan uang (pinjamkan uang ke pihak lain)
     */
    public static function recordLoanLend(Loan $loan): self
    {
        return self::recordTransaction([
            'type' => self::TYPE_LOAN_LEND,
            'amount' => (int) round($loan->amount),
            'description' => "Pinjaman kepada: " . $loan->borrower_name,
            'payment_method' => $loan->payment_method ?? 'cash',
            'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                      "Peminjam: {$loan->borrower_name}\n" .
                      "Jangka Waktu: {$loan->loan_date->format('d/m/Y')} - {$loan->due_date->format('d/m/Y')}",
            'reference_type' => Loan::class,
            'reference_id' => $loan->id,
            'transaction_date' => $loan->loan_date ?? now(),
        ]);
    }

    /**
     * Rekam angsuran pinjaman
     */
    public static function recordLoanPayment(LoanInstallment $installment): self
    {
        $loan = $installment->loan;
        
        if ($loan->loan_type === Loan::TYPE_BORROW) {
            return self::recordTransaction([
                'type' => self::TYPE_OUT,
                'amount' => (int) round($installment->amount),
                'description' => "Pembayaran angsuran pinjaman: " . $loan->borrower_name,
                'payment_method' => $installment->payment_method ?? 'cash',
                'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                          "Angsuran ke: {$installment->installment_number}\n" .
                          "Peminjam: {$loan->borrower_name}",
                'reference_type' => LoanInstallment::class,
                'reference_id' => $installment->id,
                'transaction_date' => $installment->payment_date ?? now(),
            ]);
        } else {
            return self::recordTransaction([
                'type' => self::TYPE_IN,
                'amount' => (int) round($installment->amount),
                'description' => "Penerimaan angsuran: " . $loan->borrower_name,
                'payment_method' => $installment->payment_method ?? 'cash',
                'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                          "Angsuran ke: {$installment->installment_number}\n" .
                          "Peminjam: {$loan->borrower_name}",
                'reference_type' => LoanInstallment::class,
                'reference_id' => $installment->id,
                'transaction_date' => $installment->payment_date ?? now(),
            ]);
        }
    }

    /**
     * Rekam pengeluaran (out)
     */
    public static function recordExpense(array $expenseData): self
    {
        $amount = isset($expenseData['amount']) ? (int) round($expenseData['amount']) : 0;
        
        return self::recordTransaction([
            'type' => self::TYPE_OUT,
            'amount' => $amount,
            'description' => $expenseData['description'] ?? 'Pengeluaran',
            'payment_method' => $expenseData['payment_method'] ?? 'cash',
            'notes' => $expenseData['notes'] ?? null,
            'reference_type' => $expenseData['reference_type'] ?? null,
            'reference_id' => $expenseData['reference_id'] ?? null,
            'transaction_date' => $expenseData['transaction_date'] ?? now(),
        ]);
    }

    /**
     * Rekam transaksi dari CostCalculation
     */
    public static function recordFromCostCalculation(CostCalculation $calculation): array
    {
        $transactions = [];
        
        $materialCost = (int) round($calculation->total_material_cost);
        $serviceCost = (int) round($calculation->total_service_cost);
        
        if ($materialCost > 0) {
            $transactions[] = self::recordTransaction([
                'type' => self::TYPE_OUT,
                'amount' => $materialCost,
                'description' => "Pembelian bahan untuk Order #{$calculation->order->order_number}",
                'reference_type' => CostCalculation::class,
                'reference_id' => $calculation->id,
                'payment_method' => 'cash',
                'notes' => $calculation->generateMaterialNotes() ?? 'Biaya bahan produksi',
                'transaction_date' => now(),
            ]);
        }
        
        if ($serviceCost > 0) {
            $transactions[] = self::recordTransaction([
                'type' => self::TYPE_OUT,
                'amount' => $serviceCost,
                'description' => "Pembayaran jasa untuk Order #{$calculation->order->order_number}",
                'reference_type' => CostCalculation::class,
                'reference_id' => $calculation->id,
                'payment_method' => 'cash',
                'notes' => $calculation->generateServiceNotes() ?? 'Biaya jasa produksi',
                'transaction_date' => now(),
            ]);
        }
        
        return $transactions;
    }

    /**
     * Rekam pembelian bahan (Material Purchase)
     */
    public static function recordMaterialPurchase($materialPurchase): self
    {
        $amount = isset($materialPurchase->total_amount) 
            ? (int) round($materialPurchase->total_amount) 
            : 0;
        
        return self::recordTransaction([
            'type' => self::TYPE_OUT,
            'amount' => $amount,
            'description' => "Pembelian Bahan: " . ($materialPurchase->material_name ?? 'Bahan'),
            'payment_method' => $materialPurchase->payment_method ?? 'cash',
            'notes' => "Supplier: " . ($materialPurchase->supplier ?? 'N/A') . 
                     "\nQuantity: " . ($materialPurchase->quantity ?? 0),
            'reference_type' => MaterialPurchase::class,
            'reference_id' => $materialPurchase->id ?? null,
            'transaction_date' => $materialPurchase->purchase_date ?? now(),
        ]);
    }

    /**
     * Rekam gaji karyawan (Salary Payment)
     */
    public static function recordSalaryPayment($salaryPayment): self
    {
        $amount = isset($salaryPayment->amount) 
            ? (int) round($salaryPayment->amount) 
            : 0;
        
        return self::recordTransaction([
            'type' => self::TYPE_OUT,
            'amount' => $amount,
            'description' => "Gaji Karyawan: " . ($salaryPayment->employee_name ?? 'Karyawan'),
            'payment_method' => $salaryPayment->payment_method ?? 'cash',
            'notes' => "Bulan: " . ($salaryPayment->month ?? 'N/A') . 
                     "/" . ($salaryPayment->year ?? 'N/A') . 
                     "\nPosisi: " . ($salaryPayment->position ?? 'N/A'),
            'reference_type' => SalaryPayment::class,
            'reference_id' => $salaryPayment->id ?? null,
            'transaction_date' => $salaryPayment->payment_date ?? now(),
        ]);
    }

    // Mutators
    public function setAmountAttribute($value)
    {
        if ($value === null) {
            $this->attributes['amount'] = 0;
            return;
        }
        
        if (!is_numeric($value)) {
            $value = 0;
        }
        
        $intValue = (int) round($value);
        if ($intValue % 1000 !== 0) {
            $intValue = (int) round($intValue / 1000) * 1000;
        }
        
        $this->attributes['amount'] = max(1000, $intValue);
    }

    public function setBalanceBeforeAttribute($value)
    {
        $this->attributes['balance_before'] = (int) round($value);
    }

    public function setBalanceAfterAttribute($value)
    {
        $this->attributes['balance_after'] = (int) round($value);
    }

    // Static Methods
    public static function getBalanceAtDate($date): int
    {
        $transaction = self::query()
            ->whereDate('transaction_date', '<=', $date)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
            
        return $transaction ? (int) $transaction->balance_after : self::getOpeningBalance();
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_IN => 'Uang Masuk',
            self::TYPE_OUT => 'Uang Keluar',
            self::TYPE_LOAN_BORROW => 'Uang Pinjam Masuk',
            self::TYPE_LOAN_LEND => 'Uang Pinjam Keluar',
        ];
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }
    
    public function getFormattedBalanceBeforeAttribute(): string
    {
        return 'Rp ' . number_format($this->balance_before, 0, ',', '.');
    }
    
    public function getFormattedBalanceAfterAttribute(): string
    {
        return 'Rp ' . number_format($this->balance_after, 0, ',', '.');
    }
    
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            self::TYPE_IN => 'Uang Masuk',
            self::TYPE_OUT => 'Uang Keluar',
            self::TYPE_LOAN_BORROW => 'Uang Pinjam Masuk',
            self::TYPE_LOAN_LEND => 'Uang Pinjam Keluar',
            default => $this->type,
        };
    }
    
    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            self::TYPE_IN => 'success',
            self::TYPE_OUT => 'danger',
            self::TYPE_LOAN_BORROW => 'info',
            self::TYPE_LOAN_LEND => 'warning',
            default => 'gray',
        };
    }
    
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }
    
    public function getReferenceInfoAttribute(): ?string
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }
        
        try {
            $reference = $this->reference;
            if (!$reference) {
                return "{$this->reference_type} #{$this->reference_id} (deleted)";
            }

            switch ($this->reference_type) {
                case Order::class:
                    $customerName = $reference->customer->name ?? 'Unknown';
                    return "Order #{$reference->order_number} - {$customerName}";
                
                case 'App\Models\CostCalculation':
                    $orderNumber = $reference->order->order_number ?? 'Unknown';
                    return "Cost Calculation #{$reference->id} - Order #{$orderNumber}";
                
                case 'App\Models\Loan':
                    $typeLabel = $reference->loan_type === 'borrow' ? 'Pinjam' : 'Pinjamkan';
                    return "Loan #{$reference->loan_number} - {$reference->borrower_name} - {$typeLabel}";
                
                case 'App\Models\LoanInstallment':
                    $loan = $reference->loan;
                    $loanNumber = $loan->loan_number ?? 'Unknown';
                    return "Installment #{$reference->id} - Loan #{$loanNumber}";
                
                case 'App\Models\MaterialPurchase':
                    return "Bahan: " . ($reference->material_name ?? 'N/A');
                
                case 'App\Models\SalaryPayment':
                    return "Gaji: " . ($reference->employee_name ?? 'N/A');
                
                default:
                    return class_basename($this->reference_type) . ' #' . $this->reference_id;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getIsLoanAttribute(): bool
    {
        return in_array($this->type, [self::TYPE_LOAN_BORROW, self::TYPE_LOAN_LEND]);
    }
    
    public function getIsLoanBorrowAttribute(): bool
    {
        return $this->type === self::TYPE_LOAN_BORROW;
    }
    
    public function getIsLoanLendAttribute(): bool
    {
        return $this->type === self::TYPE_LOAN_LEND;
    }
    
    public function isForOrder(int $orderId): bool
    {
        return $this->reference_type === Order::class && $this->reference_id == $orderId;
    }
    
    public function isOrderPayment(): bool
    {
        return $this->reference_type === Order::class && $this->type === self::TYPE_IN;
    }
    
    public function isOrderRefund(): bool
    {
        return $this->reference_type === Order::class && 
               $this->type === self::TYPE_OUT && 
               str_contains($this->description, 'Refund');
    }

    /**
     * Debug: tampilkan semua transaksi dengan perhitungan saldo
     */
    public static function debugBalances(): array
    {
        $transactions = self::query()
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        
        $result = [];
        $currentBalance = self::getOpeningBalance();
        
        foreach ($transactions as $transaction) {
            $calculatedAfter = $currentBalance;
            if (in_array($transaction->type, [self::TYPE_IN, self::TYPE_LOAN_BORROW])) {
                $calculatedAfter += $transaction->amount;
            } elseif (in_array($transaction->type, [self::TYPE_OUT, self::TYPE_LOAN_LEND])) {
                $calculatedAfter -= $transaction->amount;
            }
            
            $result[] = [
                'id' => $transaction->id,
                'date' => $transaction->transaction_date->format('d/m/Y H:i'),
                'type' => $transaction->type,
                'amount' => 'Rp ' . number_format($transaction->amount, 0, ',', '.'),
                'balance_before_db' => 'Rp ' . number_format($transaction->balance_before, 0, ',', '.'),
                'balance_after_db' => 'Rp ' . number_format($transaction->balance_after, 0, ',', '.'),
                'calculated_before' => 'Rp ' . number_format($currentBalance, 0, ',', '.'),
                'calculated_after' => 'Rp ' . number_format($calculatedAfter, 0, ',', '.'),
                'match' => $transaction->balance_before == $currentBalance && 
                          $transaction->balance_after == $calculatedAfter
            ];
            
            $currentBalance = $calculatedAfter;
        }
        
        return $result;
    }
    
    /**
     * Fix semua saldo
     */
    public static function fixAllBalances(): void
    {
        self::recalculateAllBalances();
    }

    // Relationships
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function order()
    {
        return $this->belongsTo(Order::class, 'reference_id')
            ->where('reference_type', Order::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    // Scopes
    public function scopeIncoming($query)
    {
        return $query->whereIn('type', [self::TYPE_IN, self::TYPE_LOAN_BORROW]);
    }
    
    public function scopeOutgoing($query)
    {
        return $query->whereIn('type', [self::TYPE_OUT, self::TYPE_LOAN_LEND]);
    }
    
    public function scopeRegular($query)
    {
        return $query->whereIn('type', [self::TYPE_IN, self::TYPE_OUT]);
    }
    
    public function scopeLoan($query)
    {
        return $query->whereIn('type', [self::TYPE_LOAN_BORROW, self::TYPE_LOAN_LEND]);
    }
    
    public function scopeLoanBorrow($query)
    {
        return $query->where('type', self::TYPE_LOAN_BORROW);
    }
    
    public function scopeLoanLend($query)
    {
        return $query->where('type', self::TYPE_LOAN_LEND);
    }
    
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
    
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }
    
    public function scopeByReference($query, $referenceType, $referenceId = null)
    {
        $query = $query->where('reference_type', $referenceType);
        if ($referenceId) {
            $query->where('reference_id', $referenceId);
        }
        return $query;
    }
    
    public function scopeByOrder($query, $orderId)
    {
        return $query->where('reference_type', Order::class)
            ->where('reference_id', $orderId);
    }
}