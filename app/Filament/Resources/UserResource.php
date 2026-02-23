<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof Pages\CreateUser)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->minLength(8)
                            ->confirmed()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof Pages\CreateUser)
                            ->dehydrated(false)
                            ->columnSpan(2),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Contact Details')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20)
                            ->columnSpan(2),

                        Forms\Components\Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Role & Status')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->options([
                                'admin' => 'Administrator',
                                'manager' => 'Manager',
                                'tailor' => 'Tailor',
                                'cashier' => 'Cashier',
                            ])
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('join_date')
                            ->displayFormat('d/m/Y')
                            ->columnSpan(1),

                        Forms\Components\FileUpload::make('avatar')
                            ->image()
                            ->avatar()
                            ->directory('avatars')
                            ->maxSize(2048)
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('300')
                            ->imageResizeTargetHeight('300')
                            ->columnSpan(1),
                    ])
                    ->columns(4),

                // TAMBAHKAN SECTION LOGIN HISTORY (Readonly)
                Forms\Components\Section::make('Login History')
                    ->schema([
                        Forms\Components\TextInput::make('last_login_at')
                            ->label('Last Login')
                            ->formatStateUsing(fn ($record) => $record?->formatted_last_login ?? 'Never logged in')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('current_login_at')
                            ->label('Current Login')
                            ->formatStateUsing(fn ($record) => $record?->formatted_current_login ?? 'Not logged in')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('login_count')
                            ->label('Total Logins')
                            ->numeric()
                            ->disabled()
                            ->columnSpan(1),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->visible(fn ($record) => $record !== null),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(function ($record) {
                        return 'https://ui-avatars.com/api/?name=' . 
                               urlencode($record->name) . 
                               '&color=7F9CF5&background=EBF4FF';
                    })
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied!')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'manager' => 'warning',
                        'tailor' => 'info',
                        'cashier' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger'),

                // TAMBAHKAN KOLOM LOGIN HISTORY
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->last_login_ip)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('current_login_at')
                    ->label('Current Login')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->current_login_ip)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($record) => $record->isCurrentlyLoggedIn() ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('login_count')
                    ->label('Logins')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('join_date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not deleted'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                        'tailor' => 'Tailor',
                        'cashier' => 'Cashier',
                    ])
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active users')
                    ->falseLabel('Inactive users')
                    ->nullable(),

                // TAMBAHKAN FILTER LOGIN HISTORY
                Tables\Filters\Filter::make('currently_logged_in')
                    ->label('Currently Logged In')
                    ->query(fn (Builder $query): Builder => $query->currentlyLoggedIn()),

                Tables\Filters\Filter::make('never_logged_in')
                    ->label('Never Logged In')
                    ->query(fn (Builder $query): Builder => $query->whereNull('last_login_at')),

                Tables\Filters\Filter::make('has_login_ip')
                    ->label('Has Login IP')
                    ->form([
                        Forms\Components\TextInput::make('ip')
                            ->label('IP Address')
                            ->placeholder('e.g., 192.168.1.1'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['ip'],
                            fn (Builder $query, $ip): Builder => 
                                $query->where('current_login_ip', 'like', "%{$ip}%")
                                      ->orWhere('last_login_ip', 'like', "%{$ip}%")
                        );
                    }),

                Tables\Filters\Filter::make('has_avatar')
                    ->label('Has Avatar')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('avatar')),

                Tables\Filters\Filter::make('join_date')
                    ->form([
                        Forms\Components\DatePicker::make('join_from'),
                        Forms\Components\DatePicker::make('join_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['join_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('join_date', '>=', $date),
                            )
                            ->when(
                                $data['join_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('join_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['join_from'] ?? null) {
                            $indicators['join_from'] = 'Join from ' . \Carbon\Carbon::parse($data['join_from'])->format('d/m/Y');
                        }
                        if ($data['join_until'] ?? null) {
                            $indicators['join_until'] = 'Join until ' . \Carbon\Carbon::parse($data['join_until'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),

                Tables\Filters\TrashedFilter::make()
                    ->label('Deleted Status')
                    ->default(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-m-eye')
                    ->color('info'),

                Tables\Actions\EditAction::make()
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning'),

                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-m-trash')
                    ->color('danger'),

                Tables\Actions\RestoreAction::make()
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('success'),

                Tables\Actions\ForceDeleteAction::make()
                    ->icon('heroicon-m-trash')
                    ->color('danger'),

                // TAMBAHKAN ACTION UNTUK RESET LOGIN HISTORY
                Tables\Actions\Action::make('resetLoginHistory')
                    ->icon('heroicon-m-arrow-path')
                    ->color('gray')
                    ->label('Reset Login')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->update([
                            'last_login_at' => null,
                            'last_login_ip' => null,
                            'current_login_at' => null,
                            'current_login_ip' => null,
                            'login_count' => 0,
                        ]);
                    })
                    ->visible(fn (User $record) => $record->login_count > 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),

                    Tables\Actions\RestoreBulkAction::make(),

                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                        })
                        ->requiresConfirmation(),

                    // TAMBAHKAN BULK ACTION UNTUK RESET LOGIN
                    Tables\Actions\BulkAction::make('resetLoginHistory')
                        ->icon('heroicon-m-arrow-path')
                        ->color('gray')
                        ->label('Reset Login History')
                        ->action(function ($records) {
                            $records->each->update([
                                'last_login_at' => null,
                                'last_login_ip' => null,
                                'current_login_at' => null,
                                'current_login_ip' => null,
                                'login_count' => 0,
                            ]);
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-m-plus')
                    ->label('Create User'),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferLoading();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Profile')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\ImageEntry::make('avatar')
                                ->circular()
                                ->size(120)
                                ->defaultImageUrl(function ($record) {
                                    return 'https://ui-avatars.com/api/?name=' . 
                                           urlencode($record->name) . 
                                           '&color=7F9CF5&background=EBF4FF&size=128';
                                })
                                ->extraImgAttributes([
                                    'class' => 'border-4 border-white shadow',
                                ]),

                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->label('Full Name')
                                        ->size('lg')
                                        ->weight('bold'),

                                    Infolists\Components\TextEntry::make('email')
                                        ->label('Email Address')
                                        ->icon('heroicon-m-envelope')
                                        ->copyable()
                                        ->color('primary'),

                                    Infolists\Components\TextEntry::make('phone')
                                        ->label('Phone Number')
                                        ->icon('heroicon-m-phone')
                                        ->copyable()
                                        ->placeholder('-'),

                                    Infolists\Components\TextEntry::make('role')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'admin' => 'danger',
                                            'manager' => 'warning',
                                            'tailor' => 'info',
                                            'cashier' => 'success',
                                            default => 'gray',
                                        })
                                        ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                ]),
                        ])->columnSpanFull(),
                    ]),

                // TAMBAHKAN SECTION LOGIN HISTORY
                Infolists\Components\Section::make('Login History')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('last_login_at')
                                    ->label('Last Login')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon('heroicon-m-arrow-left-on-rectangle')
                                    ->placeholder('Never logged in')
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('last_login_ip')
                                    ->label('Last Login IP')
                                    ->icon('heroicon-m-computer-desktop')
                                    ->placeholder('No IP recorded')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('login_count')
                                    ->label('Total Logins')
                                    ->numeric()
                                    ->icon('heroicon-m-chart-bar')
                                    ->placeholder('0'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('current_login_at')
                                    ->label('Current Login')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon('heroicon-m-check-circle')
                                    ->placeholder('Not currently logged in')
                                    ->color(fn ($record) => $record->isCurrentlyLoggedIn() ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('current_login_ip')
                                    ->label('Current Login IP')
                                    ->icon('heroicon-m-wifi')
                                    ->placeholder('No active session')
                                    ->copyable(),

                                Infolists\Components\IconEntry::make('isCurrentlyLoggedIn')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->trueColor('success')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->falseColor('gray')
                                    ->getStateUsing(fn ($record) => $record->isCurrentlyLoggedIn()),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Account Details')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Status')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->trueColor('success')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->falseColor('danger'),

                                Infolists\Components\TextEntry::make('join_date')
                                    ->label('Join Date')
                                    ->date('d/m/Y')
                                    ->icon('heroicon-m-calendar')
                                    ->placeholder('-'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon('heroicon-m-clock')
                                    ->since(),

                                Infolists\Components\TextEntry::make('deleted_at')
                                    ->label('Deleted At')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon('heroicon-m-trash')
                                    ->placeholder('Not deleted')
                                    ->color('danger'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Contact Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('address')
                            ->label('Address')
                            ->placeholder('No address provided')
                            ->columnSpanFull()
                            ->markdown()
                            ->prose(),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Additional Notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes available')
                            ->columnSpanFull()
                            ->markdown()
                            ->prose(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Tambahkan relations di sini jika diperlukan
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::count() > 10 ? 'warning' : 'success';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'last_login_ip', 'current_login_ip'];
    }
}