<?php
namespace App\Filament\Resources\ClothingTypeResource\Pages;

use App\Filament\Resources\ClothingTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClothingType extends EditRecord
{
    protected static string $resource = ClothingTypeResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}