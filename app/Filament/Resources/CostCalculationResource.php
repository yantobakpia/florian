<?php
// app/Filament/Resources/CostCalculationResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\CostCalculationResource\Pages;
use App\Models\CostCalculation;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CostCalculationResource extends Resource
{
    protected static ?string $model = CostCalculation::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Perhitungan Biaya';
    protected static ?string $modelLabel = 'Perhitungan Biaya';
    protected static ?string $pluralModelLabel = 'Perhitungan Biaya';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Order')
                    ->schema([
                        Forms\Components\Select::make('order_id')
                            ->label('Order')
                            ->relationship('order', 'order_number')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // ✅ FIXED: Tambah parameter $state
                                if ($state) {
                                    $order = Order::find($state);
                                    if ($order) {
                                        $set('order_price', $order->total_price);
                                    }
                                }
                            }),
                        
                        Forms\Components\Placeholder::make('order_info')
                            ->label('Detail Order')
                            ->content(function (Forms\Get $get) {
                                $orderId = $get('order_id');
                                if (!$orderId) return new HtmlString('<span class="text-gray-500">Pilih order</span>');
                                
                                $order = Order::find($orderId);
                                if (!$order) return new HtmlString('<span class="text-red-500">Order tidak ditemukan</span>');
                                
                                $customerName = $order->customer->name ?? 'N/A';
                                $totalPrice = number_format($order->total_price, 0, ',', '.');
                                
                                return new HtmlString("
                                    <div class='p-3 bg-gray-50 rounded-lg'>
                                        <div class='font-bold'>Customer: {$customerName}</div>
                                        <div>Harga Jual: <span class='text-green-600 font-bold'>Rp {$totalPrice}</span></div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                
                Forms\Components\Tabs::make('Perhitungan')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Biaya Bahan')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('fabric_cost')
                                            ->label('Biaya Kain')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('thread_cost')
                                            ->label('Biaya Benang')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('button_cost')
                                            ->label('Biaya Kancing')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                    ]),
                                
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('zipper_cost')
                                            ->label('Biaya Resleting')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('lining_cost')
                                            ->label('Biaya Furing')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('other_material_cost')
                                            ->label('Bahan Lain')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                    ]),
                                
                                Forms\Components\Placeholder::make('total_material')
                                    ->label('Total Biaya Bahan')
                                    ->content(function (Forms\Get $get) {
                                        $total = 
                                            (float)($get('fabric_cost') ?? 0) +
                                            (float)($get('thread_cost') ?? 0) +
                                            (float)($get('button_cost') ?? 0) +
                                            (float)($get('zipper_cost') ?? 0) +
                                            (float)($get('lining_cost') ?? 0) +
                                            (float)($get('other_material_cost') ?? 0);
                                        
                                        return new HtmlString("
                                            <div class='p-3 bg-red-50 rounded-lg'>
                                                <div class='font-bold text-red-700'>UANG KELUAR: Rp " . number_format($total, 0, ',', '.') . "</div>
                                                <div class='text-sm text-red-600'>Untuk pembelian bahan</div>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Biaya Jasa')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('sewing_cost')
                                            ->label('Jasa Jahit')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('embroidery_cost')
                                            ->label('Jasa Bordir')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                    ]),
                                
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('printing_cost')
                                            ->label('Jasa Sablon')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('ironing_cost')
                                            ->label('Jasa Setrika')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                        
                                        Forms\Components\TextInput::make('other_service_cost')
                                            ->label('Jasa Lain')
                                            ->prefix('Rp')
                                            ->numeric()
                                            ->default(0)
                                            ->live(),
                                    ]),
                                
                                Forms\Components\Placeholder::make('total_service')
                                    ->label('Total Biaya Jasa')
                                    ->content(function (Forms\Get $get) {
                                        $total = 
                                            (float)($get('sewing_cost') ?? 0) +
                                            (float)($get('embroidery_cost') ?? 0) +
                                            (float)($get('printing_cost') ?? 0) +
                                            (float)($get('ironing_cost') ?? 0) +
                                            (float)($get('other_service_cost') ?? 0);
                                        
                                        return new HtmlString("
                                            <div class='p-3 bg-orange-50 rounded-lg'>
                                                <div class='font-bold text-orange-700'>UANG KELUAR: Rp " . number_format($total, 0, ',', '.') . "</div>
                                                <div class='text-sm text-orange-600'>Untuk pembayaran jasa</div>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                            ]),
                        
                        Forms\Components\Tabs\Tab::make('Ringkasan Keuangan')
                            ->schema([
                                Forms\Components\TextInput::make('order_price')
                                    ->label('UANG MASUK (Penjualan)')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->live(),
                                
                                Forms\Components\Placeholder::make('cash_flow_summary')
                                    ->label('Aliran Kas')
                                    ->content(function (Forms\Get $get) {
                                        $cashIn = (float)($get('order_price') ?? 0);
                                        
                                        $materialCost = 
                                            (float)($get('fabric_cost') ?? 0) +
                                            (float)($get('thread_cost') ?? 0) +
                                            (float)($get('button_cost') ?? 0) +
                                            (float)($get('zipper_cost') ?? 0) +
                                            (float)($get('lining_cost') ?? 0) +
                                            (float)($get('other_material_cost') ?? 0);
                                        
                                        $serviceCost = 
                                            (float)($get('sewing_cost') ?? 0) +
                                            (float)($get('embroidery_cost') ?? 0) +
                                            (float)($get('printing_cost') ?? 0) +
                                            (float)($get('ironing_cost') ?? 0) +
                                            (float)($get('other_service_cost') ?? 0);
                                        
                                        $cashOut = $materialCost + $serviceCost;
                                        $netFlow = $cashIn - $cashOut;
                                        
                                        $colorIn = 'text-green-600';
                                        $colorOut = 'text-red-600';
                                        $colorNet = $netFlow >= 0 ? 'text-green-600' : 'text-red-600';
                                        $status = $netFlow >= 0 ? 'MENGUNTUNGKAN' : 'MERUGI';
                                        
                                        return new HtmlString("
                                            <div class='space-y-3 p-4 bg-gray-50 rounded-lg'>
                                                <div class='grid grid-cols-2 gap-4'>
                                                    <div class='bg-green-50 p-3 rounded'>
                                                        <div class='text-sm font-bold text-green-700'>UANG MASUK</div>
                                                        <div class='text-xl font-bold {$colorIn}'>Rp " . number_format($cashIn, 0, ',', '.') . "</div>
                                                    </div>
                                                    
                                                    <div class='bg-red-50 p-3 rounded'>
                                                        <div class='text-sm font-bold text-red-700'>UANG KELUAR</div>
                                                        <div class='text-xl font-bold {$colorOut}'>Rp " . number_format($cashOut, 0, ',', '.') . "</div>
                                                    </div>
                                                </div>
                                                
                                                <div class='border-t pt-3'>
                                                    <div class='flex justify-between items-center'>
                                                        <div class='font-bold'>ALIRAN KAS BERSIH:</div>
                                                        <div class='text-2xl font-bold {$colorNet}'>Rp " . number_format($netFlow, 0, ',', '.') . "</div>
                                                    </div>
                                                    
                                                    <div class='flex justify-between items-center mt-2'>
                                                        <div class='font-bold'>STATUS:</div>
                                                        <div class='px-3 py-1 rounded font-bold " . ($netFlow >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . "'>
                                                            {$status}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                                
                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
                
                // Hidden fields
                Forms\Components\Hidden::make('total_material_cost')
                    ->default(0),
                
                Forms\Components\Hidden::make('total_service_cost')
                    ->default(0),
                
                Forms\Components\Hidden::make('total_cost')
                    ->default(0),
                
                Forms\Components\Hidden::make('profit')
                    ->default(0),
                
                Forms\Components\Hidden::make('profit_percentage')
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('cash_in')
                    ->label('Uang Masuk')
                    ->money('IDR')
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('cash_out')
                    ->label('Uang Keluar')
                    ->money('IDR')
                    ->color('danger'),
                
                Tables\Columns\TextColumn::make('net_cash_flow')
                    ->label('Aliran Kas')
                    ->money('IDR')
                    ->color(fn ($record) => $record->net_cash_flow >= 0 ? 'success' : 'danger'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCostCalculations::route('/'),
            'create' => Pages\CreateCostCalculation::route('/create'),
            'edit' => Pages\EditCostCalculation::route('/{record}/edit'),
        ];
    }
}