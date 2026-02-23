<?php
// app/Filament/Resources/OrderResource/RelationManagers/BatchClothingItemsRelationManager.php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BatchClothingItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'batchClothingItems';

    protected static ?string $title = 'Jenis Pakaian Batch';

    protected static ?string $icon = 'heroicon-o-rectangle-stack';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('clothing_type_id')
                            ->label('Jenis Pakaian')
                            ->relationship('clothingType', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $clothingType = \App\Models\ClothingType::find($state);
                                    if ($clothingType) {
                                        $set('base_price', $clothingType->base_price ?? 0);
                                        if ($clothingType->is_custom) {
                                            $set('custom_name', $clothingType->name);
                                        }
                                    }
                                }
                            }),
                        
                        Forms\Components\TextInput::make('custom_name')
                            ->label('Nama Custom')
                            ->maxLength(100)
                            ->placeholder('Contoh: Kemeja Pendek, Kemeja Panjang, dll'),
                    ]),
                
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('base_price')
                            ->label('Harga Dasar (M/L)')
                            ->prefix('Rp')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(1000),
                        
                        Forms\Components\TextInput::make('color')
                            ->label('Warna Khusus')
                            ->maxLength(50)
                            ->placeholder('Kosongkan jika pakai warna umum batch'),
                    ]),
                
                Forms\Components\Section::make('Distribusi Ukuran')
                    ->schema([
                        Forms\Components\Grid::make(5)
                            ->schema([
                                Forms\Components\TextInput::make('size_distribution.XS')
                                    ->label('XS')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.S')
                                    ->label('S')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.M')
                                    ->label('M')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.L')
                                    ->label('L')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.XL')
                                    ->label('XL')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                        
                        Forms\Components\Grid::make(6)
                            ->schema([
                                Forms\Components\TextInput::make('size_distribution.XXL')
                                    ->label('XXL (+5,000)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.XXXL')
                                    ->label('XXXL/3XL (+10,000)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.4XL')
                                    ->label('4XL (+15,000)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.5XL')
                                    ->label('5XL (+20,000)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.6XL')
                                    ->label('6XL (+25,000)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                
                                Forms\Components\TextInput::make('size_distribution.7XL')
                                    ->label('7XL (+30,000)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                    ]),
                
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->maxLength(500)
                    ->placeholder('Catatan khusus untuk jenis pakaian ini...'),
                
                Forms\Components\TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->columns([
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Jenis Pakaian')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Qty Total')
                    ->numeric()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('formatted_size_distribution')
                    ->label('Distribusi Ukuran')
                    ->wrap()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Harga Dasar')
                    ->money('IDR'),
                
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->color('primary')
                    ->weight('bold'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Jenis Pakaian')
                    ->icon('heroicon-o-plus'),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }
}