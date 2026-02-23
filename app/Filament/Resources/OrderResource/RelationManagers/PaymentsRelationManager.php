<?php
// app/Filament/Resources/OrderResource/RelationManagers/PaymentsRelationManager.php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Order;
use App\Models\BalanceTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'balanceTransactions';
    
    protected static ?string $title = 'Riwayat Pembayaran';
    
    protected static ?string $recordTitleAttribute = 'description';
    
    public static function getLabel(): string
    {
        return 'Pembayaran';
    }
    
    public static function getPluralLabel(): string
    {
        return 'Pembayaran';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Pembayaran')
                    ->schema([
                        Forms\Components\Hidden::make('type')
                            ->default(BalanceTransaction::TYPE_IN)
                            ->required(),
                        
                        Forms\Components\Hidden::make('reference_type')
                            ->default(Order::class)
                            ->required(),
                        
                        Forms\Components\Hidden::make('reference_id')
                            ->default(fn ($livewire) => $livewire->ownerRecord->id)
                            ->required(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah Pembayaran')
                            ->prefix('Rp')
                            ->numeric()
                            ->required()
                            ->minValue(1000)
                            ->step(1000)
                            ->helperText(function ($livewire) {
                                $order = $livewire->ownerRecord;
                                $remaining = $order->total_price - $order->net_paid;
                                
                                if ($remaining > 0) {
                                    return new HtmlString(
                                        'Sisa tagihan: <span class="font-bold text-danger">Rp ' . 
                                        number_format($remaining, 0, ',', '.') . 
                                        '</span> | Total order: <span class="font-bold">Rp ' . 
                                        number_format($order->total_price, 0, ',', '.') . '</span>'
                                    );
                                }
                                
                                return 'Total order: Rp ' . number_format($order->total_price, 0, ',', '.');
                            })
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
                        
                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options(BalanceTransaction::PAYMENT_METHODS)
                            ->required()
                            ->default('cash'),
                        
                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Tanggal Pembayaran')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Pembayaran')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Contoh: DP 50%, Pelunasan, Transfer via BCA, dll.'),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Info Order')
                    ->schema([
                        Forms\Components\Placeholder::make('order_info')
                            ->label('Informasi Order')
                            ->content(function ($livewire) {
                                $order = $livewire->ownerRecord;
                                
                                $statusColor = match($order->payment_status) {
                                    'paid' => 'success',
                                    'dp' => 'warning',
                                    'partial' => 'info',
                                    default => 'danger',
                                };
                                
                                $statusLabel = match($order->payment_status) {
                                    'paid' => 'LUNAS',
                                    'dp' => 'DP',
                                    'partial' => 'CICILAN',
                                    default => 'BELUM BAYAR',
                                };
                                
                                return new HtmlString(
                                    '<div class="space-y-1 p-3 bg-gray-50 rounded-lg">' .
                                    '<div class="flex justify-between">' .
                                    '<span class="font-medium">No. Order:</span>' .
                                    '<span class="font-bold">' . $order->order_number . '</span>' .
                                    '</div>' .
                                    '<div class="flex justify-between">' .
                                    '<span class="font-medium">Total Harga:</span>' .
                                    '<span class="font-bold">Rp ' . number_format($order->total_price, 0, ',', '.') . '</span>' .
                                    '</div>' .
                                    '<div class="flex justify-between">' .
                                    '<span class="font-medium">Sudah Dibayar:</span>' .
                                    '<span class="font-bold text-success">Rp ' . number_format($order->net_paid, 0, ',', '.') . '</span>' .
                                    '</div>' .
                                    '<div class="flex justify-between">' .
                                    '<span class="font-medium">Sisa Pembayaran:</span>' .
                                    '<span class="font-bold text-danger">Rp ' . number_format($order->remaining_payment, 0, ',', '.') . '</span>' .
                                    '</div>' .
                                    '<div class="flex justify-between">' .
                                    '<span class="font-medium">Status Pembayaran:</span>' .
                                    '<span class="px-2 py-1 text-xs font-bold rounded bg-' . $statusColor . '-100 text-' . $statusColor . '-800">' . $statusLabel . '</span>' .
                                    '</div>' .
                                    '</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->color('success')
                    ->alignRight()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('IDR')
                            ->label('Total Pembayaran')
                    ]),
                
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
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
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Saldo Sebelum')
                    ->money('IDR')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo Setelah')
                    ->money('IDR')
                    ->alignRight()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(50)
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('transaction_date')
                    ->label('Filter Tanggal')
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
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(BalanceTransaction::PAYMENT_METHODS)
                    ->multiple(),
                
                Tables\Filters\Filter::make('amount_range')
                    ->label('Range Jumlah')
                    ->form([
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Minimal')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_amount')
                            ->label('Maksimal')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0),
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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Pembayaran')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->disabled(function ($livewire) {
                        $order = $livewire->ownerRecord;
                        return $order->total_price <= $order->net_paid;
                    })
                    ->tooltip(function ($livewire) {
                        $order = $livewire->ownerRecord;
                        if ($order->total_price <= $order->net_paid) {
                            return 'Order sudah lunas';
                        }
                        return 'Tambah pembayaran baru';
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        if (empty($data['description'])) {
                            $data['description'] = 'Pembayaran Order';
                        }
                        return $data;
                    })
                    ->after(function ($livewire) {
                        // Update payment status setelah pembayaran
                        $order = $livewire->ownerRecord;
                        $order->refresh();
                        $order->updatePaymentStatus();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('gray'),
                
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-o-pencil')
                    ->color('gray')
                    ->after(function ($livewire) {
                        // Update payment status setelah edit
                        $order = $livewire->ownerRecord;
                        $order->refresh();
                        $order->updatePaymentStatus();
                    }),
                
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Pembayaran')
                    ->modalDescription('Apakah Anda yakin ingin menghapus pembayaran ini?')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->after(function ($livewire) {
                        // Update payment status setelah hapus
                        $order = $livewire->ownerRecord;
                        $order->refresh();
                        $order->updatePaymentStatus();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Hapus Terpilih')
                    ->requiresConfirmation()
                    ->after(function ($livewire) {
                        // Update payment status setelah hapus banyak
                        $order = $livewire->ownerRecord;
                        $order->refresh();
                        $order->updatePaymentStatus();
                    }),
                
                ExportBulkAction::make()
                    ->label('Export Pembayaran')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn ($livewire) => 'pembayaran-order-' . $livewire->ownerRecord->order_number . '-' . date('Y-m-d-His'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                            ->modifyQueryUsing(fn ($query, $livewire) => $query->where('reference_id', $livewire->ownerRecord->id))
                            ->withColumns([
                                Column::make('transaction_date')
                                    ->format('d/m/Y H:i')
                                    ->heading('TANGGAL PEMBAYARAN'),
                                Column::make('amount')
                                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->heading('JUMLAH PEMBAYARAN'),
                                Column::make('payment_method')
                                    ->formatStateUsing(fn ($state) => BalanceTransaction::PAYMENT_METHODS[$state] ?? strtoupper($state))
                                    ->heading('METODE PEMBAYARAN'),
                                Column::make('balance_before')
                                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->heading('SALDO SEBELUM'),
                                Column::make('balance_after')
                                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->heading('SALDO SESUDAH'),
                                Column::make('description')
                                    ->heading('KETERANGAN'),
                                Column::make('notes')
                                    ->heading('CATATAN'),
                                Column::make('user.name')
                                    ->heading('DIBUAT OLEH'),
                                Column::make('created_at')
                                    ->format('d/m/Y H:i')
                                    ->heading('DIBUAT PADA'),
                            ]),
                    ])
                    ->modalHeading('Export Data Pembayaran')
                    ->modalDescription('Export data pembayaran untuk order ini ke Excel')
                    ->modalSubmitActionLabel('Export')
                    ->modalCancelActionLabel('Batal'),
            ])
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateDescription('Tambahkan pembayaran untuk order ini.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Pembayaran Pertama')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->disabled(function ($livewire) {
                        $order = $livewire->ownerRecord;
                        return $order->total_price <= $order->net_paid;
                    }),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->deferLoading()
            ->striped();
    }

    /**
     * Modify the base query
     */
    public function modifyQuery(Builder $query): Builder
    {
        return $query
            ->where('reference_type', Order::class)
            ->where('reference_id', $this->ownerRecord->id)
            ->where('type', BalanceTransaction::TYPE_IN)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Check if the relation manager should be registered
     */
    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return true;
    }
}