<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class UserActivity extends Widget
{
    protected static string $view = 'filament.widgets.user-activity';

    public $record;

    protected int | string | array $columnSpan = 'full';

    public function mount($record): void
    {
        $this->record = $record;
    }

    public function getActivityData(): array
    {
        // Cek apakah table activity_log ada
        if (!\Schema::hasTable('activity_log')) {
            return [
                'activities' => collect(),
                'last_login' => $this->record->last_login_at ?? 'Never',
                'login_count' => $this->record->login_count ?? 0,
            ];
        }

        $activities = DB::table('activity_log')
            ->where('causer_id', $this->record->id)
            ->where('causer_type', 'App\Models\User')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'activities' => $activities,
            'last_login' => $this->record->last_login_at ?? 'Never',
            'login_count' => $this->record->login_count ?? 0,
        ];
    }
}