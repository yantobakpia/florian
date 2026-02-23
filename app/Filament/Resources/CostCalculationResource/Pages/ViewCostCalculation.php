<?php

namespace App\Filament\Resources\CostCalculationResource\Pages;

use App\Filament\Resources\CostCalculationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCostCalculation extends ViewRecord
{
    protected static string $resource = CostCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
