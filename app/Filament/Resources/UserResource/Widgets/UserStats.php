<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStats extends BaseWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $adminUsers = User::where('role', 'admin')->count();
        $deletedUsers = User::onlyTrashed()->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Active Users', $activeUsers)
                ->description($totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) . '% of total' : '0%')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Administrators', $adminUsers)
                ->description('Admin role users')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('danger'),

            Stat::make('Deleted Users', $deletedUsers)
                ->description('Soft deleted users')
                ->descriptionIcon('heroicon-m-trash')
                ->color('warning'),
        ];
    }
}