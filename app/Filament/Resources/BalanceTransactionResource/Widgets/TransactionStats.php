<?php
// app/Filament/Resources/BalanceTransactionResource/Widgets/TransactionStats.php

namespace App\Filament\Resources\BalanceTransactionResource\Widgets;

use App\Models\BalanceTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TransactionStats extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static bool $isLazy = true;

    protected function getStats(): array
    {
        $today = now()->format('Y-m-d');
        $month = now()->format('Y-m');
        
        $todayIncoming = BalanceTransaction::whereDate('transaction_date', $today)
            ->whereIn('type', ['in', 'loan_borrow'])
            ->sum('amount');
        
        $todayOutgoing = BalanceTransaction::whereDate('transaction_date', $today)
            ->whereIn('type', ['out', 'loan_lend'])
            ->sum('amount');
        
        $monthIncoming = BalanceTransaction::whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->whereIn('type', ['in', 'loan_borrow'])
            ->sum('amount');
        
        $monthOutgoing = BalanceTransaction::whereMonth('transaction_date', now()->month)
            ->whereYear('transaction_date', now()->year)
            ->whereIn('type', ['out', 'loan_lend'])
            ->sum('amount');
        
        $totalBalance = BalanceTransaction::getCurrentBalance();
        
        $transactionCount = BalanceTransaction::count();
        $todayCount = BalanceTransaction::whereDate('transaction_date', $today)->count();
        
        return [
            Stat::make('Saldo Saat Ini', 'Rp ' . number_format($totalBalance, 0, ',', '.'))
                ->description('Total saldo kas')
                ->color($totalBalance >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-banknotes'),
            
            Stat::make('Pemasukan Hari Ini', 'Rp ' . number_format($todayIncoming, 0, ',', '.'))
                ->description('Uang masuk hari ini')
                ->color('success')
                ->icon('heroicon-o-arrow-down-right')
                ->descriptionIcon('heroicon-o-calendar')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
            
            Stat::make('Pengeluaran Hari Ini', 'Rp ' . number_format($todayOutgoing, 0, ',', '.'))
                ->description('Uang keluar hari ini')
                ->color('danger')
                ->icon('heroicon-o-arrow-up-right')
                ->descriptionIcon('heroicon-o-calendar'),
            
            Stat::make('Total Transaksi', number_format($transactionCount, 0, ',', '.'))
                ->description($todayCount . ' transaksi hari ini')
                ->color('info')
                ->icon('heroicon-o-document-text'),
            
            Stat::make('Pemasukan Bulan Ini', 'Rp ' . number_format($monthIncoming, 0, ',', '.'))
                ->description('Uang masuk bulan ' . now()->translatedFormat('F'))
                ->color('success')
                ->icon('heroicon-o-arrow-trending-up'),
            
            Stat::make('Pengeluaran Bulan Ini', 'Rp ' . number_format($monthOutgoing, 0, ',', '.'))
                ->description('Uang keluar bulan ' . now()->translatedFormat('F'))
                ->color('danger')
                ->icon('heroicon-o-arrow-trending-down'),
        ];
    }
}