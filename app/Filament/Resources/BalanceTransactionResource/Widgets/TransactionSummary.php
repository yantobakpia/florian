<?php
// app/Filament/Resources/BalanceTransactionResource/Widgets/TransactionSummary.php

namespace App\Filament\Resources\BalanceTransactionResource\Widgets;

use Filament\Widgets\Widget;
use App\Models\BalanceTransaction;
use Illuminate\Database\Eloquent\Model;

class TransactionSummary extends Widget
{
    protected static string $view = 'filament.resources.balance-transaction-resource.widgets.transaction-summary';
    
    public ?Model $record = null;

    protected function getViewData(): array
    {
        if (!$this->record) {
            return [];
        }

        $date = $this->record->transaction_date->format('Y-m-d');
        
        // Hitung total hari ini
        $todayIncome = BalanceTransaction::where('type', 'in')
            ->whereDate('transaction_date', $date)
            ->sum('amount');
            
        $todayExpense = BalanceTransaction::where('type', 'out')
            ->whereDate('transaction_date', $date)
            ->whereDate('transaction_date', $date)
            ->sum('amount');
        
        $todayNet = $todayIncome - $todayExpense;
        
        // Total transaksi hari ini
        $todayCount = BalanceTransaction::whereDate('transaction_date', $date)->count();

        return [
            'today_income' => $todayIncome,
            'today_expense' => $todayExpense,
            'today_net' => $todayNet,
            'today_count' => $todayCount,
            'transaction_date' => $this->record->transaction_date->format('d F Y'),
        ];
    }
}