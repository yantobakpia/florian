<?php

namespace App\Filament\Resources\BalanceTransactionResource\Pages;

use App\Filament\Resources\BalanceTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBalanceTransaction extends CreateRecord
{
    protected static string $resource = BalanceTransactionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pastikan created_by diisi
        if (!isset($data['created_by'])) {
            $data['created_by'] = auth()->id();
        }
        
        return $data;
    }
}