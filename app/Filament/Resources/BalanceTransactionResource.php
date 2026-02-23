<?php
// app/Filament/Resources/BalanceTransactionResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceTransactionResource\Pages;
use App\Models\BalanceTransaction;
use App\Models\Order;
use App\Models\MaterialPurchase;
use App\Models\Expense;
use App\Models\SalaryPayment;
use App\Models\CostCalculation;
use App\Models\Loan;
use App\Models\LoanInstallment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class BalanceTransactionResource extends Resource
{
    protected static ?string $model = BalanceTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'description';
    protected static ?string $slug = 'balance-transactions';
    protected static ?string $navigationLabel = 'Transaksi Kas';
    protected static ?string $modelLabel = 'Transaksi Kas';
    protected static ?string $pluralModelLabel = 'Transaksi Kas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Transaksi')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Jenis Transaksi')
                            ->options(BalanceTransaction::getTypeOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $description = match($state) {
                                    BalanceTransaction::TYPE_IN => 'Pemasukan Kas',
                                    BalanceTransaction::TYPE_OUT => 'Pengeluaran Kas',
                                    BalanceTransaction::TYPE_LOAN_BORROW => 'Pinjaman Uang Masuk',
                                    BalanceTransaction::TYPE_LOAN_LEND => 'Pinjaman Uang Keluar',
                                    default => 'Transaksi Kas',
                                };
                                $set('description', $description);
                            }),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(1000)
                            ->step(1000)
                            ->helperText('Harus dalam kelipatan Rp 1.000 (contoh: 40.000, 50.000)')
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                if ($state !== null) {
                                    $component->state((int) $state);
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state && is_numeric($state)) {
                                    $corrected = (int) round($state);
                                    if ($corrected % 1000 !== 0) {
                                        $corrected = round($corrected / 1000) * 1000;
                                    }
                                    if ($corrected < 1000) $corrected = 1000;
                                    $set('amount', $corrected);
                                }
                            }),
                        
                        Forms\Components\TextInput::make('description')
                            ->label('Keterangan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Deskripsi transaksi yang jelas'),
                        
                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options(BalanceTransaction::PAYMENT_METHODS)
                            ->required()
                            ->default('cash'),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->helperText('Catatan tambahan untuk transaksi ini'),
                        
                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Referensi (Opsional)')
                    ->description('Hubungkan transaksi ini dengan data lain di sistem')
                    ->schema([
                        Forms\Components\Select::make('reference_type')
                            ->label('Tipe Referensi')
                            ->options([
                                'App\Models\Order' => 'Order',
                                'App\Models\MaterialPurchase' => 'Pembelian Bahan',
                                'App\Models\Expense' => 'Pengeluaran',
                                'App\Models\SalaryPayment' => 'Gaji Karyawan',
                                'App\Models\CostCalculation' => 'Kalkulasi Biaya',
                                'App\Models\Loan' => 'Pinjaman',
                                'App\Models\LoanInstallment' => 'Angsuran Pinjaman',
                            ])
                            ->nullable()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('reference_id', null)),
                        
                        Forms\Components\Select::make('reference_id')
                            ->label('Pilih Referensi')
                            ->searchable()
                            ->options(function (callable $get) {
                                $referenceType = $get('reference_type');
                                
                                if ($referenceType === 'App\Models\Order') {
                                    return Order::query()
                                        ->with('customer')
                                        ->get()
                                        ->mapWithKeys(function ($order) {
                                            $customerName = $order->customer->name ?? 'Unknown';
                                            return [
                                                $order->id => "#{$order->order_number} - {$customerName} - Rp " . number_format($order->total_price, 0, ',', '.')
                                            ];
                                        })
                                        ->toArray();
                                }
                                
                                if ($referenceType === 'App\Models\CostCalculation') {
                                    return CostCalculation::query()
                                        ->with('order.customer')
                                        ->get()
                                        ->mapWithKeys(function ($calculation) {
                                            $orderNumber = $calculation->order->order_number ?? 'Unknown';
                                            $customerName = $calculation->order->customer->name ?? 'Unknown';
                                            return [
                                                $calculation->id => "Cost #{$calculation->id} - Order #{$orderNumber} - {$customerName}"
                                            ];
                                        })
                                        ->toArray();
                                }
                                
                                if ($referenceType === 'App\Models\Loan') {
                                    return Loan::query()
                                        ->with(['borrower'])
                                        ->get()
                                        ->mapWithKeys(function ($loan) {
                                            $borrowerName = $loan->borrower_name;
                                            $typeLabel = $loan->loan_type === Loan::TYPE_BORROW ? 'Pinjam' : 'Pinjamkan';
                                            return [
                                                $loan->id => "{$loan->loan_number} - {$borrowerName} - {$typeLabel} - Rp " . number_format($loan->amount, 0, ',', '.')
                                            ];
                                        })
                                        ->toArray();
                                }
                                
                                if ($referenceType === 'App\Models\LoanInstallment') {
                                    return LoanInstallment::query()
                                        ->with(['loan.borrower'])
                                        ->get()
                                        ->mapWithKeys(function ($installment) {
                                            $loan = $installment->loan;
                                            $borrowerName = $loan->borrower_name ?? 'Unknown';
                                            return [
                                                $installment->id => "Angsuran #{$installment->id} - {$loan->loan_number} - {$borrowerName} - Rp " . number_format($installment->amount, 0, ',', '.')
                                            ];
                                        })
                                        ->toArray();
                                }
                                
                                return [];
                            })
                            ->nullable()
                            ->visible(fn ($get) => in_array($get('reference_type'), [
                                'App\Models\Order', 
                                'App\Models\CostCalculation',
                                'App\Models\Loan',
                                'App\Models\LoanInstallment'
                            ])),
                    ])
                    ->collapsed()
                    ->columns(2),
                
                Forms\Components\Section::make('Info Saldo')
                    ->schema([
                        Forms\Components\Placeholder::make('current_balance')
                            ->label('Info Saldo')
                            ->content(function (callable $get, $record) {
                                $currentBalance = BalanceTransaction::getCurrentBalance();
                                
                                if ($record && $record->exists) {
                                    $balanceBefore = (int) $record->balance_before;
                                    $amount = (int) $record->amount;
                                    $balanceAfter = (int) $record->balance_after;
                                    
                                    $typeColor = match($record->type) {
                                        BalanceTransaction::TYPE_IN => 'text-green-600',
                                        BalanceTransaction::TYPE_OUT => 'text-red-600',
                                        BalanceTransaction::TYPE_LOAN_BORROW => 'text-blue-600',
                                        BalanceTransaction::TYPE_LOAN_LEND => 'text-orange-600',
                                        default => 'text-gray-600',
                                    };
                                    
                                    $typeLabel = match($record->type) {
                                        BalanceTransaction::TYPE_IN => '+',
                                        BalanceTransaction::TYPE_OUT => '-',
                                        BalanceTransaction::TYPE_LOAN_BORROW => '+ (Pinjam)',
                                        BalanceTransaction::TYPE_LOAN_LEND => '- (Pinjamkan)',
                                        default => '',
                                    };
                                    
                                    return new HtmlString(
                                        '<div class="space-y-2 p-4 bg-gray-50 rounded-lg">' .
                                        '<div class="flex justify-between">' .
                                        '<span class="font-medium">Saldo Sebelum:</span>' .
                                        '<span class="font-bold">Rp ' . number_format($balanceBefore, 0, ',', '.') . '</span>' .
                                        '</div>' .
                                        '<div class="flex justify-between">' .
                                        '<span class="font-medium">Transaksi:</span>' .
                                        '<span class="font-bold ' . $typeColor . '">' .
                                        $typeLabel . ' Rp ' . number_format($amount, 0, ',', '.') . 
                                        '</span>' .
                                        '</div>' .
                                        '<div class="flex justify-between border-t pt-2">' .
                                        '<span class="font-bold">Saldo Sesudah:</span>' .
                                        '<span class="font-bold ' . ($balanceAfter >= 0 ? 'text-green-600' : 'text-red-600') . '">' .
                                        'Rp ' . number_format($balanceAfter, 0, ',', '.') .
                                        '</span>' .
                                        '</div>' .
                                        '</div>'
                                    );
                                }
                                
                                $type = $get('type');
                                $amount = (int) ($get('amount') ?? 0);
                                
                                $newBalance = (int) $currentBalance;
                                if (in_array($type, [BalanceTransaction::TYPE_IN, BalanceTransaction::TYPE_LOAN_BORROW])) {
                                    $newBalance = $currentBalance + $amount;
                                } elseif (in_array($type, [BalanceTransaction::TYPE_OUT, BalanceTransaction::TYPE_LOAN_LEND])) {
                                    $newBalance = $currentBalance - $amount;
                                }
                                
                                $typeLabel = match($type) {
                                    BalanceTransaction::TYPE_IN => '+',
                                    BalanceTransaction::TYPE_OUT => '-',
                                    BalanceTransaction::TYPE_LOAN_BORROW => '+ (Pinjam)',
                                    BalanceTransaction::TYPE_LOAN_LEND => '- (Pinjamkan)',
                                    default => '',
                                };
                                
                                $typeColor = match($type) {
                                    BalanceTransaction::TYPE_IN => 'text-green-600',
                                    BalanceTransaction::TYPE_OUT => 'text-red-600',
                                    BalanceTransaction::TYPE_LOAN_BORROW => 'text-blue-600',
                                    BalanceTransaction::TYPE_LOAN_LEND => 'text-orange-600',
                                    default => 'text-gray-600',
                                };
                                
                                return new HtmlString(
                                    '<div class="space-y-2 p-4 bg-gray-50 rounded-lg">' .
                                    '<div class="flex justify-between">' .
                                    '<span class="font-medium">Saldo Saat Ini:</span>' .
                                    '<span class="font-semibold">Rp ' . number_format($currentBalance, 0, ',', '.') . '</span>' .
                                    '</div>' .
                                    '<div class="flex justify-between">' .
                                    '<span class="font-medium">Transaksi Ini:</span>' .
                                    '<span class="font-semibold ' . $typeColor . '">' .
                                    $typeLabel . ' Rp ' . number_format($amount, 0, ',', '.') .
                                    '</span>' .
                                    '</div>' .
                                    '<div class="flex justify-between border-t pt-2">' .
                                    '<span class="font-bold">Saldo Setelah:</span>' .
                                    '<span class="font-bold ' . ($newBalance >= 0 ? 'text-green-600' : 'text-red-600') . '">' .
                                    'Rp ' . number_format($newBalance, 0, ',', '.') .
                                    '</span>' .
                                    '</div>' .
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->hidden(fn ($record) => $record?->exists),
            ]);
    }

    /**
     * INFOLIST untuk View Page
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record(fn ($record) => $record)
            ->schema([
                Section::make('Informasi Transaksi')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                IconEntry::make('type')
                                    ->label('Jenis Transaksi')
                                    ->icon(fn ($state) => match($state) {
                                        BalanceTransaction::TYPE_IN => 'heroicon-o-arrow-down-circle',
                                        BalanceTransaction::TYPE_OUT => 'heroicon-o-arrow-up-circle',
                                        BalanceTransaction::TYPE_LOAN_BORROW => 'heroicon-o-hand-raised',
                                        BalanceTransaction::TYPE_LOAN_LEND => 'heroicon-o-hand-thumb-down',
                                        default => 'heroicon-o-question-mark-circle',
                                    })
                                    ->color(fn ($record) => $record->type_color),
                                    
                                TextEntry::make('transaction_date')
                                    ->label('Tanggal Transaksi')
                                    ->dateTime('d/m/Y H:i'),
                                    
                                TextEntry::make('payment_method')
                                    ->label('Metode Pembayaran')
                                    ->badge()
                                    ->color(fn ($state) => match($state) {
                                        'cash' => 'success',
                                        'transfer' => 'info',
                                        'qris' => 'primary',
                                        default => 'gray',
                                    }),
                            ]),
                            
                        TextEntry::make('description')
                            ->label('Keterangan')
                            ->columnSpanFull(),
                            
                        TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan')
                            ->html(),
                    ])
                    ->collapsible(false),
                    
                Section::make('Detail Keuangan')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount')
                                    ->label('Jumlah Transaksi')
                                    ->money('IDR')
                                    ->color(fn ($record) => $record->type_color)
                                    ->weight('bold'),
                                    
                                TextEntry::make('balance_before')
                                    ->label('Saldo Sebelum')
                                    ->money('IDR'),
                                    
                                TextEntry::make('balance_after')
                                    ->label('Saldo Sesudah')
                                    ->money('IDR')
                                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                            ]),
                    ])
                    ->collapsible(),
                    
                Section::make('Referensi')
                    ->schema([
                        TextEntry::make('reference_info')
                            ->label('Referensi Terkait')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada referensi')
                            ->url(function ($record) {
                                if ($record->reference_type === 'App\Models\Order') {
                                    return OrderResource::getUrl('view', ['record' => $record->reference_id]);
                                }
                                return null;
                            }),
                            
                        TextEntry::make('user.name')
                            ->label('Dibuat Oleh')
                            ->placeholder('Tidak diketahui'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis Transaksi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        BalanceTransaction::TYPE_IN => 'UANG MASUK',
                        BalanceTransaction::TYPE_OUT => 'UANG KELUAR',
                        BalanceTransaction::TYPE_LOAN_BORROW => 'PINJAM MASUK',
                        BalanceTransaction::TYPE_LOAN_LEND => 'PINJAM KELUAR',
                        default => strtoupper($state),
                    })
                    ->color(fn ($state) => match($state) {
                        BalanceTransaction::TYPE_IN => 'success',
                        BalanceTransaction::TYPE_OUT => 'danger',
                        BalanceTransaction::TYPE_LOAN_BORROW => 'info',
                        BalanceTransaction::TYPE_LOAN_LEND => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color(function ($record) {
                        return match($record->type) {
                            BalanceTransaction::TYPE_IN => 'success',
                            BalanceTransaction::TYPE_OUT => 'danger',
                            BalanceTransaction::TYPE_LOAN_BORROW => 'info',
                            BalanceTransaction::TYPE_LOAN_LEND => 'warning',
                            default => 'gray',
                        };
                    })
                    ->alignRight()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Saldo Sebelum')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo Sesudah')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color(fn ($record) => $record->balance_after >= 0 ? 'success' : 'danger')
                    ->alignRight()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->description;
                    }),
                
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn ($state) => BalanceTransaction::PAYMENT_METHODS[$state] ?? strtoupper($state))
                    ->color(fn ($state) => match($state) {
                        'cash' => 'success',
                        'transfer' => 'info',
                        'qris' => 'primary',
                        'debit' => 'warning',
                        'credit' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('reference_info')
                    ->label('Referensi')
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->reference_info;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->notes;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Transaksi')
                    ->options(BalanceTransaction::getTypeOptions())
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(BalanceTransaction::PAYMENT_METHODS)
                    ->multiple(),
                
                Tables\Filters\Filter::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('to')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query, $date) => $query->whereDate('transaction_date', '>=', $date)
                            )
                            ->when(
                                $data['to'],
                                fn ($query, $date) => $query->whereDate('transaction_date', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['from'] && !$data['to']) {
                            return null;
                        }
                        
                        $indicators = [];
                        if ($data['from']) {
                            $indicators[] = 'Dari: ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['to']) {
                            $indicators[] = 'Sampai: ' . \Carbon\Carbon::parse($data['to'])->format('d/m/Y');
                        }
                        
                        return implode(' ', $indicators);
                    }),
                
                Tables\Filters\Filter::make('amount_range')
                    ->label('Range Jumlah')
                    ->form([
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Minimal')
                            ->numeric()
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('max_amount')
                            ->label('Maksimal')
                            ->numeric()
                            ->prefix('Rp'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn ($query, $amount) => $query->where('amount', '>=', $amount)
                            )
                            ->when(
                                $data['max_amount'],
                                fn ($query, $amount) => $query->where('amount', '<=', $amount)
                            );
                    }),
                
                Tables\Filters\SelectFilter::make('reference_type')
                    ->label('Tipe Referensi')
                    ->options([
                        'App\Models\Order' => 'Order',
                        'App\Models\CostCalculation' => 'Cost Calculation',
                        'App\Models\Loan' => 'Pinjaman',
                        'App\Models\LoanInstallment' => 'Angsuran Pinjaman',
                    ])
                    ->multiple(),
                
                Tables\Filters\TrashedFilter::make()
                    ->label('Status Data')
                    ->placeholder('Semua Data')
                    ->options([
                        '' => 'Aktif',
                        'withTrashed' => 'Termasuk Dihapus',
                        'onlyTrashed' => 'Hanya Dihapus',
                    ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Export semua data transaksi ke Excel')
                    ->exports([
                        ExcelExport::make('full_export')
                            ->label('Export Lengkap')
                            ->withFilename(fn () => 'transaksi-kas-lengkap-' . date('Y-m-d-His'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                            ->modifyQueryUsing(fn ($query) => $query->with('user'))
                            ->withColumns([
                                Column::make('id')
                                    ->heading('ID'),
                                Column::make('transaction_date')
                                    ->format('d/m/Y H:i')
                                    ->heading('TANGGAL TRANSAKSI'),
                                Column::make('type')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        BalanceTransaction::TYPE_IN => 'UANG MASUK',
                                        BalanceTransaction::TYPE_OUT => 'UANG KELUAR',
                                        BalanceTransaction::TYPE_LOAN_BORROW => 'PINJAM MASUK',
                                        BalanceTransaction::TYPE_LOAN_LEND => 'PINJAM KELUAR',
                                        default => strtoupper($state),
                                    })
                                    ->heading('JENIS TRANSAKSI'),
                                Column::make('amount')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                                    ->heading('JUMLAH (RP)'),
                                Column::make('balance_before')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                                    ->heading('SALDO SEBELUM (RP)'),
                                Column::make('balance_after')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                                    ->heading('SALDO SESUDAH (RP)'),
                                Column::make('description')
                                    ->heading('KETERANGAN'),
                                Column::make('payment_method')
                                    ->formatStateUsing(fn ($state) => BalanceTransaction::PAYMENT_METHODS[$state] ?? strtoupper($state))
                                    ->heading('METODE PEMBAYARAN'),
                                Column::make('notes')
                                    ->heading('CATATAN'),
                                Column::make('reference_info')
                                    ->heading('REFERENSI'),
                                Column::make('user.name')
                                    ->heading('DIBUAT OLEH'),
                                Column::make('created_at')
                                    ->format('d/m/Y H:i')
                                    ->heading('DIBUAT PADA'),
                            ]),
                    ])
                    ->modalHeading('Export Data Transaksi')
                    ->modalDescription('Pilih format export yang diinginkan')
                    ->modalSubmitActionLabel('Export Sekarang')
                    ->modalCancelActionLabel('Batal')
                    ->modalWidth('lg'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('gray'),
                
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-o-pencil')
                    ->color('gray'),
                
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->hidden(fn ($record) => $record->trashed()),
                
                Tables\Actions\RestoreAction::make()
                    ->label('')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->trashed()),
                
                Tables\Actions\ForceDeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn ($record) => $record->trashed()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Hapus Terpilih')
                    ->requiresConfirmation(),
                
                Tables\Actions\RestoreBulkAction::make()
                    ->label('Restore Terpilih'),
                
                Tables\Actions\ForceDeleteBulkAction::make()
                    ->label('Force Delete Terpilih')
                    ->requiresConfirmation(),
                
                ExportBulkAction::make()
                    ->label('Export Terpilih')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->exports([
                        ExcelExport::make('selected_transactions')
                            ->label('Transaksi Terpilih')
                            ->fromTable()
                            ->withFilename(fn () => 'transaksi-kas-terpilih-' . date('Y-m-d-His'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                            ->modifyQueryUsing(fn ($query) => $query->with('user'))
                            ->withColumns([
                                Column::make('id')
                                    ->heading('ID'),
                                Column::make('transaction_date')
                                    ->format('d/m/Y H:i')
                                    ->heading('TANGGAL TRANSAKSI'),
                                Column::make('type')
                                    ->formatStateUsing(fn ($state) => match($state) {
                                        BalanceTransaction::TYPE_IN => 'UANG MASUK',
                                        BalanceTransaction::TYPE_OUT => 'UANG KELUAR',
                                        BalanceTransaction::TYPE_LOAN_BORROW => 'PINJAM MASUK',
                                        BalanceTransaction::TYPE_LOAN_LEND => 'PINJAM KELUAR',
                                        default => strtoupper($state),
                                    })
                                    ->heading('JENIS TRANSAKSI'),
                                Column::make('amount')
                                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->heading('JUMLAH'),
                                Column::make('balance_before')
                                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->heading('SALDO SEBELUM'),
                                Column::make('balance_after')
                                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->heading('SALDO SESUDAH'),
                                Column::make('description')
                                    ->heading('KETERANGAN'),
                                Column::make('payment_method')
                                    ->formatStateUsing(fn ($state) => BalanceTransaction::PAYMENT_METHODS[$state] ?? strtoupper($state))
                                    ->heading('METODE PEMBAYARAN'),
                                Column::make('notes')
                                    ->heading('CATATAN'),
                                Column::make('reference_info')
                                    ->heading('REFERENSI'),
                                Column::make('user.name')
                                    ->heading('DIBUAT OLEH'),
                                Column::make('created_at')
                                    ->format('d/m/Y H:i')
                                    ->heading('DIBUAT PADA'),
                            ]),
                    ])
                    ->modalHeading('Export Data Terpilih')
                    ->modalDescription('Export data transaksi yang dipilih ke Excel')
                    ->modalSubmitActionLabel('Export')
                    ->modalCancelActionLabel('Batal'),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Transaksi Baru'),
            ])
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100, 'all']);
    }

    public static function getRelations(): array
    {
        return [
            // No relations needed for balance transactions
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBalanceTransactions::route('/'),
            'create' => Pages\CreateBalanceTransaction::route('/create'),
            'view' => Pages\ViewBalanceTransaction::route('/{record}'),
            'edit' => Pages\EditBalanceTransaction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $balance = BalanceTransaction::getCurrentBalance();
        return 'Rp ' . number_format($balance, 0, ',', '.');
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $balance = BalanceTransaction::getCurrentBalance();
        return $balance >= 0 ? 'success' : 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Saldo Kas Saat Ini';
    }

    /**
     * Custom export untuk laporan keuangan
     */
    public static function getFinancialReportExport(): ExcelExport
    {
        return ExcelExport::make()
            ->withFilename(fn () => 'laporan-keuangan-' . date('Y-m-d-His'))
            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->withColumns([
                Column::make('transaction_date')
                    ->format('d/m/Y H:i')
                    ->heading('TANGGAL'),
                Column::make('type')
                    ->formatStateUsing(fn ($state) => match($state) {
                        BalanceTransaction::TYPE_IN => 'PEMASUKAN',
                        BalanceTransaction::TYPE_OUT => 'PENGELUARAN',
                        BalanceTransaction::TYPE_LOAN_BORROW => 'PINJAMAN MASUK',
                        BalanceTransaction::TYPE_LOAN_LEND => 'PINJAMAN KELUAR',
                        default => strtoupper($state),
                    })
                    ->heading('KATEGORI'),
                Column::make('amount')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                    ->heading('NOMINAL'),
                Column::make('description')
                    ->heading('KETERANGAN'),
                Column::make('payment_method')
                    ->formatStateUsing(fn ($state) => BalanceTransaction::PAYMENT_METHODS[$state] ?? strtoupper($state))
                    ->heading('PEMBAYARAN'),
                Column::make('reference_info')
                    ->heading('REFERENSI'),
            ]);
    }

    /**
     * Custom export untuk arus kas
     */
    public static function getCashFlowExport(): ExcelExport
    {
        return ExcelExport::make()
            ->withFilename(fn () => 'arus-kas-' . date('Y-m-d-His'))
            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
            ->modifyQueryUsing(fn ($query) => 
                $query->orderBy('transaction_date')
                    ->with('user')
            )
            ->withColumns([
                Column::make('transaction_date')
                    ->format('d/m/Y')
                    ->heading('TANGGAL'),
                Column::make('type')
                    ->formatStateUsing(fn ($state) => match($state) {
                        BalanceTransaction::TYPE_IN => 'DEBIT',
                        BalanceTransaction::TYPE_OUT => 'KREDIT',
                        BalanceTransaction::TYPE_LOAN_BORROW => 'PINJAMAN DEBIT',
                        BalanceTransaction::TYPE_LOAN_LEND => 'PINJAMAN KREDIT',
                        default => strtoupper($state),
                    })
                    ->heading('TIPE'),
                Column::make('amount')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                    ->heading('DEBIT/KREDIT'),
                Column::make('balance_before')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                    ->heading('SALDO AWAL'),
                Column::make('balance_after')
                    ->formatStateUsing(fn ($state) => number_format($state, 0, ',', '.'))
                    ->heading('SALDO AKHIR'),
                Column::make('description')
                    ->heading('URAIAN'),
            ]);
    }
}