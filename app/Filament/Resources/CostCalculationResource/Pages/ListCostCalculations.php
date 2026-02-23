<?php

namespace App\Filament\Resources\CostCalculationResource\Pages;

use App\Filament\Resources\CostCalculationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCostCalculations extends ListRecords
{
    protected static string $resource = CostCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
