<?php
// app/Models/Loan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'loans';
    
    protected $fillable = [
        'loan_number',
        'loan_type',
        'borrower_id',
        'borrower_type',
        'amount',
        'interest_rate',
        'installment_count',
        'installment_amount',
        'total_amount',
        'remaining_amount',
        'status',
        'loan_date',
        'due_date',
        'paid_date',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'interest_rate' => 'decimal:2',
        'installment_amount' => 'integer',
        'total_amount' => 'integer',
        'remaining_amount' => 'integer',
        'loan_date' => 'datetime',
        'due_date' => 'date',
        'paid_date' => 'datetime',
    ];

    const TYPE_BORROW = 'borrow';
    const TYPE_LEND = 'lend';

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_PARTIAL = 'partial';
    const STATUS_DEFAULTED = 'defaulted';

    public static function generateLoanNumber(): string
    {
        $prefix = 'LN';
        $date = date('Ym');
        $last = self::where('loan_number', 'like', $prefix . '-' . $date . '%')
            ->orderBy('loan_number', 'desc')
            ->first();
        
        $seq = 1;
        if ($last) {
            $seq = intval(substr($last->loan_number, -4)) + 1;
        }
        
        return $prefix . '-' . $date . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::creating(function ($loan) {
            if (!$loan->loan_number) {
                $loan->loan_number = self::generateLoanNumber();
            }
            
            if (!$loan->created_by && auth()->check()) {
                $loan->created_by = auth()->id();
            }
            
            if (!$loan->payment_method) {
                $loan->payment_method = 'cash';
            }
            
            // Calculate total amount with interest
            $interest = $loan->amount * ($loan->interest_rate / 100);
            $loan->total_amount = (int) round($loan->amount + $interest);
            
            // Calculate installment amount
            if ($loan->installment_count > 0) {
                $loan->installment_amount = (int) round($loan->total_amount / $loan->installment_count);
            } else {
                $loan->installment_amount = $loan->total_amount;
            }
            
            $loan->remaining_amount = $loan->total_amount;
            
            // Set status
            if ($loan->loan_date <= now()) {
                $loan->status = self::STATUS_ACTIVE;
            } else {
                $loan->status = self::STATUS_PENDING;
            }
        });

        static::created(function ($loan) {
            // Record transaction based on loan type
            if ($loan->loan_type === self::TYPE_BORROW) {
                BalanceTransaction::recordTransaction([
                    'type' => BalanceTransaction::TYPE_LOAN_BORROW,
                    'amount' => $loan->amount,
                    'description' => "Pinjaman dari: " . $loan->borrower_name,
                    'payment_method' => $loan->payment_method,
                    'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                              "Peminjam: {$loan->borrower_name}\n" .
                              "Jangka Waktu: {$loan->loan_date->format('d/m/Y')} - {$loan->due_date->format('d/m/Y')}",
                    'reference_type' => self::class,
                    'reference_id' => $loan->id,
                    'transaction_date' => $loan->loan_date ?? now(),
                ]);
            } else {
                BalanceTransaction::recordTransaction([
                    'type' => BalanceTransaction::TYPE_LOAN_LEND,
                    'amount' => $loan->amount,
                    'description' => "Pinjaman kepada: " . $loan->borrower_name,
                    'payment_method' => $loan->payment_method,
                    'notes' => "No. Pinjaman: {$loan->loan_number}\n" .
                              "Peminjam: {$loan->borrower_name}\n" .
                              "Jangka Waktu: {$loan->loan_date->format('d/m/Y')} - {$loan->due_date->format('d/m/Y')}",
                    'reference_type' => self::class,
                    'reference_id' => $loan->id,
                    'transaction_date' => $loan->loan_date ?? now(),
                ]);
            }
        });

        static::updating(function ($loan) {
            // Jika loan_date berubah dari pending ke active
            if ($loan->isDirty('loan_date') && $loan->loan_date <= now()) {
                $loan->status = self::STATUS_ACTIVE;
            }
            
            // Recalculate jika amount atau interest_rate berubah
            if ($loan->isDirty(['amount', 'interest_rate', 'installment_count'])) {
                $interest = $loan->amount * ($loan->interest_rate / 100);
                $loan->total_amount = (int) round($loan->amount + $interest);
                
                if ($loan->installment_count > 0) {
                    $loan->installment_amount = (int) round($loan->total_amount / $loan->installment_count);
                } else {
                    $loan->installment_amount = $loan->total_amount;
                }
                
                // Update remaining amount jika sudah ada pembayaran
                if ($loan->exists) {
                    $paid = $loan->installments()->sum('amount');
                    $loan->remaining_amount = $loan->total_amount - $paid;
                } else {
                    $loan->remaining_amount = $loan->total_amount;
                }
            }
        });
    }

    // Relationships
    public function borrower()
    {
        return $this->morphTo();
    }
    
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class, 'reference_id')
            ->where('reference_type', self::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
    
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }
    
    public function scopeByBorrower($query, $borrowerType, $borrowerId)
    {
        return $query->where('borrower_type', $borrowerType)
            ->where('borrower_id', $borrowerId);
    }
    
    public function scopeByType($query, $type)
    {
        return $query->where('loan_type', $type);
    }
    
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
    
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }
    
    public function scopePartial($query)
    {
        return $query->where('status', self::STATUS_PARTIAL);
    }
    
    public function scopeDefaulted($query)
    {
        return $query->where('status', self::STATUS_DEFAULTED);
    }
    
    public function scopeBorrow($query)
    {
        return $query->where('loan_type', self::TYPE_BORROW);
    }
    
    public function scopeLend($query)
    {
        return $query->where('loan_type', self::TYPE_LEND);
    }
    
    public function scopeDueSoon($query, $days = 7)
    {
        $dueDate = now()->addDays($days);
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('due_date', '<=', $dueDate)
            ->where('due_date', '>=', now());
    }
    
    // Methods
    public function updateRemainingAmount(): void
    {
        $paid = $this->installments()->sum('amount');
        $this->remaining_amount = max(0, $this->total_amount - $paid);
        
        if ($this->remaining_amount <= 0) {
            $this->status = self::STATUS_PAID;
            $this->paid_date = now();
        } elseif ($this->remaining_amount < $this->total_amount) {
            $this->status = self::STATUS_PARTIAL;
        } else {
            $this->status = self::STATUS_ACTIVE;
        }
        
        $this->save();
    }
    
    public function checkOverdue(): void
    {
        if ($this->due_date < now() && in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PARTIAL, self::STATUS_PENDING])) {
            $this->status = self::STATUS_OVERDUE;
            $this->save();
        }
    }
    
    public function getNextInstallmentNumber(): int
    {
        $last = $this->installments()->orderBy('installment_number', 'desc')->first();
        return $last ? $last->installment_number + 1 : 1;
    }
    
    public function getTotalPaid(): int
    {
        return $this->installments()->sum('amount');
    }
    
    public function getRemainingInstallments(): int
    {
        if ($this->installment_amount <= 0) {
            return 0;
        }
        
        $remainingAmount = $this->remaining_amount;
        return (int) ceil($remainingAmount / $this->installment_amount);
    }
    
    public function canAddInstallment(): bool
    {
        return $this->remaining_amount > 0 && 
               in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PARTIAL, self::STATUS_OVERDUE]);
    }
    
    public function markAsPaid(): void
    {
        $this->status = self::STATUS_PAID;
        $this->remaining_amount = 0;
        $this->paid_date = now();
        $this->save();
    }
    
    public function markAsDefaulted(): void
    {
        $this->status = self::STATUS_DEFAULTED;
        $this->save();
    }
    
    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }
    
    public function getFormattedTotalAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }
    
    public function getFormattedRemainingAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->remaining_amount, 0, ',', '.');
    }
    
    public function getFormattedInstallmentAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->installment_amount, 0, ',', '.');
    }
    
    public function getFormattedTotalPaidAttribute(): string
    {
        return 'Rp ' . number_format($this->getTotalPaid(), 0, ',', '.');
    }
    
    public function getBorrowerNameAttribute(): string
    {
        if (!$this->borrower) {
            return 'Unknown';
        }
        
        if ($this->borrower_type === 'App\Models\Customer') {
            return $this->borrower->name ?? 'Unknown';
        } elseif ($this->borrower_type === 'App\Models\Employee') {
            return $this->borrower->name ?? 'Unknown';
        } elseif ($this->borrower_type === 'App\Models\Supplier') {
            return $this->borrower->name ?? 'Unknown';
        }
        
        return 'Unknown';
    }
    
    public function getBorrowerTypeLabelAttribute(): string
    {
        return match($this->borrower_type) {
            'App\Models\Customer' => 'Customer',
            'App\Models\Employee' => 'Karyawan',
            'App\Models\Supplier' => 'Supplier',
            default => $this->borrower_type,
        };
    }
    
    public function getLoanTypeLabelAttribute(): string
    {
        return match($this->loan_type) {
            self::TYPE_BORROW => 'Pinjam Uang',
            self::TYPE_LEND => 'Pinjamkan Uang',
            default => $this->loan_type,
        };
    }
    
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_PAID => 'Lunas',
            self::STATUS_OVERDUE => 'Jatuh Tempo',
            self::STATUS_PARTIAL => 'Sebagian',
            self::STATUS_DEFAULTED => 'Wanprestasi',
            default => $this->status,
        };
    }
    
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_ACTIVE => 'success',
            self::STATUS_PAID => 'info',
            self::STATUS_OVERDUE => 'danger',
            self::STATUS_PARTIAL => 'primary',
            self::STATUS_DEFAULTED => 'danger',
            default => 'gray',
        };
    }
    
    public function getLoanTypeColorAttribute(): string
    {
        return match($this->loan_type) {
            self::TYPE_BORROW => 'info',
            self::TYPE_LEND => 'warning',
            default => 'gray',
        };
    }
    
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }
        
        $paid = $this->getTotalPaid();
        return min(100, ($paid / $this->total_amount) * 100);
    }
    
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_OVERDUE || 
               ($this->due_date < now() && in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PARTIAL]));
    }
    
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_PARTIAL]);
    }
    
    public function getIsPaidAttribute(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
    
    public function getDueInDaysAttribute(): int
    {
        if ($this->is_paid) {
            return 0;
        }
        
        $now = now();
        $dueDate = $this->due_date;
        
        if ($dueDate < $now) {
            return -$now->diffInDays($dueDate);
        }
        
        return $now->diffInDays($dueDate);
    }
    
    public function getPaymentMethodLabelAttribute(): string
    {
        return BalanceTransaction::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }
    
    // Static Methods
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_PAID => 'Lunas',
            self::STATUS_OVERDUE => 'Jatuh Tempo',
            self::STATUS_PARTIAL => 'Sebagian',
            self::STATUS_DEFAULTED => 'Wanprestasi',
        ];
    }
    
    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_BORROW => 'Pinjam Uang',
            self::TYPE_LEND => 'Pinjamkan Uang',
        ];
    }
    
    public static function getBorrowerTypeOptions(): array
    {
        return [
            'App\Models\Customer' => 'Customer',
            'App\Models\Employee' => 'Karyawan',
            'App\Models\Supplier' => 'Supplier',
            'App\Models\User' => 'User',
        ];
    }
    
    public static function calculateMonthlyInstallment($amount, $interestRate, $months): int
    {
        if ($months <= 0) {
            return (int) $amount;
        }
        
        $totalAmount = $amount + ($amount * ($interestRate / 100));
        return (int) round($totalAmount / $months);
    }
    
    public static function getTotalActiveLoans(): int
    {
        return self::whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PARTIAL, self::STATUS_OVERDUE])
            ->count();
    }
    
    public static function getTotalLoanAmount(string $type = null): int
    {
        $query = self::whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PARTIAL, self::STATUS_OVERDUE]);
        
        if ($type) {
            $query->where('loan_type', $type);
        }
        
        return $query->sum('remaining_amount');
    }
}