<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-m-pencil-square')
                ->color('warning'),

            Actions\DeleteAction::make()
                ->icon('heroicon-m-trash')
                ->color('danger'),

            Actions\RestoreAction::make()
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('success'),

            Actions\ForceDeleteAction::make()
                ->icon('heroicon-m-trash')
                ->color('danger'),
        ];
    }

    // HAPUS METHOD getFooterWidgets() ATAU KOSONGKAN
    protected function getFooterWidgets(): array
    {
        return [];
    }
}