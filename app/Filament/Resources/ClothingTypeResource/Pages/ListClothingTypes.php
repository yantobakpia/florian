<?php
namespace App\Filament\Resources\ClothingTypeResource\Pages;

use App\Filament\Resources\ClothingTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClothingTypes extends ListRecords
{
    protected static string $resource = ClothingTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Jenis Pakaian'),
        ];
    }
}