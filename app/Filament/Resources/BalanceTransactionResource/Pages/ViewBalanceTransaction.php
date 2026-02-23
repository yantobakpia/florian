<?php

namespace App\Filament\Resources\BalanceTransactionResource\Pages;

use App\Filament\Resources\BalanceTransactionResource;
use App\Models\BalanceTransaction;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;

class ViewBalanceTransaction extends ViewRecord
{
    protected static string $resource = BalanceTransactionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Components\Section::make('Detail Transaksi')
                    ->schema([
                        Components\TextEntry::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->dateTime('d/m/Y H:i'),
                        
                        Components\TextEntry::make('type')
                            ->label('Jenis')
                            ->badge()
                            ->formatStateUsing(function ($state) {
                                return $state === 'in' ? 'MASUK' : 'KELUAR';
                            })
                            ->color(function ($state) {
                                return $state === 'in' ? 'success' : 'danger';
                            }),
                        
                        Components\TextEntry::make('amount')
                            ->label('Jumlah')
                            ->money('IDR')
                            ->color(function ($record) {
                                return $record->type === 'in' ? 'success' : 'danger';
                            }),
                        
                        Components\TextEntry::make('description')
                            ->label('Keterangan')
                            ->columnSpanFull(),
                        
                        Components\TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->badge()
                            ->formatStateUsing(function ($state) {
                                $methods = [
                                    'cash' => 'TUNAI',
                                    'transfer' => 'TRANSFER',
                                    'qris' => 'QRIS',
                                    'debit' => 'DEBIT',
                                    'credit' => 'KREDIT',
                                ];
                                return $methods[$state] ?? strtoupper($state);
                            })
                            ->color(function ($state) {
                                $colors = [
                                    'cash' => 'success',
                                    'transfer' => 'info',
                                    'qris' => 'primary',
                                ];
                                return $colors[$state] ?? 'gray';
                            }),
                        
                        Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan'),
                    ])
                    ->columns(2),
                
                Components\Section::make('Info Saldo')
                    ->schema([
                        Components\TextEntry::make('balance_before')
                            ->label('Saldo Sebelum')
                            ->money('IDR'),
                        
                        Components\TextEntry::make('balance_after')
                            ->label('Saldo Setelah')
                            ->money('IDR')
                            ->color(function ($record) {
                                return $record->balance_after >= 0 ? 'success' : 'danger';
                            }),
                        
                        Components\TextEntry::make('balance_change')
                            ->label('Perubahan Saldo')
                            ->getStateUsing(function ($record) {
                                $sign = $record->type === 'in' ? '+' : '-';
                                return $sign . ' Rp ' . number_format($record->amount, 0, ',', '.');
                            })
                            ->color(function ($record) {
                                return $record->type === 'in' ? 'success' : 'danger';
                            }),
                    ])
                    ->columns(3),
                
                Components\Section::make('Referensi')
                    ->schema([
                        Components\TextEntry::make('reference_type_label')
                            ->label('Tipe Referensi')
                            ->badge()
                            ->color('primary'),
                        
                        Components\TextEntry::make('reference_info')
                            ->label('Info Referensi')
                            ->placeholder('Tidak ada referensi'),
                    ])
                    ->collapsed(),
                
                Components\Section::make('Informasi Sistem')
                    ->schema([
                        Components\TextEntry::make('creator.name')
                            ->label('Dibuat Oleh')
                            ->placeholder('System'),
                        
                        Components\TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d/m/Y H:i'),
                        
                        Components\TextEntry::make('updated_at')
                            ->label('Diperbarui Pada')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit'),
            Actions\DeleteAction::make()
                ->label('Hapus'),
        ];
    }
}