<?php

namespace App\Filament\Widgets;

use App\Models\BalanceTransaction;
use App\Models\Order;
use App\Models\CostCalculation;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Illuminate\Contracts\Support\Htmlable;

class CashFlowBarChart extends ChartWidget
{
    protected static ?string $heading = 'Uang Masuk vs Uang Keluar';
    protected static ?int $sort = 2;
    protected static ?string $maxHeight = '400px';
    
    // Property untuk periode
    public ?string $period = '1years';
    
    // Label dinamis berdasarkan periode
    public function getHeading(): string|Htmlable
    {
        $periodLabel = match($this->period) {
            
            '1year' => '1 Tahun Terakhir', 
            '6months' => '6 Bulan Terakhir',
            '3months' => '3 Bulan Terakhir',
            default => '2 Tahun Terakhir',
        };
        
        return "Uang Masuk vs Uang Keluar ({$periodLabel})";
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    // Form untuk memilih periode
    protected function getForm(): Form
    {
        return parent::getForm()
            ->schema([
                Select::make('period')
                    ->label('Periode')
                    ->options([
                       
                        '1year' => '1 Tahun Terakhir',
                        '6months' => '6 Bulan Terakhir',
                        '3months' => '3 Bulan Terakhir',
                    ])
                    ->default('2years')
                    ->reactive()
                    ->afterStateUpdated(fn () => $this->updateChartData()),
            ]);
    }

    protected function getData(): array
    {
        $labels = [];
        $cashIn = [];
        $cashOut = [];
        
        // Tentukan jumlah bulan berdasarkan periode
        $months = match($this->period) {
            '1year' => 12,   // 1 tahun = 12 bulan
            '6months' => 6,  // 6 bulan
            '3months' => 3,  // 3 bulan
            default => 24,
        };
        
        // Data untuk bulan-bulan terakhir sesuai periode
        for ($i = $months - 1; $i >= 0; $i--) {
            $startDate = now()->subMonths($i)->startOfMonth();
            $endDate = now()->subMonths($i)->endOfMonth();
            
            // Format label berdasarkan periode
            if ($months <= 6) {
                // Untuk periode pendek, tampilkan tanggal lengkap
                $labels[] = $startDate->format('d M');
            } else if ($months <= 12) {
                // Untuk 1 tahun, tampilkan bulan
                $labels[] = $startDate->format('M');
            } else {
                // Untuk 2 tahun, tampilkan bulan dan tahun singkat
                $labels[] = $startDate->format('M y');
            }
            
            // UANG MASUK dari BalanceTransaction (type 'in')
            $monthCashIn = BalanceTransaction::where('type', 'in')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');
            $cashIn[] = $monthCashIn; // Data ASLI, tidak dikonversi ke juta
            
            // UANG KELUAR dari BalanceTransaction (type 'out')
            $monthCashOut = BalanceTransaction::where('type', 'out')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');
            $cashOut[] = $monthCashOut; // Data ASLI, tidak dikonversi ke juta
        }

        return [
            'datasets' => [
                [
                    'label' => 'Uang Masuk (Rp)',
                    'data' => $cashIn,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Uang Keluar (Rp)',
                    'data' => $cashOut,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        // Tentukan jumlah bulan untuk menyesuaikan tampilan
        $months = match($this->period) {
            '2years' => 24,
            '1year' => 12,
            '6months' => 6,
            '3months' => 3,
            default => 24,
        };
        
        $options = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 20,
                        'font' => [
                            'size' => 12
                        ]
                    ]
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { 
                            return context.dataset.label.replace("(Rp)", "") + ": Rp " + 
                                   new Intl.NumberFormat("id-ID").format(context.raw); 
                        }',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'maxRotation' => $months > 12 ? 45 : 0,
                        'minRotation' => $months > 12 ? 45 : 0,
                        'font' => [
                            'size' => $months > 12 ? 9 : 10
                        ]
                    ],
                    'grid' => [
                        'display' => true
                    ],
                    'title' => [
                        'display' => true,
                        'text' => $months <= 6 ? 'Tanggal' : 'Bulan',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ]
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { 
                            if (value >= 1000000000) {
                                return "Rp " + (value / 1000000000).toFixed(1) + "M";
                            } else if (value >= 1000000) {
                                return "Rp " + (value / 1000000).toFixed(1) + "jt";
                            } else if (value >= 1000) {
                                return "Rp " + (value / 1000).toFixed(0) + "rb";
                            } else {
                                return "Rp " + new Intl.NumberFormat("id-ID").format(value);
                            }
                        }',
                        'font' => [
                            'size' => 10
                        ]
                    ],
                    'grid' => [
                        'display' => true
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Jumlah (Rupiah)',
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ]
                ],
            ],
        ];
        
        return $options;
    }
    
    // Method untuk mendapatkan statistik ringkasan
    public function getSummaryStats(): array
    {
        $months = match($this->period) {
            '2years' => 24,
            '1year' => 12,
            '6months' => 6,
            '3months' => 3,
            default => 24,
        };
        
        $startDate = now()->subMonths($months)->startOfMonth();
        $endDate = now()->endOfMonth();
        
        $totalCashIn = BalanceTransaction::where('type', 'in')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');
            
        $totalCashOut = BalanceTransaction::where('type', 'out')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum('amount');
            
        $netCashFlow = $totalCashIn - $totalCashOut;
        
        // Format angka dengan satuan yang sesuai
        $formatAmount = function($amount) {
            if ($amount >= 1000000000) {
                return 'Rp ' . number_format($amount / 1000000000, 1, ',', '.') . ' M';
            } else if ($amount >= 1000000) {
                return 'Rp ' . number_format($amount / 1000000, 1, ',', '.') . ' jt';
            } else if ($amount >= 1000) {
                return 'Rp ' . number_format($amount / 1000, 0, ',', '.') . ' rb';
            } else {
                return 'Rp ' . number_format($amount, 0, ',', '.');
            }
        };
        
        return [
            'total_in' => $totalCashIn,
            'total_out' => $totalCashOut,
            'net' => $netCashFlow,
            'total_in_formatted' => $formatAmount($totalCashIn),
            'total_out_formatted' => $formatAmount($totalCashOut),
            'net_formatted' => $formatAmount($netCashFlow),
            'period' => $this->period,
            'start_date' => $startDate->format('d M Y'),
            'end_date' => $endDate->format('d M Y'),
            'months' => $months,
        ];
    }
    
    // Method untuk menampilkan summary di widget
    public function getSummaryHtml(): string
    {
        $stats = $this->getSummaryStats();
        
        return '
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="bg-green-50 p-3 rounded border border-green-200">
                    <p class="text-sm text-green-800">Total Uang Masuk</p>
                    <p class="text-xl font-bold text-green-900">' . $stats['total_in_formatted'] . '</p>
                </div>
                <div class="bg-red-50 p-3 rounded border border-red-200">
                    <p class="text-sm text-red-800">Total Uang Keluar</p>
                    <p class="text-xl font-bold text-red-900">' . $stats['total_out_formatted'] . '</p>
                </div>
                <div class="bg-blue-50 p-3 rounded border border-blue-200">
                    <p class="text-sm text-blue-800">Net Cash Flow</p>
                    <p class="text-xl font-bold ' . ($stats['net'] >= 0 ? 'text-green-900' : 'text-red-900') . '">' . $stats['net_formatted'] . '</p>
                </div>
            </div>
        ';
    }
}