<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Order'),
            
            Actions\Action::make('add_payment')
                ->label('Tambah Pembayaran')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Jumlah')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(fn (Order $record) => $record->remaining_payment)
                        ->helperText(fn (Order $record) => 'Sisa: Rp ' . number_format($record->remaining_payment, 0, ',', '.')),
                    
                    \Filament\Forms\Components\Select::make('method')
                        ->label('Metode')
                        ->options([
                            'cash' => 'Cash',
                            'transfer' => 'Transfer',
                            'qris' => 'QRIS',
                        ])
                        ->default('cash'),
                ])
                ->action(function (Order $record, array $data) {
                    $validation = $record->canAcceptPayment($data['amount']);
                    if (!$validation['success']) {
                        throw new \Exception($validation['message']);
                    }
                    
                    $record->recordPayment(
                        $data['amount'],
                        'partial',
                        $data['method'],
                        null,
                        'Pembayaran dari halaman view'
                    );
                    
                    \Filament\Notifications\Notification::make()
                        ->title('✅ Pembayaran Berhasil')
                        ->body('Pembayaran sebesar Rp ' . number_format($data['amount'], 0, ',', '.') . ' berhasil dicatat.')
                        ->success()
                        ->send();
                })
                ->visible(fn (Order $record) => !$record->is_fully_paid),
                
            Actions\Action::make('print_invoice')
                ->label('Cetak Invoice')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->url(fn (Order $record) => route('orders.invoice', $record))
                ->openUrlInNewTab(),
                
            Actions\DeleteAction::make()
                ->label('Hapus Order'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Infolists\Components\Section::make('Informasi Order')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number')
                            ->label('No. Order')
                            ->icon('heroicon-o-document-text'),
                            
                        Infolists\Components\TextEntry::make('order_date')
                            ->label('Tanggal Order')
                            ->date('d F Y')
                            ->icon('heroicon-o-calendar'),
                            
                        Infolists\Components\TextEntry::make('due_date')
                            ->label('Deadline')
                            ->date('d F Y')
                            ->color(fn ($record) => $record->is_overdue ? 'danger' : null)
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Customer')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Nama Customer')
                            ->icon('heroicon-o-user'),
                            
                        Infolists\Components\TextEntry::make('customer.phone')
                            ->label('Telepon')
                            ->icon('heroicon-o-phone'),
                            
                        Infolists\Components\TextEntry::make('customer.email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->visible(fn ($record) => !empty($record->customer->email)),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Detail Produk')
                    ->schema([
                        Infolists\Components\TextEntry::make('clothing_type_display')
                            ->label('Jenis Pakaian')
                            ->icon('heroicon-o-tag'),
                            
                        Infolists\Components\TextEntry::make('size')
                            ->label('Ukuran')
                            ->badge()
                            ->color(fn ($state) => in_array($state, ['XXL', 'XXXL', '4XL', '5XL']) ? 'danger' : 'gray'),
                            
                        Infolists\Components\TextEntry::make('color')
                            ->label('Warna')
                            ->icon('heroicon-o-swatch'),
                            
                        Infolists\Components\TextEntry::make('quantity')
                            ->label('Jumlah')
                            ->suffix(' pcs')
                            ->icon('heroicon-o-cube'),
                            
                        Infolists\Components\TextEntry::make('material_needed')
                            ->label('Kebutuhan Bahan')
                            ->suffix(' meter')
                            ->icon('heroicon-o-scissors'),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Harga & Pembayaran')
                    ->schema([
                        Infolists\Components\TextEntry::make('base_price')
                            ->label('Harga Dasar')
                            ->money('IDR')
                            ->icon('heroicon-o-currency-dollar'),
                            
                        Infolists\Components\TextEntry::make('size_surcharge')
                            ->label('Tambahan Ukuran')
                            ->money('IDR')
                            ->color('warning')
                            ->visible(fn ($record) => $record->size_surcharge > 0),
                            
                        Infolists\Components\TextEntry::make('additional_charges')
                            ->label('Biaya Tambahan')
                            ->money('IDR')
                            ->color('info')
                            ->visible(fn ($record) => $record->additional_charges > 0),
                            
                        Infolists\Components\TextEntry::make('discount')
                            ->label('Diskon')
                            ->money('IDR')
                            ->color('danger')
                            ->visible(fn ($record) => $record->discount > 0),
                            
                        Infolists\Components\TextEntry::make('total_price')
                            ->label('Total Harga')
                            ->money('IDR')
                            ->color('success')
                            ->weight('bold')
                            ->size('lg'),
                            
                        Infolists\Components\TextEntry::make('payment_status')
                            ->label('Status Pembayaran')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'paid' => 'LUNAS',
                                'dp' => 'DP',
                                'partial' => 'CICILAN',
                                default => 'BELUM BAYAR',
                            })
                            ->color(fn ($state) => match($state) {
                                'paid' => 'success',
                                'dp' => 'warning',
                                'partial' => 'info',
                                default => 'danger',
                            }),
                            
                        Infolists\Components\TextEntry::make('dp_paid')
                            ->label('Sudah Dibayar')
                            ->money('IDR')
                            ->color('success')
                            ->visible(fn ($record) => $record->dp_paid > 0),
                            
                        Infolists\Components\TextEntry::make('remaining_payment')
                            ->label('Sisa Pembayaran')
                            ->money('IDR')
                            ->color('danger')
                            ->visible(fn ($record) => $record->remaining_payment > 0),
                            
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->badge()
                            ->visible(fn ($record) => $record->payment_method),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Status Produksi')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_status')
                            ->label('Status Order')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'pending' => 'PENDING',
                                'measurement' => 'PENGUKURAN',
                                'cutting' => 'PEMOTONGAN',
                                'sewing' => 'PENJAHITAN',
                                'finishing' => 'FINISHING',
                                'ready' => 'SIAP DIAMBIL',
                                'completed' => 'SELESAI',
                                'cancelled' => 'BATAL',
                                default => strtoupper($state),
                            })
                            ->color(fn ($state) => match($state) {
                                'completed' => 'success',
                                'ready' => 'info',
                                'cancelled' => 'danger',
                                default => 'warning'
                            }),
                            
                        Infolists\Components\TextEntry::make('priority')
                            ->label('Prioritas')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'low' => 'RENDAH',
                                'normal' => 'NORMAL',
                                'high' => 'TINGGI',
                                'urgent' => 'MENDESAK',
                                default => strtoupper($state),
                            })
                            ->color(fn ($state) => match($state) {
                                'urgent' => 'danger',
                                'high' => 'warning',
                                'normal' => 'info',
                                'low' => 'gray',
                            }),
                            
                        Infolists\Components\TextEntry::make('days_remaining')
                            ->label('Sisa Waktu')
                            ->formatStateUsing(function ($state, Order $record) {
                                if ($record->is_overdue) {
                                    return abs($state) . ' hari terlambat';
                                }
                                return $state . ' hari tersisa';
                            })
                            ->color(fn (Order $record) => $record->is_overdue ? 'danger' : 'success'),
                            
                        Infolists\Components\TextEntry::make('tailor.name')
                            ->label('Penjahit')
                            ->icon('heroicon-o-user-circle')
                            ->visible(fn ($record) => $record->tailor),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Catatan')
                    ->schema([
                        Infolists\Components\TextEntry::make('measurement_notes')
                            ->label('Catatan Ukuran')
                            ->markdown()
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->measurement_notes)),
                            
                        Infolists\Components\TextEntry::make('customer_notes')
                            ->label('Catatan Customer')
                            ->markdown()
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->customer_notes)),
                            
                        Infolists\Components\TextEntry::make('production_notes')
                            ->label('Catatan Produksi')
                            ->markdown()
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->production_notes)),
                            
                        Infolists\Components\TextEntry::make('internal_notes')
                            ->label('Catatan Internal')
                            ->markdown()
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->internal_notes)),
                    ])
                    ->collapsible(),
            ]);
    }
}