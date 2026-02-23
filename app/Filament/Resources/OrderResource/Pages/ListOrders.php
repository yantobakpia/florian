<?php
// app/Filament/Resources/OrderResource/Pages/ListOrders.php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Order Baru')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}