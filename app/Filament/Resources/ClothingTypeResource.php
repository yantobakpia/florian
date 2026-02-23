<?php
// app/Filament/Resources/ClothingTypeResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\ClothingTypeResource\Pages;
use App\Models\ClothingType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClothingTypeResource extends Resource
{
    protected static ?string $model = ClothingType::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Jenis Pakaian')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Jenis Pakaian')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        
                        Forms\Components\Toggle::make('is_custom')
                            ->label('Custom Type?')
                            ->helperText('Centang jika ini tipe custom yang bisa diinput user')
                            ->reactive()
                            ->inline(false),
                    ]),
                
                Forms\Components\Section::make('Harga & Material Default')
                    ->schema([
                        Forms\Components\TextInput::make('base_price')
                            ->label('Harga Dasar (Opsional)')
                            ->prefix('Rp')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Harga default, bisa di override di order'),
                        
                        Forms\Components\TextInput::make('material_needed')
                            ->label('Kebutuhan Material Default (meter)')
                            ->numeric()
                            ->minValue(0.1)
                            ->step(0.1)
                            ->suffix('m')
                            ->helperText('Estimasi kebutuhan bahan per item'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                        
                        Forms\Components\TextInput::make('order_count')
                            ->label('Jumlah Order')
                            ->numeric()
                            ->disabled()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('base_price')
                    ->label('Harga Dasar')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('material_needed')
                    ->label('Kebutuhan Material')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} m" : '-')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('is_custom')
                    ->label('Custom')
                    ->boolean()
                    ->trueIcon('heroicon-o-pencil')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('warning')
                    ->falseColor('success'),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('order_count')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Aktif Saja')
                    ->query(fn ($query) => $query->where('is_active', true)),
                
                Tables\Filters\SelectFilter::make('is_custom')
                    ->label('Tipe')
                    ->options([
                        '1' => 'Custom Types',
                        '0' => 'Standard Types',
                    ]),
                
                Tables\Filters\Filter::make('popular')
                    ->label('Populer (≥ 10 order)')
                    ->query(fn ($query) => $query->where('order_count', '>=', 10)),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->action(function ($record) {
                        $record->is_active = !$record->is_active;
                        $record->save();
                    }),
                
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Aktifkan')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClothingTypes::route('/'),
            'create' => Pages\CreateClothingType::route('/create'),
            'edit' => Pages\EditClothingType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('order_count', 'desc')
            ->orderBy('name');
    }
}