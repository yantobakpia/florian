<?php
namespace App\Filament\Resources\ClothingTypeResource\Pages;

use App\Filament\Resources\ClothingTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClothingType extends CreateRecord
{
    protected static string $resource = ClothingTypeResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}