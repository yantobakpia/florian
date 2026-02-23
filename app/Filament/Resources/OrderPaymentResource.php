<?php
// app/Filament/Resources/OrderPaymentResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderPaymentResource\Pages;
use App\Models\OrderPayment;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class OrderPaymentResource extends Resource
{
    protected static ?string $model = OrderPayment::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'reference_number';
    protected static ?string $navigationLabel = 'Pembayaran Order';
    protected static ?string $modelLabel = 'Pembayaran Order';
    protected static ?string $pluralModelLabel = 'Pembayaran Order';
    protected static ?string $slug = 'order-payments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Order')
                    ->schema([
                        Forms\Components\Select::make('order_id')
                            ->label('Order')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // FIXED: Parameter ketiga dihapus
                                if ($state) {
                                    $order = Order::find($state);
                                    if ($order) {
                                        $set('order_info', "Customer: {$order->customer->name} | Total: Rp " . number_format($order->total_price, 0, ',', '.'));
                                    }
                                }
                            }),

                        Forms\Components\Placeholder::make('order_info')
                            ->label('Info Order')
                            ->content(function (callable $get) {
                                $orderId = $get('order_id');
                                if ($orderId) {
                                    $order = Order::with('customer')->find($orderId);
                                    if ($order) {
                                        $customer = $order->customer;
                                        $remaining = $order->remaining_payment;
                                        
                                        return new HtmlString("
                                            <div class='space-y-1'>
                                                <div><strong>Customer:</strong> {$customer->name} ({$customer->phone})</div>
                                                <div><strong>Total Order:</strong> Rp " . number_format($order->total_price, 0, ',', '.') . "</div>
                                                <div><strong>Sudah Dibayar:</strong> Rp " . number_format($order->net_paid, 0, ',', '.') . "</div>
                                                <div><strong>Sisa Pembayaran:</strong> Rp " . number_format($remaining, 0, ',', '.') . "</div>
                                            </div>
                                        ");
                                    }
                                }
                                return new HtmlString('<span class="text-gray-500">Pilih order terlebih dahulu</span>');
                            })
                            ->columnSpanFull()
                            ->hidden(fn (callable $get) => !$get('order_id')),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Detail Pembayaran')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipe Pembayaran')
                            ->options([
                                'dp' => 'DP',
                                'partial' => 'Cicilan',
                                'full' => 'Pelunasan',
                                'refund' => 'Refund',
                            ])
                            ->required()
                            ->default('partial')
                            ->reactive(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->step(1000)
                            ->reactive()
                            ->helperText(function (callable $get) {
                                $orderId = $get('order_id');
                                $type = $get('type');
                                
                                if (!$orderId) {
                                    return 'Pilih order terlebih dahulu';
                                }
                                
                                $order = Order::find($orderId);
                                if (!$order) {
                                    return 'Order tidak ditemukan';
                                }
                                
                                $remaining = $order->remaining_payment;
                                $maxAmount = $type === 'refund' ? $order->net_paid : $remaining;
                                
                                return "Maksimal: Rp " . number_format($maxAmount, 0, ',', '.');
                            }),

                        Forms\Components\Select::make('method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Cash',
                                'transfer' => 'Transfer',
                                'qris' => 'QRIS',
                                'debit' => 'Kartu Debit',
                                'credit' => 'Kartu Kredit',
                                'other' => 'Lainnya',
                            ])
                            ->required()
                            ->default('cash'),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('No. Referensi')
                            ->maxLength(50)
                            ->placeholder('Contoh: TRF-001, INV-001'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->placeholder('Catatan pembayaran...'),

                        Forms\Components\DateTimePicker::make('payment_date')
                            ->label('Tanggal Pembayaran')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
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

                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->color(fn ($record) => $record->type === 'refund' ? 'danger' : 'success')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('method')
                    ->label('Metode')
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
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe Pembayaran')
                    ->options([
                        'dp' => 'DP',
                        'partial' => 'Cicilan',
                        'full' => 'Pelunasan',
                        'refund' => 'Refund',
                    ]),
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
                    ->color('danger'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Hapus')
                    ->requiresConfirmation(),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Pembayaran'),
            ])
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderPayments::route('/'),
            'create' => Pages\CreateOrderPayment::route('/create'),
            'view' => Pages\ViewOrderPayment::route('/{record}'),
            'edit' => Pages\EditOrderPayment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getNavigationBadge(): ?string
    {
        $todayTotal = OrderPayment::whereDate('payment_date', today())
            ->where('type', '!=', 'refund')
            ->sum('amount');
        
        return 'Rp ' . number_format($todayTotal, 0, ',', '.');
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}