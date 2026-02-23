<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\CreateAction;

class ClothingItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'clothingItems';
    protected static ?string $recordTitleAttribute = 'id';

    // RelationManager::table is non-static in this Filament version — match signature
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clothing_type.name')->label('Jenis Pakaian'),
                TextColumn::make('quantity')->label('Jumlah'),
                TextColumn::make('size')->label('Ukuran'),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
