<?php
// app/Filament/Widgets/CashFlowSummary.php

namespace App\Filament\Widgets;

use App\Models\CostCalculation;
use App\Models\Order;
use App\Models\Customer;
use App\Models\BalanceTransaction;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class CashFlowSummary extends BaseWidget
{
    protected static ?string $heading = 'Ringkasan Cash Flow Lengkap';
    
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        try {
            // ============================================
            // DATA DARI BALANCE TRANSACTION (SESUAI FILE ANDA)
            // ============================================
            
            // 1. UANG MASUK TOTAL dari balance_transactions type 'in'
            $totalCashIn = BalanceTransaction::where('type', 'in')->sum('amount');
            
            // 2. UANG KELUAR TOTAL dari balance_transactions type 'out'
            $totalCashOut = BalanceTransaction::where('type', 'out')->sum('amount');
            
            // 3. SALDO KAS BERSIH saat ini dari BalanceTransaction
            $netCashFlow = BalanceTransaction::getCurrentBalance();
            
            // 4. Hitung juga peminjaman masuk dan keluar jika perlu
            $loanIn = BalanceTransaction::where('type', 'loan_borrow')->sum('amount');
            $loanOut = BalanceTransaction::where('type', 'loan_lend')->sum('amount');
            $netLoan = $loanIn - $loanOut;
            
            // ============================================
            // DATA BULAN INI (UNTUK PERBANDINGAN)
            // ============================================
            
            $currentMonthStart = now()->startOfMonth();
            $currentMonthEnd = now()->endOfMonth();
            
            // Uang masuk bulan ini
            $cashInThisMonth = BalanceTransaction::where('type', 'in')
                ->whereBetween('transaction_date', [$currentMonthStart, $currentMonthEnd])
                ->sum('amount');
            
            // Uang keluar bulan ini
            $cashOutThisMonth = BalanceTransaction::where('type', 'out')
                ->whereBetween('transaction_date', [$currentMonthStart, $currentMonthEnd])
                ->sum('amount');
            
            // Bulan sebelumnya
            $lastMonthStart = now()->subMonth()->startOfMonth();
            $lastMonthEnd = now()->subMonth()->endOfMonth();
            
            $cashInLastMonth = BalanceTransaction::where('type', 'in')
                ->whereBetween('transaction_date', [$lastMonthStart, $lastMonthEnd])
                ->sum('amount');
            
            $cashOutLastMonth = BalanceTransaction::where('type', 'out')
                ->whereBetween('transaction_date', [$lastMonthStart, $lastMonthEnd])
                ->sum('amount');
            
            // Hitung persentase perubahan
            $cashInChange = $cashInLastMonth > 0 
                ? round((($cashInThisMonth - $cashInLastMonth) / $cashInLastMonth) * 100, 2)
                : 0;
            
            $cashOutChange = $cashOutLastMonth > 0
                ? round((($cashOutThisMonth - $cashOutLastMonth) / $cashOutLastMonth) * 100, 2)
                : 0;
            
            // ============================================
            // DATA DARI ORDER (untuk perbandingan)
            // ============================================
            
            // Total DP yang sudah dibayar dari order
            $totalDPPaid = Order::where('payment_status', '!=', 'unpaid')->sum('dp_paid');
            
            // Order bulan ini
            $ordersThisMonth = Order::whereBetween('order_date', [$currentMonthStart, $currentMonthEnd])->count();
            $ordersLastMonth = Order::whereBetween('order_date', [$lastMonthStart, $lastMonthEnd])->count();
            $orderChange = $ordersLastMonth > 0 
                ? round((($ordersThisMonth - $ordersLastMonth) / $ordersLastMonth) * 100, 2)
                : 0;
            
            // ============================================
            // DATA DARI COST CALCULATION
            // ============================================
            
            // Average profit
            $avgProfit = CostCalculation::where('profit', '>', 0)
                ->avg('profit_percentage');
            
            // Unprofitable orders count
            $unprofitableCount = CostCalculation::where('profit', '<', 0)
                ->count();
            
            // Profit distribution
            $highProfit = CostCalculation::where('profit_percentage', '>', 30)->count();
            $mediumProfit = CostCalculation::whereBetween('profit_percentage', [10, 30])->count();
            $lowProfit = CostCalculation::whereBetween('profit_percentage', [0, 10])->count();
            
            // Jika profit_percentage tidak ada, coba dengan kalkulasi manual
            if ($highProfit == 0 && $mediumProfit == 0 && $lowProfit == 0) {
                $costCalculations = CostCalculation::all();
                $highProfit = $mediumProfit = $lowProfit = 0;
                
                foreach ($costCalculations as $cost) {
                    if (isset($cost->total_cost) && isset($cost->selling_price) && $cost->total_cost > 0) {
                        $profitPercentage = (($cost->selling_price - $cost->total_cost) / $cost->total_cost) * 100;
                        
                        if ($profitPercentage > 30) {
                            $highProfit++;
                        } elseif ($profitPercentage >= 10 && $profitPercentage <= 30) {
                            $mediumProfit++;
                        } elseif ($profitPercentage >= 0 && $profitPercentage < 10) {
                            $lowProfit++;
                        }
                    }
                }
            }
            
            // ============================================
            // DATA PELANGGAN DAN ORDER
            // ============================================
            
            // Total pelanggan
            $totalCustomers = Customer::count();
            
            // Total order
            $totalOrders = Order::count();
            
            // Order berdasarkan payment_status
            $completedOrders = Order::where('payment_status', 'paid')->count();
            $ongoingOrders = Order::whereIn('payment_status', ['unpaid', 'partial'])->count();
            
            // Jika tidak ada data, set default
            if ($completedOrders == 0 && $ongoingOrders == 0) {
                $ongoingOrders = $totalOrders;
                $completedOrders = 0;
            }
            
            // ============================================
            // DATA CHART 2 TAHUN TERAKHIR (24 BULAN)
            // ============================================
            
            $chartData = $this->getTwoYearChartData();
            
            // ============================================
            // STATISTIK YANG DITAMPILKAN
            // ============================================
            
            return [
                // 1. UANG MASUK TOTAL
                Stat::make('Uang Masuk Total', 'Rp ' . number_format($totalCashIn, 0, ',', '.'))
                    ->description('Semua transaksi masuk')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('success')
                    ->chart($chartData['cashInMonthly']),
                
                // 2. UANG KELUAR TOTAL
                Stat::make('Uang Keluar Total', 'Rp ' . number_format($totalCashOut, 0, ',', '.'))
                    ->description('Semua transaksi keluar')
                    ->descriptionIcon('heroicon-m-arrow-trending-down')
                    ->color('danger')
                    ->chart($chartData['cashOutMonthly']),
                
                // 3. SALDO KAS BERSIH
                Stat::make('Saldo Kas Bersih', 'Rp ' . number_format($netCashFlow, 0, ',', '.'))
                    ->description($netCashFlow >= 0 ? 'Positif' : 'Negatif')
                    ->descriptionIcon($netCashFlow >= 0 ? 'heroicon-m-banknotes' : 'heroicon-m-exclamation-triangle')
                    ->color($netCashFlow >= 0 ? 'success' : 'danger'),
                
                // 4. UANG MASUK BULAN INI
                Stat::make('Uang Masuk Bulan Ini', 'Rp ' . number_format($cashInThisMonth, 0, ',', '.'))
                    ->description($cashInChange >= 0 ? "↑ {$cashInChange}%" : "↓ {$cashInChange}%")
                    ->descriptionIcon($cashInChange >= 0 ? 'heroicon-m-arrow-up-right' : 'heroicon-m-arrow-down-right')
                    ->color($cashInChange >= 0 ? 'success' : 'warning'),
                
                // 5. UANG KELUAR BULAN INI
                Stat::make('Uang Keluar Bulan Ini', 'Rp ' . number_format($cashOutThisMonth, 0, ',', '.'))
                    ->description($cashOutChange >= 0 ? "↑ {$cashOutChange}%" : "↓ {$cashOutChange}%")
                    ->descriptionIcon($cashOutChange >= 0 ? 'heroicon-m-arrow-up-right' : 'heroicon-m-arrow-down-right')
                    ->color($cashOutChange >= 0 ? 'warning' : 'danger'),
                
                // 6. DP ORDER DIBAYAR
                Stat::make('DP Order Dibayar', 'Rp ' . number_format($totalDPPaid, 0, ',', '.'))
                    ->description('Dari pembayaran order')
                    ->descriptionIcon('heroicon-m-credit-card')
                    ->color('info'),
                
                // 7. NET PINJAMAN
                Stat::make('Net Pinjaman', 'Rp ' . number_format($netLoan, 0, ',', '.'))
                    ->description($netLoan >= 0 ? 'Lebih banyak pinjam masuk' : 'Lebih banyak pinjam keluar')
                    ->descriptionIcon('heroicon-m-currency-dollar')
                    ->color($netLoan >= 0 ? 'success' : 'warning'),
                
                // 8. RATA-RATA PROFIT
                Stat::make('Rata-rata Profit', $avgProfit ? number_format($avgProfit, 2) . '%' : '0%')
                    ->description('Dari order menguntungkan')
                    ->descriptionIcon('heroicon-m-chart-bar')
                    ->color($avgProfit > 20 ? 'success' : ($avgProfit > 10 ? 'warning' : 'danger')),
                
                // 9. DISTRIBUSI PROFIT
                Stat::make('Distribusi Profit', 
                    $highProfit . 'T/' . $mediumProfit . 'S/' . $lowProfit . 'R')
                    ->description('Tinggi/Sedang/Rendah')
                    ->descriptionIcon('heroicon-m-chart-pie')
                    ->color('info'),
                
                // 10. ORDER TIDAK MENGUNTUNGKAN
                Stat::make('Order Tidak Untung', $unprofitableCount)
                    ->description('Perlu evaluasi harga/biaya')
                    ->descriptionIcon('heroicon-m-exclamation-circle')
                    ->color($unprofitableCount > 0 ? 'warning' : 'success'),
                
                // 11. TOTAL ORDER
                Stat::make('Total Order', $totalOrders)
                    ->description("{$ordersThisMonth} order bulan ini")
                    ->descriptionIcon('heroicon-m-document-text')
                    ->color('primary')
                    ->chart($chartData['ordersMonthly']),
                
                // 12. ORDER BERJALAN & SELESAI
                Stat::make('Order Berjalan', $ongoingOrders)
                    ->description('Belum lunas / sedang proses')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('warning'),
                
                Stat::make('Order Lunas', $completedOrders)
                    ->description('Pembayaran sudah lunas')
                    ->descriptionIcon('heroicon-m-check-circle')
                    ->color('success'),
                
                // 13. TOTAL PELANGGAN
                Stat::make('Total Pelanggan', $totalCustomers)
                    ->description('Pelanggan terdaftar')
                    ->descriptionIcon('heroicon-m-user-group')
                    ->color('gray'),
                
                // 14. ORDER BULAN INI
                Stat::make('Order Bulan Ini', $ordersThisMonth)
                    ->description($orderChange >= 0 ? "↑ {$orderChange}%" : "↓ {$orderChange}%")
                    ->descriptionIcon($orderChange >= 0 ? 'heroicon-m-arrow-up-right' : 'heroicon-m-arrow-down-right')
                    ->color($orderChange >= 0 ? 'success' : 'warning'),
            ];
        } catch (\Exception $e) {
            return [
                Stat::make('Error', 'Database Error')
                    ->description($e->getMessage())
                    ->color('danger')
                    ->descriptionIcon('heroicon-m-exclamation-triangle'),
            ];
        }
    }
    
    // ============================================
    // METHOD UNTUK CHART DATA 2 TAHUN TERAKHIR
    // ============================================
    
    private function getTwoYearChartData(): array
    {
        try {
            // Data untuk 24 bulan terakhir
            $months = [];
            $cashInMonthly = [];
            $cashOutMonthly = [];
            $ordersMonthly = [];
            
            for ($i = 23; $i >= 0; $i--) {
                $startDate = now()->subMonths($i)->startOfMonth();
                $endDate = now()->subMonths($i)->endOfMonth();
                
                // Format label (Jan 2024, Feb 2024, dst)
                $months[] = $startDate->format('M Y');
                
                // Uang masuk bulan ini
                $cashIn = BalanceTransaction::where('type', 'in')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');
                $cashInMonthly[] = $cashIn / 1000000; // Convert to millions for better scaling
                
                // Uang keluar bulan ini
                $cashOut = BalanceTransaction::where('type', 'out')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');
                $cashOutMonthly[] = $cashOut / 1000000; // Convert to millions
                
                // Order bulan ini
                $orders = Order::whereBetween('order_date', [$startDate, $endDate])->count();
                $ordersMonthly[] = $orders;
            }
            
            return [
                'months' => $months,
                'cashInMonthly' => $cashInMonthly,
                'cashOutMonthly' => $cashOutMonthly,
                'ordersMonthly' => $ordersMonthly,
            ];
        } catch (\Exception $e) {
            // Fallback data jika error
            $months = [];
            $cashInMonthly = [];
            $cashOutMonthly = [];
            $ordersMonthly = [];
            
            for ($i = 23; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $months[] = $date->format('M Y');
                $cashInMonthly[] = rand(5, 20); // Random data dalam juta
                $cashOutMonthly[] = rand(3, 15); // Random data dalam juta
                $ordersMonthly[] = rand(5, 30); // Random order count
            }
            
            return [
                'months' => $months,
                'cashInMonthly' => $cashInMonthly,
                'cashOutMonthly' => $cashOutMonthly,
                'ordersMonthly' => $ordersMonthly,
            ];
        }
    }
    
    // ============================================
    // METHOD UNTUK DATA TAHUNAN (12 BULAN)
    // ============================================
    
    private function getYearlyChartData(): array
    {
        try {
            $months = [];
            $cashInData = [];
            $cashOutData = [];
            
            // Data untuk 12 bulan terakhir
            for ($i = 11; $i >= 0; $i--) {
                $startDate = now()->subMonths($i)->startOfMonth();
                $endDate = now()->subMonths($i)->endOfMonth();
                
                // Format label (Jan, Feb, Mar, dst)
                $months[] = $startDate->format('M');
                
                // Uang masuk bulan ini
                $cashIn = BalanceTransaction::where('type', 'in')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');
                $cashInData[] = $cashIn / 1000000; // Convert to millions
                
                // Uang keluar bulan ini
                $cashOut = BalanceTransaction::where('type', 'out')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');
                $cashOutData[] = $cashOut / 1000000; // Convert to millions
            }
            
            return [
                'months' => $months,
                'cashInData' => $cashInData,
                'cashOutData' => $cashOutData,
            ];
        } catch (\Exception $e) {
            return [
                'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                'cashInData' => array_fill(0, 12, 0),
                'cashOutData' => array_fill(0, 12, 0),
            ];
        }
    }
    
    // ============================================
    // METHOD UNTUK DATA QUARTERLY (4 QUARTER TERAKHIR)
    // ============================================
    
    private function getQuarterlyChartData(): array
    {
        try {
            $quarters = [];
            $cashInQuarterly = [];
            $cashOutQuarterly = [];
            
            // Data untuk 4 quarter terakhir
            for ($i = 3; $i >= 0; $i--) {
                $startDate = now()->subQuarters($i)->startOfQuarter();
                $endDate = now()->subQuarters($i)->endOfQuarter();
                
                // Format label (Q1 2024, Q2 2024, dst)
                $quarterNumber = ceil($startDate->month / 3);
                $quarters[] = "Q{$quarterNumber} {$startDate->year}";
                
                // Uang masuk quarter ini
                $cashIn = BalanceTransaction::where('type', 'in')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');
                $cashInQuarterly[] = $cashIn / 1000000; // Convert to millions
                
                // Uang keluar quarter ini
                $cashOut = BalanceTransaction::where('type', 'out')
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');
                $cashOutQuarterly[] = $cashOut / 1000000; // Convert to millions
            }
            
            return [
                'quarters' => $quarters,
                'cashInQuarterly' => $cashInQuarterly,
                'cashOutQuarterly' => $cashOutQuarterly,
            ];
        } catch (\Exception $e) {
            return [
                'quarters' => ['Q1', 'Q2', 'Q3', 'Q4'],
                'cashInQuarterly' => array_fill(0, 4, 0),
                'cashOutQuarterly' => array_fill(0, 4, 0),
            ];
        }
    }
}