<?php
// app/Filament/Resources/CostCalculationResource/RelationManagers/CostCalculationsRelationManager.php

namespace App\Filament\Resources\CostCalculationResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Support\HtmlString;

class CostCalculationsRelationManager extends RelationManager
{
    protected static string $relationship = 'costCalculations';
    
    protected static ?string $title = 'Perhitungan Biaya';
    
    protected static ?string $label = 'Perhitungan Biaya';
    
    protected static ?string $pluralLabel = 'Perhitungan Biaya';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Biaya Bahan')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('fabric_cost')
                                    ->label('Kain')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(),
                                
                                Forms\Components\TextInput::make('thread_cost')
                                    ->label('Benang')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(),
                                
                                Forms\Components\TextInput::make('button_cost')
                                    ->label('Kancing')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(),
                            ]),
                        
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('zipper_cost')
                                    ->label('Resleting')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(),
                                
                                Forms\Components\TextInput::make('lining_cost')
                                    ->label('Furing')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(),
                                
                                Forms\Components\TextInput::make('other_material_cost')
                                    ->label('Lainnya')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(),
                            ]),
                    ])
                    ->columns(1),
                
                Forms\Components\Section::make('Biaya Jasa')
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
                    ])
                    ->columns(1),
                
                Forms\Components\Section::make('Ringkasan')
                    ->schema([
                        Forms\Components\TextInput::make('order_price')
                            ->label('Harga Jual')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(fn ($record) => $record->order->total_price ?? 0)
                            ->live(),
                        
                        Forms\Components\Placeholder::make('summary')
                            ->label('Perhitungan')
                            ->content(function (Forms\Get $get) {
                                $material = 
                                    ($get('fabric_cost') ?? 0) +
                                    ($get('thread_cost') ?? 0) +
                                    ($get('button_cost') ?? 0) +
                                    ($get('zipper_cost') ?? 0) +
                                    ($get('lining_cost') ?? 0) +
                                    ($get('other_material_cost') ?? 0);
                                
                                $service = 
                                    ($get('sewing_cost') ?? 0) +
                                    ($get('embroidery_cost') ?? 0) +
                                    ($get('printing_cost') ?? 0) +
                                    ($get('ironing_cost') ?? 0) +
                                    ($get('other_service_cost') ?? 0);
                                
                                $total = $material + $service;
                                $selling = $get('order_price') ?? 0;
                                $profit = $selling - $total;
                                $percentage = $total > 0 ? ($profit / $total) * 100 : 0;
                                
                                return new HtmlString("
                                    <div class='space-y-2'>
                                        <div class='flex justify-between'>
                                            <span>Total Biaya:</span>
                                            <span class='font-bold text-red-600'>Rp " . number_format($total, 0, ',', '.') . "</span>
                                        </div>
                                        <div class='flex justify-between'>
                                            <span>Harga Jual:</span>
                                            <span class='font-bold text-green-600'>Rp " . number_format($selling, 0, ',', '.') . "</span>
                                        </div>
                                        <div class='flex justify-between border-t pt-2'>
                                            <span class='font-bold'>Keuntungan:</span>
                                            <span class='font-bold " . ($profit >= 0 ? 'text-green-600' : 'text-red-600') . "'>
                                                Rp " . number_format($profit, 0, ',', '.') . "
                                                (" . number_format($percentage, 2) . "%)
                                            </span>
                                        </div>
                                    </div>
                                ");
                            }),
                    ])
                    ->columns(1),
                
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('cash_in')
                    ->label('Masuk')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color('success')
                    ->alignRight(),
                
                Tables\Columns\TextColumn::make('cash_out')
                    ->label('Keluar')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color('danger')
                    ->alignRight(),
                
                Tables\Columns\TextColumn::make('net_cash_flow')
                    ->label('Bersih')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->alignRight(),
                
                Tables\Columns\TextColumn::make('profit_percentage_calculated')
                    ->label('Profit %')
                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->alignCenter(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Buat Perhitungan')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-set order price from parent order
                        $data['order_price'] = $this->getOwnerRecord()->total_price;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-o-pencil')
                    ->color('gray'),
                
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('danger'),
            ]);
    }
}