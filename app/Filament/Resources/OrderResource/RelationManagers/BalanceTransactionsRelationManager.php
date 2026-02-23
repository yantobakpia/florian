<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\BalanceTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class BalanceTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'balanceTransactions';

    protected static ?string $title = 'Transaksi Kas';

    protected static ?string $label = 'Transaksi Kas';

    protected static ?string $pluralLabel = 'Transaksi Kas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Jenis')
                    ->options([
                        'in' => 'Uang Masuk',
                        'out' => 'Uang Keluar',
                    ])
                    ->required()
                    ->disabled(),

                Forms\Components\TextInput::make('amount')
                    ->label('Jumlah')
                    ->numeric()
                    ->required()
                    ->prefix('Rp')
                    ->disabled(),

                Forms\Components\TextInput::make('description')
                    ->label('Keterangan')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->disabled(),

                Forms\Components\Select::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(BalanceTransaction::PAYMENT_METHODS)
                    ->required()
                    ->disabled(),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull()
                    ->disabled(),

                Forms\Components\DateTimePicker::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->required()
                    ->disabled(),

                Forms\Components\Placeholder::make('balance_info')
                    ->label('Info Saldo')
                    ->content(function ($record) {
                        if (!$record) return '';
                        
                        return new HtmlString(
                            '<div class="space-y-1 p-3 bg-gray-50 rounded-lg">' .
                            '<div class="flex justify-between">' .
                            '<span class="text-gray-600">Saldo Sebelum:</span>' .
                            '<span class="font-semibold">Rp ' . number_format($record->balance_before, 0, ',', '.') . '</span>' .
                            '</div>' .
                            '<div class="flex justify-between">' .
                            '<span class="text-gray-600">Transaksi:</span>' .
                            '<span class="font-semibold ' . ($record->type === 'in' ? 'text-green-600' : 'text-red-600') . '">' .
                            ($record->type === 'in' ? '+' : '-') . ' Rp ' . number_format($record->amount, 0, ',', '.') .
                            '</span>' .
                            '</div>' .
                            '<div class="flex justify-between border-t pt-2">' .
                            '<span class="font-bold text-gray-700">Saldo Setelah:</span>' .
                            '<span class="font-bold ' . ($record->balance_after >= 0 ? 'text-green-600' : 'text-red-600') . '">' .
                            'Rp ' . number_format($record->balance_after, 0, ',', '.') .
                            '</span>' .
                            '</div>' .
                            '</div>'
                        );
                    })
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'in' ? 'MASUK' : 'KELUAR')
                    ->color(fn ($state) => $state === 'in' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->color(fn ($record) => $record->type === 'in' ? 'success' : 'danger')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo Setelah')
                    ->money('IDR')
                    ->color(fn ($record) => $record->balance_after >= 0 ? 'success' : 'danger')
                    ->alignRight(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'cash' => 'TUNAI',
                        'transfer' => 'TRANSFER',
                        'qris' => 'QRIS',
                        'debit' => 'DEBIT',
                        'credit' => 'KREDIT',
                        default => strtoupper($state),
                    })
                    ->color(fn ($state) => match($state) {
                        'cash' => 'success',
                        'transfer' => 'info',
                        'qris' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Transaksi')
                    ->options([
                        'in' => 'Uang Masuk',
                        'out' => 'Uang Keluar',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options(BalanceTransaction::PAYMENT_METHODS),

                Tables\Filters\Filter::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari'),
                        Forms\Components\DatePicker::make('to')
                            ->label('Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query, $date) => $query->whereDate('transaction_date', '>=', $date)
                            )
                            ->when(
                                $data['to'],
                                fn ($query, $date) => $query->whereDate('transaction_date', '<=', $date)
                            );
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Transaksi Kas')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Jenis')
                            ->options([
                                'in' => 'Uang Masuk',
                                'out' => 'Uang Keluar',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->step(0.01),

                        Forms\Components\TextInput::make('description')
                            ->label('Keterangan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->default(fn ($get) => $get('type') === 'in' ? 'Pemasukan Kas' : 'Pengeluaran Kas'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options(BalanceTransaction::PAYMENT_METHODS)
                            ->required()
                            ->default('cash'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                    ])
                    ->action(function (array $data, $relationship): void {
                        // Otomatis set reference ke order yang sedang dilihat
                        $data['reference_type'] = 'App\Models\Order';
                        $data['reference_id'] = $relationship->getParent()->id;
                        $data['created_by'] = auth()->id();
                        
                        BalanceTransaction::recordTransaction($data);
                    })
                    ->successNotificationTitle('Transaksi kas berhasil ditambahkan')
                    ->modalWidth('2xl'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->modalHeading('Detail Transaksi Kas'),

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Jenis')
                            ->options([
                                'in' => 'Uang Masuk',
                                'out' => 'Uang Keluar',
                            ])
                            ->required()
                            ->disabled(), // Tidak bisa edit type karena mempengaruhi saldo

                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->step(0.01)
                            ->disabled(), // Tidak bisa edit amount karena mempengaruhi saldo

                        Forms\Components\TextInput::make('description')
                            ->label('Keterangan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options(BalanceTransaction::PAYMENT_METHODS)
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->seconds(false),
                    ])
                    ->successNotificationTitle('Transaksi kas berhasil diperbarui')
                    ->modalWidth('2xl'),

                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->successNotificationTitle('Transaksi kas berhasil dihapus')
                    ->before(function ($record, Tables\Actions\DeleteAction $action) {
                        if (!$record->canDelete()) {
                            $action->cancel();
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Tidak dapat menghapus')
                                ->body('Tidak dapat menghapus transaksi ini karena akan mengganggu konsistensi saldo.')
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus')
                        ->requiresConfirmation()
                        ->before(function ($records, Tables\Actions\DeleteBulkAction $action) {
                            foreach ($records as $record) {
                                if (!$record->canDelete()) {
                                    $action->cancel();
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Tidak dapat menghapus')
                                        ->body('Beberapa transaksi tidak dapat dihapus karena akan mengganggu konsistensi saldo.')
                                        ->send();
                                    break;
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('Belum ada transaksi kas')
            ->emptyStateDescription('Transaksi kas untuk order ini akan muncul di sini.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Transaksi Kas Pertama')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Jenis')
                            ->options([
                                'in' => 'Uang Masuk',
                                'out' => 'Uang Keluar',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->step(0.01),

                        Forms\Components\TextInput::make('description')
                            ->label('Keterangan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->default(fn ($get) => $get('type') === 'in' ? 'Pemasukan Kas' : 'Pengeluaran Kas'),

                        Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options(BalanceTransaction::PAYMENT_METHODS)
                            ->required()
                            ->default('cash'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->required()
                            ->default(now())
                            ->seconds(false),
                    ])
                    ->action(function (array $data, $relationship): void {
                        $data['reference_type'] = 'App\Models\Order';
                        $data['reference_id'] = $relationship->getParent()->id;
                        $data['created_by'] = auth()->id();
                        
                        BalanceTransaction::recordTransaction($data);
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}