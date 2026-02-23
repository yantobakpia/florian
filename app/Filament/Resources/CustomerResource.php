<?php
// app/Filament/Resources/CustomerResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Customer Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('phone')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('whatsapp')
                                    ->icon('heroicon-s-chat-bubble-left-right')
                                    ->color('success')
                                    ->url(function ($get) {
                                        $phone = $get('phone');
                                        if (!empty($phone)) {
                                            $phone = preg_replace('/[^0-9]/', '', $phone);
                                            
                                            if (substr($phone, 0, 1) === '0') {
                                                $phone = '62' . substr($phone, 1);
                                            }
                                            
                                            if (substr($phone, 0, 2) !== '62') {
                                                $phone = '62' . $phone;
                                            }
                                            
                                            return "https://wa.me/{$phone}";
                                        }
                                        return null;
                                    })
                                    ->openUrlInNewTab()
                                    ->tooltip('Buka WhatsApp')
                                    ->visible(fn ($get) => !empty($get('phone')))
                            ),
                        
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ])->columns(2),
                
                Forms\Components\Section::make('Detail Customer')
                    ->schema([
                        Forms\Components\Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options([
                                'male' => 'Laki-laki',
                                'female' => 'Perempuan',
                                'other' => 'Lainnya',
                            ])
                            ->nullable(),
                        
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3)
                            ->maxLength(500),
                        
                        Forms\Components\Textarea::make('measurement_notes')
                            ->label('Catatan Ukuran')
                            ->rows(3)
                            ->helperText('Catatan khusus ukuran badan customer'),
                        
                        Forms\Components\Textarea::make('preferences')
                            ->label('Preferensi')
                            ->rows(3)
                            ->helperText('Preferensi bahan, model, atau style favorit'),
                    ])->columns(1),
                
                Forms\Components\Section::make('Catatan & Stats')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Internal')
                            ->rows(3)
                            ->maxLength(1000),
                    ])->collapsed(),
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
                
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->sortable()
                    ->url(function ($record) {
                        // Membuat link WhatsApp jika nomor telepon tersedia
                        if (!empty($record->phone)) {
                            // Format nomor: hapus karakter non-digit
                            $phone = preg_replace('/[^0-9]/', '', $record->phone);
                            
                            // Jika nomor diawali dengan 0, ubah menjadi 62 (kode Indonesia)
                            if (substr($phone, 0, 1) === '0') {
                                $phone = '62' . substr($phone, 1);
                            }
                            
                            // Pastikan nomor sudah diawali dengan 62
                            if (substr($phone, 0, 2) !== '62') {
                                $phone = '62' . $phone;
                            }
                            
                            return "https://wa.me/{$phone}";
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->icon('heroicon-s-chat-bubble-left-right')
                    ->color('success')
                    ->iconPosition('after')
                    ->tooltip('Klik untuk chat via WhatsApp'),
                
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'male' => 'Laki',
                        'female' => 'Perempuan',
                        default => 'Lainnya'
                    })
                    ->color(fn ($state) => match($state) {
                        'male' => 'info',
                        'female' => 'pink',
                        default => 'gray'
                    }),
                
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Total Order')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Belanja')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight(),
                
                Tables\Columns\TextColumn::make('last_order_date')
                    ->label('Order Terakhir')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\IconColumn::make('is_regular')
                    ->label('Regular')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->isRegularCustomer())
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Laki-laki',
                        'female' => 'Perempuan',
                        'other' => 'Lainnya',
                    ]),
                
                Tables\Filters\Filter::make('is_regular')
                    ->label('Customer Regular')
                    ->query(fn ($query) => $query->where('total_orders', '>=', 3)),
                
                Tables\Filters\Filter::make('has_email')
                    ->label('Ada Email')
                    ->query(fn ($query) => $query->whereNotNull('email')),
                
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color('success')
                    ->url(function ($record) {
                        if (!empty($record->phone)) {
                            $phone = preg_replace('/[^0-9]/', '', $record->phone);
                            
                            if (substr($phone, 0, 1) === '0') {
                                $phone = '62' . substr($phone, 1);
                            }
                            
                            if (substr($phone, 0, 2) !== '62') {
                                $phone = '62' . $phone;
                            }
                            
                            return "https://wa.me/{$phone}";
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => !empty($record->phone)),
                
                Tables\Actions\Action::make('quick_order')
                    ->label('Buat Order')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->url(fn ($record) => route('filament.admin.resources.orders.create', [
                        'customer_id' => $record->id
                    ])),
                
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('export_contacts')
                        ->label('Export Kontak')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // Logic untuk export kontak
                        }),
                        
                    Tables\Actions\BulkAction::make('send_whatsapp_bulk')
                        ->label('Kirim WhatsApp')
                        ->icon('heroicon-m-chat-bubble-left-right')
                        ->color('success')
                        ->action(function ($records) {
                            // Logika untuk mengirim WhatsApp ke multiple customers
                            $phones = [];
                            foreach ($records as $record) {
                                if (!empty($record->phone)) {
                                    $phone = preg_replace('/[^0-9]/', '', $record->phone);
                                    
                                    if (substr($phone, 0, 1) === '0') {
                                        $phone = '62' . substr($phone, 1);
                                    }
                                    
                                    if (substr($phone, 0, 2) !== '62') {
                                        $phone = '62' . $phone;
                                    }
                                    
                                    $phones[] = $phone;
                                }
                            }
                            
                            if (!empty($phones)) {
                                $whatsappLinks = [];
                                foreach ($phones as $phone) {
                                    $whatsappLinks[] = "https://wa.me/{$phone}";
                                }
                                
                                // Simpan ke session atau tampilkan modal dengan links
                                session()->flash('whatsapp_bulk_links', $whatsappLinks);
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Kirim WhatsApp ke Multiple Customers')
                        ->modalDescription('Ini akan membuka WhatsApp untuk setiap customer yang dipilih. Lanjutkan?')
                        ->modalSubmitActionLabel('Buka WhatsApp'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Nanti kita tambah relation ke orders
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}