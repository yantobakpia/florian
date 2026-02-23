<?php

namespace App\Filament\Resources\CostCalculationResource\Pages;

use App\Filament\Resources\CostCalculationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCostCalculation extends EditRecord
{
    protected static string $resource = CostCalculationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
