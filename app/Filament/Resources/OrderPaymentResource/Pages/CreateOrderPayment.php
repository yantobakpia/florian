<?php

namespace App\Filament\Resources\OrderPaymentResource\Pages;

use App\Filament\Resources\OrderPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOrderPayment extends CreateRecord
{
    protected static string $resource = OrderPaymentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Update order payment status
        $order = $this->record->order;
        $order->updatePaymentStatus();
    }
}