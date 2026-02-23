<?php

namespace App\Filament\Resources\BalanceTransactionResource\Pages;

use App\Filament\Resources\BalanceTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBalanceTransaction extends EditRecord
{
    protected static string $resource = BalanceTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
