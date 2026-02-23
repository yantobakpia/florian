<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\TableWidget;
use Filament\Tables;
use Filament\Tables\Table;

class LatestOrders extends TableWidget
{
    protected static ?string $heading = 'Order Terbaru';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with('customer')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('No Order')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan'),

                Tables\Columns\TextColumn::make('order_date')
                    ->label('Tanggal')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('dp_paid')
                    ->label('DP Dibayar')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),

                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Status Pembayaran')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'unpaid' => 'Belum Bayar',
                        'partial' => 'Sebagian',
                        'paid' => 'Lunas',
                        default => $state,
                    })
                    ->colors([
                        'danger' => 'unpaid',
                        'warning' => 'partial',
                        'success' => 'paid',
                    ]),
            ]);
    }
}
