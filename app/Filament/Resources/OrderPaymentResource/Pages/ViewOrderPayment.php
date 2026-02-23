<?php

namespace App\Filament\Resources\OrderPaymentResource\Pages;

use App\Filament\Resources\OrderPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Support\HtmlString;

class ViewOrderPayment extends ViewRecord
{
    protected static string $resource = OrderPaymentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Components\Section::make('Informasi Order')
                    ->schema([
                        Components\TextEntry::make('order.order_number')
                            ->label('No. Order')
                            ->url(fn ($record) => route('filament.admin.resources.orders.view', $record->order_id))
                            ->openUrlInNewTab(),
                        
                        Components\TextEntry::make('order.customer.name')
                            ->label('Customer'),
                        
                        Components\TextEntry::make('order.customer.phone')
                            ->label('Telepon'),
                        
                        Components\TextEntry::make('order_total')
                            ->label('Total Order')
                            ->getStateUsing(fn ($record) => 'Rp ' . number_format($record->order->total_price, 0, ',', '.')),
                        
                        Components\TextEntry::make('order_paid')
                            ->label('Sudah Dibayar')
                            ->getStateUsing(fn ($record) => 'Rp ' . number_format($record->order->net_paid, 0, ',', '.')),
                        
                        Components\TextEntry::make('order_remaining')
                            ->label('Sisa Pembayaran')
                            ->getStateUsing(fn ($record) => 'Rp ' . number_format($record->order->remaining_payment, 0, ',', '.'))
                            ->color('danger'),
                    ])
                    ->columns(2)
                    ->collapsed(),
                
                Components\Section::make('Detail Pembayaran')
                    ->schema([
                        Components\TextEntry::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->dateTime('d/m/Y H:i'),
                        
                        Components\TextEntry::make('type')
                            ->label('Tipe Pembayaran')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'dp' => 'DP',
                                'partial' => 'CICILAN',
                                'full' => 'PELUNASAN',
                                'refund' => 'REFUND',
                                default => strtoupper($state),
                            })
                            ->color(fn ($state) => match($state) {
                                'dp' => 'warning',
                                'partial' => 'info',
                                'full' => 'success',
                                'refund' => 'danger',
                                default => 'gray',
                            }),
                        
                        Components\TextEntry::make('amount')
                            ->label('Jumlah')
                            ->money('IDR')
                            ->color(fn ($record) => $record->type === 'refund' ? 'danger' : 'success'),
                        
                        Components\TextEntry::make('method')
                            ->label('Metode Pembayaran')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'cash' => 'TUNAI',
                                'transfer' => 'TRANSFER',
                                'qris' => 'QRIS',
                                default => strtoupper($state),
                            })
                            ->color(fn ($state) => match($state) {
                                'cash' => 'success',
                                'transfer' => 'info',
                                'qris' => 'primary',
                                default => 'gray',
                            }),
                        
                        Components\TextEntry::make('reference_number')
                            ->label('No. Referensi')
                            ->placeholder('Tidak ada'),
                        
                        Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan')
                            ->html(),
                    ])
                    ->columns(2),
                
                Components\Section::make('Pengaruh terhadap Order')
                    ->schema([
                        Components\TextEntry::make('before_payment')
                            ->label('Sebelum Pembayaran')
                            ->getStateUsing(function ($record) {
                                $order = $record->order;
                                $beforePaid = $order->net_paid - $record->amount;
                                $beforeRemaining = $order->total_price - $beforePaid;
                                
                                return new HtmlString(
                                    '<div class="space-y-1">' .
                                    '<div>Sudah dibayar: <span class="font-semibold">Rp ' . number_format($beforePaid, 0, ',', '.') . '</span></div>' .
                                    '<div>Sisa: <span class="font-semibold">Rp ' . number_format($beforeRemaining, 0, ',', '.') . '</span></div>' .
                                    '</div>'
                                );
                            })
                            ->html(),
                        
                        Components\TextEntry::make('after_payment')
                            ->label('Setelah Pembayaran')
                            ->getStateUsing(function ($record) {
                                $order = $record->order;
                                $order->refresh(); // Refresh untuk mendapatkan data terbaru
                                
                                $statusColor = match($order->payment_status) {
                                    'paid' => 'text-green-600',
                                    'dp' => 'text-yellow-600',
                                    'partial' => 'text-blue-600',
                                    default => 'text-red-600',
                                };
                                
                                $statusLabel = match($order->payment_status) {
                                    'paid' => 'LUNAS',
                                    'dp' => 'DP',
                                    'partial' => 'CICILAN',
                                    default => 'BELUM BAYAR',
                                };
                                
                                return new HtmlString(
                                    '<div class="space-y-1">' .
                                    '<div>Sudah dibayar: <span class="font-semibold text-green-600">Rp ' . number_format($order->net_paid, 0, ',', '.') . '</span></div>' .
                                    '<div>Sisa: <span class="font-semibold ' . ($order->remaining_payment > 0 ? 'text-red-600' : 'text-green-600') . '">' .
                                    'Rp ' . number_format($order->remaining_payment, 0, ',', '.') . '</span></div>' .
                                    '<div>Status: <span class="font-bold ' . $statusColor . '">' .
                                    $statusLabel . '</span></div>' .
                                    '</div>'
                                );
                            })
                            ->html(),
                    ])
                    ->columns(2),
                
                Components\Section::make('Informasi Sistem')
                    ->schema([
                        Components\TextEntry::make('creator.name')
                            ->label('Dicatat Oleh'),
                        
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