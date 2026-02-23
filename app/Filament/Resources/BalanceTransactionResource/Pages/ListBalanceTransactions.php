<?php

namespace App\Filament\Resources\BalanceTransactionResource\Pages;

use App\Filament\Resources\BalanceTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBalanceTransactions extends ListRecords
{
    protected static string $resource = BalanceTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
