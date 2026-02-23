<?php
// app/Filament/Resources/OrderResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Customer;
use App\Models\ClothingType;
use App\Models\BatchClothingItem;
use App\Models\OrderPayment; 
use App\Filament\Resources\CustomerResource; 
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Order Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'order_number';
    protected static ?string $slug = 'orders';
    protected static ?string $navigationLabel = 'Order';
    protected static ?string $modelLabel = 'Order';
    protected static ?string $pluralModelLabel = 'Orders';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\Toggle::make('is_batch')
                            ->label('Batch Order (Kelompok)')
                            ->onIcon('heroicon-o-user-group')
                            ->offIcon('heroicon-o-user')
                            ->onColor('primary')
                            ->offColor('success')
                            ->inline()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('is_batch_multi_item', false);
                                }
                            }),
                        
                        Forms\Components\Toggle::make('is_batch_multi_item')
                            ->label('Batch dengan Multi Jenis Pakaian')
                            ->helperText('Centang jika batch ini memiliki beberapa jenis pakaian (contoh: kemeja pendek & panjang)')
                            ->hidden(fn (callable $get): bool => !$get('is_batch'))
                            ->reactive(),
                        
                        Forms\Components\Placeholder::make('order_type_info')
                            ->label('Tipe Order')
                            ->content(function (callable $get) {
                                $isBatch = $get('is_batch');
                                $isBatchMulti = $get('is_batch_multi_item');
                                
                                if ($isBatch) {
                                    if ($isBatchMulti) {
                                        return new HtmlString('
                                            <div class="flex items-center space-x-2 p-3 bg-purple-50 rounded-lg">
                                                <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M11 17a1 1 0 001.447.894l4-2A1 1 0 0017 15V9.236a1 1 0 00-1.447-.894l-4 2a1 1 0 00-.553.894V17zM15.211 6.276a1 1 0 000-1.788l-4.764-2.382a1 1 0 00-.894 0L4.789 4.488a1 1 0 000 1.788l4.764 2.382a1 1 0 00.894 0l4.764-2.382zM4.447 8.342A1 1 0 003 9.236V15a1 1 0 00.553.894l4 2A1 1 0 009 17v-5.764a1 1 0 00-.553-.894l-4-2z"/>
                                                </svg>
                                                <span class="font-medium text-purple-700">Batch Multi-Jenis Order</span>
                                                <span class="text-sm text-purple-600">(Beberapa jenis pakaian dalam 1 batch)</span>
                                            </div>
                                        ');
                                    } else {
                                        return new HtmlString('
                                            <div class="flex items-center space-x-2 p-3 bg-primary-50 rounded-lg">
                                                <svg class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/>
                                                </svg>
                                                <span class="font-medium text-primary-700">Batch Single Jenis Order</span>
                                                <span class="text-sm text-primary-600">(1 jenis pakaian untuk seluruh batch)</span>
                                            </div>
                                        ');
                                    }
                                } else {
                                    return new HtmlString('
                                        <div class="flex items-center space-x-2 p-3 bg-success-50 rounded-lg">
                                            <svg class="w-5 h-5 text-success-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="font-medium text-success-700">Single Order (Order Individu)</span>
                                        </div>
                                    ');
                                }
                            })
                            ->columnSpanFull()
                            ->hidden(fn (callable $get): bool => $get('is_batch') === null),
                    ])
                    ->columns(1),
                
                Forms\Components\Card::make()
                    ->schema(function (callable $get) {
                        $isBatch = $get('is_batch');
                        $isBatchMulti = $get('is_batch_multi_item');
                        
                        if ($isBatch) {
                            if ($isBatchMulti) {
                                return self::batchMultiItemSchema();
                            }
                            return self::batchSingleItemSchema();
                        }
                        
                        return self::singleOrderSchema();
                    })
                    ->hidden(fn (callable $get): bool => $get('is_batch') === null)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Schema untuk Single Order
     */
    private static function singleOrderSchema(): array
    {
        return [
            Forms\Components\Wizard::make([
                Forms\Components\Wizard\Step::make('Customer & Produk')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Pilih customer atau buat baru')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Nama Lengkap'),
                                
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->maxLength(20)
                                    ->label('No. Telepon')
                                    ->helperText('Contoh: 081234567890'),
                            ])
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $customer = Customer::find($state);
                                    if ($customer) {
                                        $set('customer_info', $state);
                                    }
                                }
                            }),
                        
                        Forms\Components\Placeholder::make('customer_info')
                            ->label('Info Customer')
                            ->content(function (callable $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) {
                                    return new HtmlString('<span class="text-gray-500">Belum memilih customer</span>');
                                }
                                
                                $customer = Customer::find($customerId);
                                if ($customer) {
                                    $info = "<div class='space-y-1'>";
                                    $info .= "<div class='font-bold'>{$customer->name}</div>";
                                    if ($customer->phone) {
                                        $info .= "<div class='text-sm'>📞 {$customer->phone}</div>";
                                    }
                                    $info .= "</div>";
                                    return new HtmlString($info);
                                }
                                
                                return new HtmlString('<span class="text-gray-500">Customer tidak ditemukan</span>');
                            })
                            ->columnSpanFull(),
                        
                        Forms\Components\Select::make('clothing_type_id')
                            ->label('Jenis Pakaian')
                            ->relationship('clothingType', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $clothingType = ClothingType::find($state);
                                    if ($clothingType) {
                                        $set('base_price', $clothingType->base_price ?? 0);
                                        if ($clothingType->is_custom) {
                                            $set('custom_clothing_type', $clothingType->name);
                                        }
                                        $set('size_surcharge', 0);
                                        $set('size_price', $clothingType->base_price ?? 0);
                                    }
                                }
                            }),
                        
                        Forms\Components\TextInput::make('custom_clothing_type')
                            ->label('Atau Tulis Jenis Pakaian Custom')
                            ->maxLength(100)
                            ->placeholder('Contoh: Gamis, Setelan, dll'),
                        
                        Forms\Components\TextInput::make('base_price')
                            ->label('Harga Dasar (M/L)')
                            ->prefix('Rp')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->step(1000)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $size = $get('size');
                                $sizeSurcharge = Order::calculateSizeSurcharge($size);
                                $set('size_surcharge', $sizeSurcharge);
                                $sizePrice = floatval($state) + floatval($sizeSurcharge);
                                $set('size_price', $sizePrice);
                                self::calculateSingleTotal($get, $set);
                            }),
                        
                        Forms\Components\Select::make('size')
                            ->label('Ukuran')
                            ->options([
                                'XS' => 'XS',
                                'S' => 'S',
                                'M' => 'M',
                                'L' => 'L',
                                'XL' => 'XL',
                                'XXL' => 'XXL (+5,000)',
                                'XXXL' => 'XXXL/3XL (+10,000)',
                                '4XL' => '4XL (+15,000)',
                                '5XL' => '5XL (+20,000)',
                                '6XL' => '6XL (+25,000)',
                                '7XL' => '7XL (+30,000)',
                            ])
                            ->default('M')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $basePrice = $get('base_price') ?? 0;
                                $sizeSurcharge = Order::calculateSizeSurcharge($state);
                                $set('size_surcharge', $sizeSurcharge);
                                $sizePrice = floatval($basePrice) + floatval($sizeSurcharge);
                                $set('size_price', $sizePrice);
                                self::calculateSingleTotal($get, $set);
                            }),
                        
                        Forms\Components\TextInput::make('size_price')
                            ->label('Harga Final Ukuran')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0)
                            ->readOnly()
                            ->helperText('Harga dasar + tambahan ukuran'),
                        
                        Forms\Components\TextInput::make('size_surcharge')
                            ->label('Tambahan Ukuran')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0)
                            ->readOnly()
                            ->helperText('Untuk XXXL/3XL ke atas'),
                        
                        Forms\Components\TextInput::make('single_quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                self::calculateSingleTotal($get, $set);
                            }),
                        
                        Forms\Components\TextInput::make('color')
                            ->label('Warna')
                            ->maxLength(50)
                            ->helperText('Contoh: Hitam, Putih, Navy'),
                        
                        Forms\Components\Textarea::make('measurement_notes')
                            ->label('Catatan Ukuran')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Contoh: Lingkar dada 100cm, Panjang baju 70cm'),
                    ])
                    ->columns(2),
                
                Forms\Components\Wizard\Step::make('Biaya Tambahan')
                    ->schema([
                        Forms\Components\Repeater::make('additional_fees_items')
                            ->label('Biaya Tambahan')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Jenis Biaya')
                                    ->options([
                                        'embroidery' => 'Embroidery/Sulaman',
                                        'printing' => 'Printing/Sablon',
                                        'express' => 'Express Service',
                                        'material_upgrade' => 'Upgrade Material',
                                        'design_complex' => 'Desain Kompleks',
                                        'other' => 'Lainnya',
                                    ])
                                    ->required(),
                                
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Biaya')
                                    ->required()
                                    ->placeholder('Contoh: Embroidery Logo, Sablon Full Color, dll'),
                                
                                Forms\Components\TextInput::make('amount')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->required()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $get, callable $set) {
                                        self::calculateSingleTotal($get, $set);
                                    }),
                                
                                Forms\Components\Textarea::make('notes')
                                    ->label('Keterangan')
                                    ->rows(1)
                                    ->placeholder('Keterangan tambahan...'),
                            ])
                            ->addActionLabel('Tambah Biaya')
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull()
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                self::calculateSingleTotal($get, $set);
                            })
                            ->deleteAction(
                                fn ($action) => $action->after(function (callable $get, callable $set) {
                                    self::calculateSingleTotal($get, $set);
                                }),
                            ),
                        
                        Forms\Components\TextInput::make('discount')
                            ->label('Diskon')
                            ->prefix('Rp')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(1000)
                            ->reactive()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                self::calculateSingleTotal($get, $set);
                            }),
                        
                        Forms\Components\Placeholder::make('price_calculation')
                            ->label('Perhitungan Total')
                            ->content(function (callable $get) {
                                $basePrice = floatval($get('base_price') ?? 0);
                                $sizeSurcharge = floatval($get('size_surcharge') ?? 0);
                                $quantity = intval($get('single_quantity') ?? 1);
                                $discount = floatval($get('discount') ?? 0);
                                $additionalFees = $get('additional_fees_items') ?? [];
                                $additionalTotal = 0;
                                
                                foreach ($additionalFees as $fee) {
                                    if (isset($fee['amount'])) {
                                        $additionalTotal += floatval($fee['amount']);
                                    }
                                }
                                
                                $sizePrice = $basePrice + $sizeSurcharge;
                                $subtotal = $sizePrice * $quantity;
                                $total = $subtotal + $additionalTotal - $discount;
                                
                                $sizeName = $get('size') ?? 'M';
                                $sizeLabel = Order::getSizeLabel($sizeName);
                                
                                $html = "
                                    <div class='space-y-2 text-sm p-4 bg-gray-50 rounded-lg'>
                                        <div class='grid grid-cols-2 gap-2'>
                                            <div class='text-gray-600'>Harga Dasar (M/L):</div>
                                            <div class='text-right font-semibold'>Rp " . number_format($basePrice, 0, ',', '.') . "</div>
                                            
                                            <div class='text-gray-600'>Ukuran ({$sizeLabel}):</div>
                                            <div class='text-right font-semibold'>" . ($sizeSurcharge > 0 ? '+ Rp ' . number_format($sizeSurcharge, 0, ',', '.') : 'Tidak ada tambahan') . "</div>
                                            
                                            <div class='text-gray-600 border-t pt-2 font-bold'>Harga per Item:</div>
                                            <div class='text-right border-t pt-2 font-bold text-green-600'>Rp " . number_format($sizePrice, 0, ',', '.') . "</div>
                                            
                                            <div class='text-gray-600'>× Jumlah:</div>
                                            <div class='text-right font-semibold'>{$quantity} pcs</div>
                                            
                                            <div class='text-gray-600 border-t pt-2 font-bold'>Subtotal Item:</div>
                                            <div class='text-right border-t pt-2 font-bold'>Rp " . number_format($subtotal, 0, ',', '.') . "</div>
                                            
                                            <div class='text-gray-600'>+ Biaya Tambahan:</div>
                                            <div class='text-right font-semibold'>+ Rp " . number_format($additionalTotal, 0, ',', '.') . "</div>
                                            
                                            <div class='text-gray-600'>- Diskon:</div>
                                            <div class='text-right font-semibold text-red-600'>- Rp " . number_format($discount, 0, ',', '.') . "</div>
                                            
                                            <div class='text-gray-700 border-t pt-2 font-bold text-lg mt-2'>TOTAL:</div>
                                            <div class='text-right border-t pt-2 font-bold text-lg text-primary-600'>Rp " . number_format($total, 0, ',', '.') . "</div>
                                        </div>
                                    </div>
                                ";
                                
                                return new HtmlString($html);
                            })
                            ->columnSpanFull()
                            ->reactive(),
                    ])
                    ->columns(2),
                
                Forms\Components\Wizard\Step::make('Pembayaran & Status')
                    ->schema([
                        Forms\Components\Section::make('Informasi Pembayaran')
                            ->schema([
                                Forms\Components\Select::make('payment_status')
                                    ->label('Status Pembayaran')
                                    ->options([
                                        'unpaid' => 'Belum Bayar',
                                        'dp' => 'DP',
                                        'partial' => 'Cicilan',
                                        'paid' => 'Lunas',
                                    ])
                                    ->default('unpaid')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state === 'paid') {
                                            $set('dp_paid', $get('total_price'));
                                        } elseif ($state === 'unpaid') {
                                            $set('dp_paid', 0);
                                        }
                                    }),
                                
                                Forms\Components\TextInput::make('dp_paid')
                                    ->label('DP/Cicilan Dibayar')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(1000)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $totalPrice = floatval($get('total_price') ?? 0);
                                        $dpPaid = floatval($state);
                                        if ($dpPaid >= $totalPrice) {
                                            $set('payment_status', 'paid');
                                        } elseif ($dpPaid > 0) {
                                            $set('payment_status', 'dp');
                                        } else {
                                            $set('payment_status', 'unpaid');
                                        }
                                    }),
                                
                                Forms\Components\Select::make('payment_method')
                                    ->label('Metode Pembayaran')
                                    ->options([
                                        'cash' => 'Cash',
                                        'transfer' => 'Transfer',
                                        'qris' => 'QRIS',
                                        'other' => 'Lainnya',
                                    ])
                                    ->default('cash'),
                                
                                Forms\Components\Textarea::make('payment_notes')
                                    ->label('Catatan Pembayaran')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->placeholder('Catatan khusus tentang pembayaran...'),
                            ])
                            ->columns(2),
                        
                        Forms\Components\Section::make('Status & Jadwal')
                            ->schema([
                                Forms\Components\Select::make('order_status')
                                    ->label('Status Order')
                                    ->options([
                                        'pending' => 'Pending',
                                        'design_review' => 'Review Desain',
                                        'measurement' => 'Pengukuran',
                                        'cutting' => 'Pemotongan',
                                        'sewing' => 'Penjahitan',
                                        'finishing' => 'Finishing',
                                        'ready' => 'Siap Diambil',
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan',
                                    ])
                                    ->default('pending')
                                    ->required(),
                                
                                Forms\Components\Select::make('priority')
                                    ->label('Prioritas')
                                    ->options([
                                        'low' => 'Rendah',
                                        'normal' => 'Normal',
                                        'high' => 'Tinggi',
                                        'urgent' => 'Mendesak',
                                    ])
                                    ->default('normal')
                                    ->required(),
                                
                                Forms\Components\DatePicker::make('order_date')
                                    ->label('Tanggal Order')
                                    ->default(now()->addDays())
                                    ->required()
                                    ->maxDate(now()->addDays(7)),
                                
                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Deadline/Tanggal Jadi')
                                    ->default(now()->addDays(7))
                                    ->required()
                                    ->minDate(now())
                                    ->helperText('Estimasi tanggal selesai'),
                            ])
                            ->columns(2),
                        
                        Forms\Components\Textarea::make('customer_notes')
                            ->label('Catatan Customer')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Permintaan khusus dari customer'),
                        
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Catatan Internal')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Catatan untuk internal'),
                        
                        // Hidden fields untuk single order
                        Forms\Components\Hidden::make('total_price')
                            ->default(0),
                        
                        Forms\Components\Hidden::make('additional_fees_total')
                            ->default(0),
                        
                        Forms\Components\Hidden::make('is_batch')
                            ->default(false),
                        
                        Forms\Components\Hidden::make('is_batch_multi_item')
                            ->default(false),
                        
                        Forms\Components\Hidden::make('quantity')
                            ->default(1)
                            ->dehydrateStateUsing(function (callable $get) {
                                return (int) ($get('single_quantity') ?? 1);
                            })
                            ->afterStateHydrated(function (callable $set, $state) {
                                $set('single_quantity', (int) ($state ?? 1));
                            }),
                    ])
                    ->columns(2),
            ])
            ->columnSpanFull()
            ->skippable(),
        ];
    }

    /**
     * Schema untuk Batch Single Item (1 jenis pakaian)
     */
    private static function batchSingleItemSchema(): array
    {
        return [
            Forms\Components\Section::make('Informasi Grup')
                ->schema([
                    Forms\Components\TextInput::make('group_name')
                        ->label('Nama Grup/Kelompok')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Contoh: Batch Order Angkatan 2024, Order Seragam OSIS, dll')
                        ->helperText('Nama untuk mengidentifikasi batch order ini'),
                    
                    Forms\Components\Select::make('customer_id')
                        ->label('Koordinator/Kontak Person')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->label('Nama Koordinator'),
                            
                            Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->required()
                                ->maxLength(20)
                                ->label('No. Telepon'),
                        ])
                        ->helperText('Orang yang bertanggung jawab untuk batch order ini'),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('Detail Produk Batch')
                ->schema([
                    Forms\Components\Select::make('clothing_type_id')
                        ->label('Jenis Pakaian')
                        ->relationship('clothingType', 'name')
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $clothingType = ClothingType::find($state);
                                if ($clothingType && $clothingType->base_price) {
                                    $set('base_price', $clothingType->base_price);
                                }
                            }
                        }),
                    
                    Forms\Components\TextInput::make('base_price')
                        ->label('Harga Dasar (Size M/L)')
                        ->prefix('Rp')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->minValue(0)
                        ->step(1000)
                        ->helperText('Harga dasar untuk size M/L')
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            self::calculateBatchItem($get, $set);
                        }),
                    
                    Forms\Components\TextInput::make('color')
                        ->label('Warna Umum')
                        ->maxLength(50)
                        ->placeholder('Contoh: Hitam, Putih, Navy')
                        ->helperText('Warna yang sama untuk semua item'),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('Distribusi Ukuran & Jumlah')
                ->description('Masukkan jumlah untuk setiap ukuran. Sistem akan hitung otomatis.')
                ->schema([
                    Forms\Components\Repeater::make('batch_items')
                        ->label('Distribusi Ukuran')
                        ->schema([
                            Forms\Components\Select::make('size')
                                ->label('Ukuran')
                                ->options([
                                    'XS' => 'XS',
                                    'S' => 'S',
                                    'M' => 'M',
                                    'L' => 'L',
                                    'XL' => 'XL',
                                    'XXL' => 'XXL (+5,000)',
                                    'XXXL' => 'XXXL/3XL (+10,000)',
                                    '4XL' => '4XL (+15,000)',
                                    '5XL' => '5XL (+20,000)',
                                    '6XL' => '6XL (+25,000)',
                                    '7XL' => '7XL (+30,000)',
                                ])
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    self::calculateBatchItem($get, $set);
                                }),
                            
                            Forms\Components\TextInput::make('batch_quantity')
                                ->label('Jumlah (Qty)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->reactive()
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    self::calculateBatchItem($get, $set);
                                }),
                            
                            Forms\Components\TextInput::make('price_per_item')
                                ->label('Harga per Item')
                                ->prefix('Rp')
                                ->numeric()
                                ->default(0)
                                ->readOnly()
                                ->helperText('Harga dasar + tambahan ukuran'),
                            
                            Forms\Components\TextInput::make('surcharge')
                                ->label('Tambahan Ukuran')
                                ->prefix('Rp')
                                ->numeric()
                                ->default(0)
                                ->readOnly()
                                ->helperText('Untuk XXXL/3XL ke atas'),
                            
                            Forms\Components\TextInput::make('subtotal')
                                ->label('Subtotal Ukuran Ini')
                                ->prefix('Rp')
                                ->numeric()
                                ->default(0)
                                ->readOnly()
                                ->helperText('Harga per item × jumlah'),
                        ])
                        ->columns(5)
                        ->columnSpanFull()
                        ->addActionLabel('Tambah Ukuran')
                        ->defaultItems(1)
                        ->reorderable()
                        ->collapsible()
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            self::calculateBatchTotal($get, $set);
                        })
                        ->afterStateHydrated(function (callable $set, $state) {
                            if (is_array($state)) {
                                $processedItems = [];
                                foreach ($state as $item) {
                                    $processedItems[] = [
                                        'size' => $item['size'] ?? 'M',
                                        'batch_quantity' => (int) ($item['batch_quantity'] ?? ($item['quantity'] ?? 1)),
                                        'notes' => $item['notes'] ?? null,
                                    ];
                                }
                                $set('batch_items', $processedItems);
                            }
                        }),
                ]),
            
            Forms\Components\Section::make('Biaya Tambahan & Diskon Grup')
                ->schema([
                    Forms\Components\Repeater::make('batch_additional_fees')
                        ->label('Biaya Tambahan (Untuk Seluruh Grup)')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Jenis Biaya')
                                ->options([
                                    'embroidery' => 'Embroidery/Sulaman',
                                    'printing' => 'Printing/Sablon',
                                    'express' => 'Express Service',
                                    'material_upgrade' => 'Upgrade Material',
                                    'design_complex' => 'Desain Kompleks',
                                    'other' => 'Lainnya',
                                ])
                                ->required(),
                            
                            Forms\Components\TextInput::make('name')
                                ->label('Nama Biaya')
                                ->required()
                                ->placeholder('Contoh: Sablon Logo Sekolah, Express 3 hari, dll'),
                            
                            Forms\Components\TextInput::make('amount')
                                ->label('Jumlah Total')
                                ->numeric()
                                ->required()
                                ->prefix('Rp')
                                ->default(0)
                                ->helperText('Jumlah untuk seluruh grup')
                                ->reactive()
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    self::calculateBatchTotal($get, $set);
                                }),
                            
                            Forms\Components\Textarea::make('notes')
                                ->label('Keterangan')
                                ->rows(1)
                                ->placeholder('Keterangan tambahan...'),
                        ])
                        ->addActionLabel('Tambah Biaya')
                        ->defaultItems(0)
                        ->collapsible()
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            self::calculateBatchTotal($get, $set);
                        }),
                    
                    Forms\Components\TextInput::make('discount')
                        ->label('Diskon Grup')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(1000)
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            self::calculateBatchTotal($get, $set);
                        }),
                ]),
            
            Forms\Components\Section::make('Ringkasan & Pembayaran')
                ->schema([
                    Forms\Components\Placeholder::make('batch_summary')
                        ->label('Ringkasan Batch Order')
                        ->content(function (callable $get) {
                            $batchItems = $get('batch_items') ?? [];
                            $additionalFees = $get('batch_additional_fees') ?? [];
                            $basePrice = floatval($get('base_price') ?? 0);
                            $discount = floatval($get('discount') ?? 0);
                            
                            $totalItems = 0;
                            $totalSurcharge = 0;
                            $totalAdditional = 0;
                            $sizeDistribution = [];
                            
                            foreach ($batchItems as $item) {
                                $quantity = (int) ($item['batch_quantity'] ?? 0);
                                $size = $item['size'] ?? 'M';
                                $surcharge = Order::calculateSizeSurcharge($size);
                                
                                $totalItems += $quantity;
                                $totalSurcharge += $surcharge * $quantity;
                                
                                if (!isset($sizeDistribution[$size])) {
                                    $sizeDistribution[$size] = 0;
                                }
                                $sizeDistribution[$size] += $quantity;
                            }
                            
                            foreach ($additionalFees as $fee) {
                                $totalAdditional += floatval($fee['amount'] ?? 0);
                            }
                            
                            $subtotal = ($basePrice * $totalItems) + $totalSurcharge;
                            $total = $subtotal + $totalAdditional - $discount;
                            
                            $sizeDistStr = '';
                            foreach ($sizeDistribution as $size => $count) {
                                if ($count > 0) {
                                    $surcharge = Order::calculateSizeSurcharge($size);
                                    $sizeLabel = Order::getSizeLabel($size);
                                    if ($surcharge > 0) {
                                        $sizeDistStr .= "<span class='px-2 py-1 bg-gray-100 rounded text-xs mr-2 mb-1 inline-block'>{$sizeLabel}: {$count} pcs (+" . number_format($surcharge, 0, ',', '.') . ")</span>";
                                    } else {
                                        $sizeDistStr .= "<span class='px-2 py-1 bg-gray-100 rounded text-xs mr-2 mb-1 inline-block'>{$sizeLabel}: {$count} pcs</span>";
                                    }
                                }
                            }
                            
                            $html = "
                                <div class='space-y-2 text-sm p-4 bg-gray-50 rounded-lg'>
                                    <div class='grid grid-cols-2 gap-2'>
                                        <div class='text-gray-600'>Harga Dasar (M/L):</div>
                                        <div class='text-right font-semibold'>Rp " . number_format($basePrice, 0, ',', '.') . "</div>
                                        
                                        <div class='text-gray-600'>Total Item:</div>
                                        <div class='text-right font-semibold'>{$totalItems} pcs</div>
                                        
                                        <div class='text-gray-600'>+ Total Tambahan Ukuran:</div>
                                        <div class='text-right font-semibold'>+ Rp " . number_format($totalSurcharge, 0, ',', '.') . "</div>
                                        
                                        <div class='text-gray-600'>+ Biaya Tambahan:</div>
                                        <div class='text-right font-semibold'>+ Rp " . number_format($totalAdditional, 0, ',', '.') . "</div>
                                        
                                        <div class='text-gray-600'>- Diskon Grup:</div>
                                        <div class='text-right font-semibold text-red-600'>- Rp " . number_format($discount, 0, ',', '.') . "</div>
                                        
                                        <div class='text-gray-700 border-t pt-2 font-bold text-lg mt-2'>TOTAL ORDER GRUP:</div>
                                        <div class='text-right border-t pt-2 font-bold text-lg text-primary-600'>Rp " . number_format($total, 0, ',', '.') . "</div>
                                    </div>
                                    <div class='mt-2 text-sm'>
                                        <div class='font-medium text-gray-700'>Distribusi Ukuran:</div>
                                        <div class='flex flex-wrap gap-1 mt-1'>
                                            {$sizeDistStr}
                                        </div>
                                    </div>
                                </div>
                            ";
                            
                            return new HtmlString($html);
                        })
                        ->columnSpanFull()
                        ->reactive(),
                    
                    Forms\Components\Select::make('payment_status')
                        ->label('Status Pembayaran Grup')
                        ->options([
                            'unpaid' => 'Belum Bayar',
                            'dp' => 'DP',
                            'partial' => 'Cicilan',
                            'paid' => 'Lunas',
                        ])
                        ->default('unpaid')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if ($state === 'paid') {
                                $set('dp_paid', $get('total_price'));
                            } elseif ($state === 'unpaid') {
                                $set('dp_paid', 0);
                            }
                        }),
                    
                    Forms\Components\TextInput::make('dp_paid')
                        ->label('DP Grup Dibayar')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(1000)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            $totalPrice = floatval($get('total_price') ?? 0);
                            $dpPaid = floatval($state);
                            if ($dpPaid >= $totalPrice) {
                                $set('payment_status', 'paid');
                            } elseif ($dpPaid > 0) {
                                $set('payment_status', 'dp');
                            } else {
                                $set('payment_status', 'unpaid');
                            }
                        }),
                    
                    Forms\Components\Select::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->options([
                            'cash' => 'Cash',
                            'transfer' => 'Transfer',
                            'qris' => 'QRIS',
                            'other' => 'Lainnya',
                        ])
                        ->default('cash'),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('Status & Jadwal')
                ->schema([
                    Forms\Components\Select::make('order_status')
                        ->label('Status Order')
                        ->options([
                            'pending' => 'Pending',
                            'design_review' => 'Review Desain',
                            'measurement' => 'Pengukuran',
                            'cutting' => 'Pemotongan',
                            'sewing' => 'Penjahitan',
                            'finishing' => 'Finishing',
                            'ready' => 'Siap Diambil',
                            'completed' => 'Selesai',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->default('pending')
                        ->required(),
                    
                    Forms\Components\Select::make('priority')
                        ->label('Prioritas')
                        ->options([
                            'low' => 'Rendah',
                            'normal' => 'Normal',
                            'high' => 'Tinggi',
                            'urgent' => 'Mendesak',
                        ])
                        ->default('normal')
                        ->required(),
                    
                    Forms\Components\DatePicker::make('order_date')
                        ->label('Tanggal Order')
                        ->default(now())
                        ->required()
                        ->maxDate(now()->addDays()),
                    
                    Forms\Components\DatePicker::make('due_date')
                        ->label('Deadline/Tanggal Jadi')
                        ->default(now()->addDays(10))
                        ->required()
                        ->minDate(now())
                        ->helperText('Estimasi tanggal selesai untuk seluruh batch'),
                    
                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Catatan Internal')
                        ->rows(2)
                        ->maxLength(500)
                        ->placeholder('Catatan untuk batch order ini...'),
                ])
                ->columns(2),
            
            // Hidden fields untuk batch order
            Forms\Components\Hidden::make('total_price')
                ->default(0),
            
            Forms\Components\Hidden::make('is_batch')
                ->default(true),
            
            Forms\Components\Hidden::make('is_batch_multi_item')
                ->default(false),
            
            Forms\Components\Hidden::make('quantity')
                ->default(0)
                ->dehydrateStateUsing(function (callable $get) {
                    $batchItems = $get('batch_items') ?? [];
                    $totalQuantity = 0;
                    
                    foreach ($batchItems as $item) {
                        $totalQuantity += (int) ($item['batch_quantity'] ?? 0);
                    }
                    
                    return $totalQuantity;
                }),
            
            Forms\Components\Hidden::make('batch_items_data')
                ->default('[]')
                ->dehydrateStateUsing(function (callable $get) {
                    $batchItems = $get('batch_items') ?? [];
                    $processedItems = [];
                    
                    foreach ($batchItems as $item) {
                        $processedItems[] = [
                            'size' => $item['size'] ?? 'M',
                            'quantity' => (int) ($item['batch_quantity'] ?? 1),
                            'notes' => $item['notes'] ?? null,
                        ];
                    }
                    
                    return json_encode($processedItems);
                }),
            
            Forms\Components\Hidden::make('batch_additional_fees_data')
                ->default('[]')
                ->dehydrateStateUsing(function (callable $get) {
                    $additionalFees = $get('batch_additional_fees') ?? [];
                    return json_encode($additionalFees);
                }),
            
            Forms\Components\Hidden::make('additional_fees_total')
                ->default(0)
                ->dehydrateStateUsing(function (callable $get) {
                    $additionalFees = $get('batch_additional_fees') ?? [];
                    $total = 0;
                    
                    foreach ($additionalFees as $fee) {
                        $total += floatval($fee['amount'] ?? 0);
                    }
                    
                    return $total;
                }),
        ];
    }

    /**
     * Schema untuk Batch Multi Item (beberapa jenis pakaian)
     */
    private static function batchMultiItemSchema(): array
    {
        return [
            Forms\Components\Section::make('Informasi Grup')
                ->schema([
                    Forms\Components\TextInput::make('group_name')
                        ->label('Nama Grup/Kelompok')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Contoh: Batch Order Angkatan 2024, Order Seragam OSIS, dll')
                        ->helperText('Nama untuk mengidentifikasi batch order ini'),
                    
                    Forms\Components\Select::make('customer_id')
                        ->label('Koordinator/Kontak Person')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->label('Nama Koordinator'),
                            
                            Forms\Components\TextInput::make('phone')
                                ->tel()
                                ->required()
                                ->maxLength(20)
                                ->label('No. Telepon'),
                        ])
                        ->helperText('Orang yang bertanggung jawab untuk batch order ini'),
                    
                    Forms\Components\TextInput::make('batch_color')
                        ->label('Warna Umum (Opsional)')
                        ->maxLength(50)
                        ->placeholder('Contoh: Hitam, Putih, Navy, Merah')
                        ->helperText('Warna yang sama untuk semua jenis pakaian (jika ada)'),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('Jenis Pakaian dalam Batch')
                ->description('Tambahkan jenis pakaian yang berbeda dalam batch ini. Contoh: Kemeja Pendek, Kemeja Panjang, dll')
                ->schema([
                    Forms\Components\Repeater::make('batch_clothing_items')
                        ->label('Daftar Jenis Pakaian')
                        ->relationship('batchClothingItems')
                        ->schema([
                            Forms\Components\Grid::make(4)
                                ->schema([
                                    Forms\Components\Select::make('clothing_type_id')
                                        ->label('Jenis Pakaian')
                                        ->relationship('clothingType', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state) {
                                                $clothingType = ClothingType::find($state);
                                                if ($clothingType) {
                                                    $set('base_price', $clothingType->base_price ?? 0);
                                                    if ($clothingType->is_custom) {
                                                        $set('custom_name', $clothingType->name);
                                                    }
                                                }
                                            }
                                        })
                                        ->columnSpan(2),
                                    
                                    Forms\Components\TextInput::make('custom_name')
                                        ->label('Atau Tulis Nama Custom')
                                        ->maxLength(100)
                                        ->placeholder('Contoh: Kemeja Pendek, Kemeja Panjang, Jaket, dll')
                                        ->columnSpan(2),
                                ]),
                            
                            Forms\Components\Grid::make(3)
                                ->schema([
                                    Forms\Components\TextInput::make('base_price')
                                        ->label('Harga Dasar (Size M/L)')
                                        ->prefix('Rp')
                                        ->numeric()
                                        ->required()
                                        ->default(0)
                                        ->minValue(0)
                                        ->step(1000)
                                        ->reactive()
                                        ->afterStateUpdated(function (callable $get, callable $set) {
                                            self::calculateBatchMultiItemTotal($get, $set);
                                        }),
                                    
                                    Forms\Components\TextInput::make('color')
                                        ->label('Warna Khusus (Opsional)')
                                        ->maxLength(50)
                                        ->placeholder('Kosongkan jika pakai warna umum')
                                        ->helperText('Biarkan kosong untuk menggunakan warna umum batch'),
                                    
                                    Forms\Components\Textarea::make('notes')
                                        ->label('Catatan Item')
                                        ->rows(1)
                                        ->maxLength(200)
                                        ->placeholder('Catatan khusus untuk jenis pakaian ini...'),
                                ]),
                            
                            Forms\Components\Section::make('Distribusi Ukuran')
                                ->description('Masukkan jumlah untuk setiap ukuran')
                                ->schema([
                                    Forms\Components\Grid::make(5)
                                        ->schema([
                                            Forms\Components\TextInput::make('size_xs')
                                                ->label('XS')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_s')
                                                ->label('S')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_m')
                                                ->label('M')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_l')
                                                ->label('L')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_xl')
                                                ->label('XL')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                        ]),
                                    
                                    Forms\Components\Grid::make(6)
                                        ->schema([
                                            Forms\Components\TextInput::make('size_xxl')
                                                ->label('XXL (+5,000)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_xxxl')
                                                ->label('XXXL/3XL (+10,000)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_4xl')
                                                ->label('4XL (+15,000)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_5xl')
                                                ->label('5XL (+20,000)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_6xl')
                                                ->label('6XL (+25,000)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                            
                                            Forms\Components\TextInput::make('size_7xl')
                                                ->label('7XL (+30,000)')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->reactive()
                                                ->afterStateUpdated(function (callable $get, callable $set) {
                                                    self::calculateBatchMultiItemTotal($get, $set);
                                                }),
                                        ]),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel('Tambah Jenis Pakaian')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->reorderable()
                        ->collapsible()
                        ->columnSpanFull()
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            self::calculateBatchMultiItemTotal($get, $set);
                        })
                        ->deleteAction(
                            fn ($action) => $action->after(function (callable $get, callable $set) {
                                self::calculateBatchMultiItemTotal($get, $set);
                            }),
                        ),
                ]),
            
            Forms\Components\Section::make('Biaya Tambahan & Diskon Grup')
                ->schema([
                    Forms\Components\Repeater::make('batch_additional_fees')
                        ->label('Biaya Tambahan (Untuk Seluruh Grup)')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Jenis Biaya')
                                ->options([
                                    'embroidery' => 'Embroidery/Sulaman',
                                    'printing' => 'Printing/Sablon',
                                    'express' => 'Express Service',
                                    'material_upgrade' => 'Upgrade Material',
                                    'design_complex' => 'Desain Kompleks',
                                    'other' => 'Lainnya',
                                ])
                                ->required(),
                            
                            Forms\Components\TextInput::make('name')
                                ->label('Nama Biaya')
                                ->required()
                                ->placeholder('Contoh: Sablon Logo Sekolah, Express 3 hari, dll'),
                            
                            Forms\Components\TextInput::make('amount')
                                ->label('Jumlah Total')
                                ->numeric()
                                ->required()
                                ->prefix('Rp')
                                ->default(0)
                                ->helperText('Jumlah untuk seluruh grup')
                                ->reactive()
                                ->afterStateUpdated(function (callable $get, callable $set) {
                                    self::calculateBatchMultiItemTotal($get, $set);
                                }),
                            
                            Forms\Components\Textarea::make('notes')
                                ->label('Keterangan')
                                ->rows(1)
                                ->placeholder('Keterangan tambahan...'),
                        ])
                        ->addActionLabel('Tambah Biaya')
                        ->defaultItems(0)
                        ->collapsible()
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            self::calculateBatchMultiItemTotal($get, $set);
                        }),
                    
                    Forms\Components\TextInput::make('discount')
                        ->label('Diskon Grup')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(1000)
                        ->reactive()
                        ->afterStateUpdated(function (callable $get, callable $set) {
                            self::calculateBatchMultiItemTotal($get, $set);
                        }),
                ]),
            
            Forms\Components\Section::make('Ringkasan & Pembayaran')
                ->schema([
                    Forms\Components\Placeholder::make('batch_multi_summary')
                        ->label('Ringkasan Batch Multi-Jenis')
                        ->content(function (callable $get) {
                            $clothingItems = $get('batch_clothing_items') ?? [];
                            $additionalFees = $get('batch_additional_fees') ?? [];
                            $discount = floatval($get('discount') ?? 0);
                            
                            $totalItems = 0;
                            $totalPrice = 0;
                            $totalAdditional = 0;
                            $itemSummaries = [];
                            
                            foreach ($clothingItems as $itemIndex => $item) {
                                $itemName = $item['custom_name'] ?? 
                                          (isset($item['clothing_type_id']) ? ClothingType::find($item['clothing_type_id'])?->name : 'Item ' . ($itemIndex + 1));
                                $basePriceRaw = $item['base_price'] ?? 0;
                                $basePrice = (float) preg_replace('/[^0-9.\-]/', '', (string) $basePriceRaw);
                                
                                $itemQty = 0;
                                $itemTotal = 0;
                                $sizeDist = [];
                                
                                // Hitung per ukuran
                                $sizes = ['xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', '4xl', '5xl', '6xl', '7xl'];
                                foreach ($sizes as $size) {
                                    $qtyRaw = $item["size_{$size}"] ?? 0;
                                    $qty = (int) preg_replace('/\D/', '', (string) $qtyRaw);
                                    if ($qty > 0) {
                                        $sizeUpper = strtoupper($size);
                                        $surcharge = Order::calculateSizeSurcharge($sizeUpper);
                                        $pricePerItem = $basePrice + $surcharge;
                                        $subtotal = $pricePerItem * $qty;
                                        
                                        $itemQty += $qty;
                                        $itemTotal += $subtotal;
                                        $sizeDist[$sizeUpper] = $qty;
                                    }
                                }
                                
                                $totalItems += $itemQty;
                                $totalPrice += $itemTotal;
                                
                                $itemSummaries[] = [
                                    'name' => $itemName,
                                    'quantity' => $itemQty,
                                    'total' => $itemTotal,
                                    'size_distribution' => $sizeDist,
                                ];
                            }
                            
                            foreach ($additionalFees as $fee) {
                                $feeAmountRaw = $fee['amount'] ?? 0;
                                $totalAdditional += (float) preg_replace('/[^0-9.\-]/', '', (string) $feeAmountRaw);
                            }
                            
                            $total = $totalPrice + $totalAdditional - $discount;
                            
                            $html = "
                                <div class='space-y-4 text-sm p-4 bg-gray-50 rounded-lg'>
                                    <div class='grid grid-cols-2 gap-2'>
                                        <div class='text-gray-600'>Total Jenis Pakaian:</div>
                                        <div class='text-right font-semibold'>" . count($clothingItems) . " jenis</div>
                                        
                                        <div class='text-gray-600'>Total Item:</div>
                                        <div class='text-right font-semibold'>{$totalItems} pcs</div>
                                        
                                        <div class='text-gray-600 border-t pt-2 font-bold'>Subtotal Item:</div>
                                        <div class='text-right border-t pt-2 font-bold'>Rp " . number_format($totalPrice, 0, ',', '.') . "</div>
                                        
                                        <div class='text-gray-600'>+ Biaya Tambahan:</div>
                                        <div class='text-right font-semibold'>+ Rp " . number_format($totalAdditional, 0, ',', '.') . "</div>
                                        
                                        <div class='text-gray-600'>- Diskon Grup:</div>
                                        <div class='text-right font-semibold text-red-600'>- Rp " . number_format($discount, 0, ',', '.') . "</div>
                                        
                                        <div class='text-gray-700 border-t pt-2 font-bold text-lg mt-2'>TOTAL ORDER GRUP:</div>
                                        <div class='text-right border-t pt-2 font-bold text-lg text-primary-600'>Rp " . number_format($total, 0, ',', '.') . "</div>
                                    </div>
                            ";
                            
                            // Detail per jenis pakaian
                            if (!empty($itemSummaries)) {
                                $html .= "<div class='mt-4 pt-4 border-t'>";
                                $html .= "<div class='font-medium text-gray-700 mb-2'>Detail per Jenis Pakaian:</div>";
                                $html .= "<div class='space-y-2'>";
                                
                                foreach ($itemSummaries as $summary) {
                                    if ($summary['quantity'] > 0) {
                                        $sizeStr = '';
                                        foreach ($summary['size_distribution'] as $size => $qty) {
                                            $surcharge = Order::calculateSizeSurcharge($size);
                                            $sizeLabel = Order::getSizeLabel($size);
                                            if ($surcharge > 0) {
                                                $sizeStr .= "<span class='px-2 py-0.5 bg-gray-100 rounded text-xs mr-1 mb-1 inline-block'>{$sizeLabel}: {$qty} pcs (+" . number_format($surcharge, 0, ',', '.') . ")</span>";
                                            } else {
                                                $sizeStr .= "<span class='px-2 py-0.5 bg-gray-100 rounded text-xs mr-1 mb-1 inline-block'>{$sizeLabel}: {$qty} pcs</span>";
                                            }
                                        }
                                        
                                        $html .= "
                                            <div class='p-2 bg-white rounded border'>
                                                <div class='font-medium'>{$summary['name']}</div>
                                                <div class='flex justify-between text-xs mt-1'>
                                                    <div>Jumlah: <span class='font-semibold'>{$summary['quantity']} pcs</span></div>
                                                    <div>Subtotal: <span class='font-semibold text-primary-600'>Rp " . number_format($summary['total'], 0, ',', '.') . "</span></div>
                                                </div>
                                                <div class='flex flex-wrap gap-1 mt-1'>
                                                    {$sizeStr}
                                                </div>
                                            </div>
                                        ";
                                    }
                                }
                                
                                $html .= "</div></div>";
                            }
                            
                            $html .= "</div>";
                            
                            return new HtmlString($html);
                        })
                        ->columnSpanFull()
                        ->reactive(),
                    
                    Forms\Components\Select::make('payment_status')
                        ->label('Status Pembayaran Grup')
                        ->options([
                            'unpaid' => 'Belum Bayar',
                            'dp' => 'DP',
                            'partial' => 'Cicilan',
                            'paid' => 'Lunas',
                        ])
                        ->default('unpaid')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if ($state === 'paid') {
                                $set('dp_paid', $get('total_price'));
                            } elseif ($state === 'unpaid') {
                                $set('dp_paid', 0);
                            }
                        }),
                    
                    Forms\Components\TextInput::make('dp_paid')
                        ->label('DP Grup Dibayar')
                        ->prefix('Rp')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(1000)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            $totalPrice = floatval($get('total_price') ?? 0);
                            $dpPaid = floatval($state);
                            if ($dpPaid >= $totalPrice) {
                                $set('payment_status', 'paid');
                            } elseif ($dpPaid > 0) {
                                $set('payment_status', 'dp');
                            } else {
                                $set('payment_status', 'unpaid');
                            }
                        }),
                    
                    Forms\Components\Select::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->options([
                            'cash' => 'Cash',
                            'transfer' => 'Transfer',
                            'qris' => 'QRIS',
                            'other' => 'Lainnya',
                        ])
                        ->default('cash'),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('Status & Jadwal')
                ->schema([
                    Forms\Components\Select::make('order_status')
                        ->label('Status Order')
                        ->options([
                            'pending' => 'Pending',
                            'design_review' => 'Review Desain',
                            'measurement' => 'Pengukuran',
                            'cutting' => 'Pemotongan',
                            'sewing' => 'Penjahitan',
                            'finishing' => 'Finishing',
                            'ready' => 'Siap Diambil',
                            'completed' => 'Selesai',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->default('pending')
                        ->required(),
                    
                    Forms\Components\Select::make('priority')
                        ->label('Prioritas')
                        ->options([
                            'low' => 'Rendah',
                            'normal' => 'Normal',
                            'high' => 'Tinggi',
                            'urgent' => 'Mendesak',
                        ])
                        ->default('normal')
                        ->required(),
                    
                    Forms\Components\DatePicker::make('order_date')
                        ->label('Tanggal Order')
                        ->default(now())
                        ->required()
                        ->maxDate(now()->addDays()),
                    
                    Forms\Components\DatePicker::make('due_date')
                        ->label('Deadline/Tanggal Jadi')
                        ->default(now()->addDays(10))
                        ->required()
                        ->minDate(now())
                        ->helperText('Estimasi tanggal selesai untuk seluruh batch'),
                    
                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Catatan Internal')
                        ->rows(2)
                        ->maxLength(500)
                        ->placeholder('Catatan untuk batch order ini...'),
                ])
                ->columns(2),
            
            // Hidden fields untuk batch multi-item
            Forms\Components\Hidden::make('total_price')
                ->default(0),
            
            Forms\Components\Hidden::make('is_batch')
                ->default(true),
            
            Forms\Components\Hidden::make('is_batch_multi_item')
                ->default(true),
            
            Forms\Components\Hidden::make('quantity')
                ->default(0)
                ->dehydrateStateUsing(function (callable $get) {
                    $clothingItems = $get('batch_clothing_items') ?? [];
                    $totalQuantity = 0;
                    
                    foreach ($clothingItems as $item) {
                        $sizes = ['xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', '4xl', '5xl', '6xl', '7xl'];
                        foreach ($sizes as $size) {
                            $totalQuantity += (int) ($item["size_{$size}"] ?? 0);
                        }
                    }
                    
                    return $totalQuantity;
                }),
            
            Forms\Components\Hidden::make('batch_items_data')
                ->default('[]'),
            
            Forms\Components\Hidden::make('batch_additional_fees_data')
                ->default('[]')
                ->dehydrateStateUsing(function (callable $get) {
                    $additionalFees = $get('batch_additional_fees') ?? [];
                    return json_encode($additionalFees);
                }),
            
            Forms\Components\Hidden::make('additional_fees_total')
                ->default(0)
                ->dehydrateStateUsing(function (callable $get) {
                    $additionalFees = $get('batch_additional_fees') ?? [];
                    $total = 0;
                    
                    foreach ($additionalFees as $fee) {
                        $total += floatval($fee['amount'] ?? 0);
                    }
                    
                    return $total;
                }),
        ];
    }

    /**
     * Hitung total untuk single order
     */
    private static function calculateSingleTotal(callable $get, callable $set): void
    {
        $basePrice = floatval($get('base_price') ?? 0);
        $sizeSurcharge = floatval($get('size_surcharge') ?? 0);
        $quantity = intval($get('single_quantity') ?? 1);
        $discount = floatval($get('discount') ?? 0);
        $additionalFees = $get('additional_fees_items') ?? [];
        
        $additionalTotal = 0;
        foreach ($additionalFees as $fee) {
            if (isset($fee['amount'])) {
                $additionalTotal += floatval($fee['amount']);
            }
        }
        
        $sizePrice = $basePrice + $sizeSurcharge;
        $subtotal = $sizePrice * $quantity;
        $total = $subtotal + $additionalTotal - $discount;
        
        $set('total_price', $total);
        $set('additional_fees_total', $additionalTotal);
        $set('size_price', $sizePrice);
    }

    /**
     * Hitung item batch single
     */
    private static function calculateBatchItem(callable $get, callable $set): void
    {
    $batchItems = $get('batch_items') ?? [];
    $basePriceRaw = $get('base_price') ?? 0;
    $basePrice = (float) preg_replace('/[^0-9.\-]/', '', (string) $basePriceRaw);
        
        $updatedItems = [];
        foreach ($batchItems as $item) {
            $size = $item['size'] ?? 'M';
            $quantityRaw = $item['batch_quantity'] ?? 1;
            $quantity = (int) preg_replace('/\D/', '', (string) $quantityRaw);
            
            $surcharge = Order::calculateSizeSurcharge($size);
            $pricePerItem = $basePrice + (float) $surcharge;
            $subtotal = $pricePerItem * $quantity;
            
            $updatedItem = $item;
            $updatedItem['surcharge'] = $surcharge;
            $updatedItem['price_per_item'] = $pricePerItem;
            $updatedItem['subtotal'] = $subtotal;
            
            $updatedItems[] = $updatedItem;
        }
        
        $set('batch_items', $updatedItems);
        self::calculateBatchTotal($get, $set);
    }

    /**
     * Hitung total batch single
     */
    private static function calculateBatchTotal(callable $get, callable $set): void
    {
    $batchItems = $get('batch_items') ?? [];
    $additionalFees = $get('batch_additional_fees') ?? [];
    $basePriceRaw = $get('base_price') ?? 0;
    $basePrice = (float) preg_replace('/[^0-9.\-]/', '', (string) $basePriceRaw);
    $discountRaw = $get('discount') ?? 0;
    $discount = (float) preg_replace('/[^0-9.\-]/', '', (string) $discountRaw);
        
        $totalItems = 0;
        $totalSurcharge = 0;
        $totalAdditional = 0;
        
        foreach ($batchItems as $item) {
            $quantityRaw = $item['batch_quantity'] ?? 0;
            $quantity = (int) preg_replace('/\D/', '', (string) $quantityRaw);
            $size = $item['size'] ?? 'M';
            $surcharge = Order::calculateSizeSurcharge($size);
            
            $totalItems += $quantity;
            $totalSurcharge += ((float) $surcharge) * $quantity;
        }
        
        foreach ($additionalFees as $fee) {
            $feeAmountRaw = $fee['amount'] ?? 0;
            $totalAdditional += (float) preg_replace('/[^0-9.\-]/', '', (string) $feeAmountRaw);
        }
        
        $subtotal = ($basePrice * $totalItems) + $totalSurcharge;
        $total = $subtotal + $totalAdditional - $discount;
        
        $set('total_price', $total);
        $set('quantity', $totalItems);
        $set('additional_fees_total', $totalAdditional);
    }

    /**
     * Hitung total batch multi-item
     */
    private static function calculateBatchMultiItemTotal(callable $get, callable $set): void
    {
    $clothingItems = $get('batch_clothing_items') ?? [];
    $additionalFees = $get('batch_additional_fees') ?? [];
    $discountRaw = $get('discount') ?? 0;
    $discount = (float) preg_replace('/[^0-9.\-]/', '', (string) $discountRaw);
        
        $totalPrice = 0;
        $totalQuantity = 0;
        $totalAdditional = 0;
        
        foreach ($clothingItems as $item) {
            $basePriceRaw = $item['base_price'] ?? 0;
            $basePrice = (float) preg_replace('/[^0-9.\-]/', '', (string) $basePriceRaw);
            
            // Hitung per ukuran
            $sizes = ['xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', '4xl', '5xl', '6xl', '7xl'];
            foreach ($sizes as $size) {
                $qtyRaw = $item["size_{$size}"] ?? 0;
                $quantity = (int) preg_replace('/\D/', '', (string) $qtyRaw);
                if ($quantity > 0) {
                    $sizeUpper = strtoupper($size);
                    $surcharge = Order::calculateSizeSurcharge($sizeUpper);
                    $pricePerItem = $basePrice + (float) $surcharge;
                    $subtotal = $pricePerItem * $quantity;
                    
                    $totalPrice += $subtotal;
                    $totalQuantity += $quantity;
                }
            }
        }
        
        foreach ($additionalFees as $fee) {
            $feeAmountRaw = $fee['amount'] ?? 0;
            $totalAdditional += (float) preg_replace('/[^0-9.\-]/', '', (string) $feeAmountRaw);
        }
        
        $total = $totalPrice + $totalAdditional - $discount;
        
        $set('total_price', $total);
        $set('quantity', $totalQuantity);
        $set('additional_fees_total', $totalAdditional);
    }

    /**
     * Dapatkan label ukuran yang user-friendly
     */
    public static function getSizeLabelForm(string $size): string
    {
        $labels = [
            'XS' => 'XS',
            'S' => 'S',
            'M' => 'M',
            'L' => 'L',
            'XL' => 'XL',
            'XXL' => 'XXL',
            'XXXL' => 'XXXL/3XL',
            '4XL' => '4XL',
            '5XL' => '5XL',
            '6XL' => '6XL',
            '7XL' => '7XL',
        ];
        
        return $labels[$size] ?? $size;
    }

    /**
     * INFOLIST untuk View Page
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Order')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Nomor Order')
                                    ->badge()
                                    ->color('primary')
                                    ->size(TextEntry\TextEntrySize::Large),
                                    
                                TextEntry::make('order_date')
                                    ->label('Tanggal Order')
                                    ->date('d/m/Y'),
                                    
                                TextEntry::make('due_date')
                                    ->label('Deadline')
                                    ->date('d/m/Y')
                                    ->color(fn ($state, $record = null) => ($record?->is_overdue ?? false) ? 'danger' : 'success')
                                    ->description(fn ($state, $record = null) => ($record?->is_overdue ?? false) ? 'TERLAMBAT' : null),
                            ]),
                            
                        TextEntry::make('customer.name')
                            ->label('Pelanggan')
                            ->url(fn ($record = null) => $record ? CustomerResource::getUrl('view', ['record' => $record->customer_id]) : null)
                            ->badge()
                            ->color('gray'),
                            
                        TextEntry::make('batch_type_label')
                            ->label('Tipe Order')
                            ->badge()
                            ->color(fn ($state, $record = null) => match($record?->batch_type_label ?? 'Single Order') {
                                'Batch Multi-Jenis' => 'purple',
                                'Batch Single Jenis' => 'primary',
                                default => 'success',
                            }),
                            
                        TextEntry::make('clothing_type_display')
                            ->label('Jenis Pakaian')
                            ->badge()
                            ->color('info'),
                    ])
                    ->collapsible(false),
                    
                Section::make('Detail Produk')
                    ->schema(function ($record) {
                        if (!$record) {
                            return [
                                TextEntry::make('no_data')
                                    ->label('Data tidak tersedia')
                                    ->columnSpanFull(),
                            ];
                        }
                        
                        $schema = [];
                        
                        if ($record->is_batch && $record->is_batch_multi_item) {
                            // Untuk batch multi-item
                            $schema[] = Grid::make(2)
                                ->schema([
                                    TextEntry::make('quantity')
                                        ->label('Total Jumlah')
                                        ->formatStateUsing(fn ($state) => ($state ?? 0) . ' pcs'),
                                        
                                    TextEntry::make('group_name')
                                        ->label('Nama Grup'),
                                ]);
                            
                            $schema[] = ViewEntry::make('batch_multi_items_details')
                                ->label('Detail Jenis Pakaian')
                                ->view('filament.infolists.components.order-batch-multi-items')
                                ->columnSpanFull();
                        } elseif ($record->is_batch) {
                            // Untuk batch single item
                            $schema[] = Grid::make(4)
                                ->schema([
                                    TextEntry::make('quantity')
                                        ->label('Jumlah')
                                        ->formatStateUsing(fn ($state) => ($state ?? 0) . ' pcs'),
                                        
                                    TextEntry::make('group_name')
                                        ->label('Nama Grup'),
                                        
                                    TextEntry::make('color')
                                        ->label('Warna'),
                                        
                                    TextEntry::make('material_needed')
                                        ->label('Kain (m)')
                                        ->formatStateUsing(fn ($state) => ($state ?? 0) . ' m'),
                                ]);
                        } else {
                            // Untuk single order
                            $schema[] = Grid::make(4)
                                ->schema([
                                    TextEntry::make('size')
                                        ->label('Ukuran')
                                        ->badge()
                                        ->color(fn ($state) => Order::getSizeColor($state ?? 'M')),
                                        
                                    TextEntry::make('color')
                                        ->label('Warna'),
                                        
                                    TextEntry::make('quantity')
                                        ->label('Jumlah')
                                        ->formatStateUsing(fn ($state) => ($state ?? 0) . ' pcs'),
                                        
                                    TextEntry::make('material_needed')
                                        ->label('Kain (m)')
                                        ->formatStateUsing(fn ($state) => ($state ?? 0) . ' m'),
                                ]);
                        }
                        
                        $schema[] = TextEntry::make('measurement_notes')
                            ->label('Catatan Ukuran')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan')
                            ->html();
                        
                        return $schema;
                    })
                    ->collapsible(),
                    
                Section::make('Status')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('order_status')
                                    ->label('Status Order')
                                    ->icon(fn ($state) => match($state) {
                                        'completed' => 'heroicon-o-check-circle',
                                        'cancelled' => 'heroicon-o-x-circle',
                                        default => 'heroicon-o-clock',
                                    })
                                    ->color(fn ($state, $record = null) => $record?->order_status_color ?? 'gray'),
                                    
                                IconEntry::make('payment_status')
                                    ->label('Status Pembayaran')
                                    ->icon(fn ($state) => match($state) {
                                        'paid' => 'heroicon-o-banknotes',
                                        'dp' => 'heroicon-o-currency-dollar',
                                        default => 'heroicon-o-credit-card',
                                    })
                                    ->color(fn ($state, $record = null) => $record?->payment_status_color ?? 'gray'),
                            ]),
                    ])
                    ->collapsible(),
                    
                Section::make('Pembayaran')
                    ->schema([
                        Split::make([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('total_price')
                                        ->label('Total Harga')
                                        ->money('IDR')
                                        ->weight('bold'),
                                        
                                    TextEntry::make('net_paid')
                                        ->label('Sudah Dibayar')
                                        ->money('IDR')
                                        ->color('success'),
                                ]),
                                
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('dp_paid')
                                        ->label('DP/Cicilan Dibayar')
                                        ->money('IDR')
                                        ->color('warning'),
                                        
                                    TextEntry::make('remaining_payment')
                                        ->label('Sisa Pembayaran')
                                        ->money('IDR')
                                        ->color(fn ($state) => ($state ?? 0) > 0 ? 'danger' : 'success'),
                                ]),
                        ]),
                        
                        TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match($state) {
                                'cash' => 'CASH',
                                'transfer' => 'TRANSFER',
                                'qris' => 'QRIS',
                                'other' => 'LAINNYA',
                                default => strtoupper($state ?? ''),
                            })
                            ->color(fn ($state) => match($state) {
                                'cash' => 'success',
                                'transfer' => 'info',
                                'qris' => 'primary',
                                default => 'gray',
                            }),
                    ])
                    ->collapsible(),
                    
                Section::make('Biaya & Diskusi')
                    ->schema([
                        ViewEntry::make('price_breakdown')
                            ->label('Rincian Harga')
                            ->view('filament.infolists.components.order-price-breakdown'),
                    ])
                    ->collapsible(),
                    
                Section::make('Catatan')
                    ->schema([
                        TextEntry::make('customer_notes')
                            ->label('Catatan Customer')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan')
                            ->html(),
                            
                        TextEntry::make('internal_notes')
                            ->label('Catatan Internal')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan')
                            ->html(),
                            
                        TextEntry::make('payment_notes')
                            ->label('Catatan Pembayaran')
                            ->columnSpanFull()
                            ->placeholder('Tidak ada catatan')
                            ->html(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->description(fn ($record = null) => $record?->customer?->name ?? '-'),
                
                Tables\Columns\IconColumn::make('is_batch')
                    ->label('Batch')
                    ->boolean()
                    ->trueIcon('heroicon-o-user-group')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->tooltip(fn ($record = null) => ($record?->is_batch ?? false) ? 'Batch Order' : 'Single Order'),
                
                Tables\Columns\IconColumn::make('is_batch_multi_item')
                    ->label('Multi')
                    ->boolean()
                    ->trueIcon('heroicon-o-cube')
                    ->falseIcon('heroicon-o-cube')
                    ->trueColor('purple')
                    ->falseColor('gray')
                    ->tooltip(fn ($record = null) => ($record?->is_batch_multi_item ?? false) ? 'Multi Jenis' : 'Single Jenis')
                    ->hidden(fn ($record = null) => !($record?->is_batch ?? false)),
                
                Tables\Columns\TextColumn::make('group_name')
                    ->label('Nama Grup')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->hidden(fn ($record = null) => !($record?->is_batch ?? false)),
                
                Tables\Columns\TextColumn::make('clothing_type_display')
                    ->label('Jenis Pakaian')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record = null) => $record?->clothing_type_display ?? null),
                
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn ($state) => is_numeric($state) ? $state . ' pcs' : '0 pcs')
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable()
                    ->color('primary')
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('net_paid')
                    ->label('Dibayar')
                    ->money('IDR')
                    ->color('success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('remaining_payment')
                    ->label('Sisa')
                    ->money('IDR')
                    ->color(fn ($state) => ($state ?? 0) > 0 ? 'danger' : 'success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'paid' => 'LUNAS',
                        'dp' => 'DP',
                        'partial' => 'CICILAN',
                        default => 'BELUM',
                    })
                    ->color(fn ($state) => match($state) {
                        'paid' => 'success',
                        'dp' => 'warning',
                        'partial' => 'info',
                        default => 'danger',
                    }),
                
                Tables\Columns\TextColumn::make('order_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'pending' => 'PENDING',
                        'design_review' => 'REVIEW',
                        'measurement' => 'UKUR',
                        'cutting' => 'POTONG',
                        'sewing' => 'JAHIT',
                        'finishing' => 'FINISHING',
                        'ready' => 'SIAP',
                        'completed' => 'SELESAI',
                        'cancelled' => 'BATAL',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        'completed' => 'success',
                        'ready' => 'info',
                        'cancelled' => 'danger',
                        'pending' => 'secondary',
                        default => 'warning'
                    }),
                
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Deadline')
                    ->date('d M')
                    ->sortable()
                    ->color(fn ($record = null) => ($record?->is_overdue ?? false) ? 'danger' : null)
                    ->description(fn ($record = null) => ($record?->is_overdue ?? false) ? 'TERLAMBAT' : null),
            ])
            ->defaultSort('order_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('batch_type')
                    ->label('Tipe Batch')
                    ->options([
                        'single_batch' => 'Batch Single Jenis',
                        'multi_batch' => 'Batch Multi Jenis',
                        'single_order' => 'Single Order',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        
                        return match($data['value']) {
                            'single_batch' => $query->where('is_batch', true)->where('is_batch_multi_item', false),
                            'multi_batch' => $query->where('is_batch', true)->where('is_batch_multi_item', true),
                            'single_order' => $query->where('is_batch', false),
                            default => $query,
                        };
                    }),
                
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Status Pembayaran')
                    ->options([
                        'unpaid' => 'Belum Bayar',
                        'dp' => 'DP',
                        'paid' => 'Lunas',
                        'partial' => 'Cicilan',
                    ]),
                
                Tables\Filters\SelectFilter::make('order_status')
                    ->label('Status Order')
                    ->options([
                        'pending' => 'Pending',
                        'design_review' => 'Review Desain',
                        'measurement' => 'Pengukuran',
                        'cutting' => 'Pemotongan',
                        'sewing' => 'Penjahitan',
                        'finishing' => 'Finishing',
                        'ready' => 'Siap Diambil',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ]),
                
                Tables\Filters\Filter::make('overdue')
                    ->label('Terlambat')
                    ->query(fn (Builder $query) => $query->where('due_date', '<', now())
                        ->whereNotIn('order_status', ['completed', 'cancelled'])),
                
                Tables\Filters\Filter::make('pending_payment')
                    ->label('Belum Lunas')
                    ->query(fn (Builder $query) => $query->whereIn('payment_status', ['unpaid', 'dp', 'partial'])),
                
                Tables\Filters\TrashedFilter::make()
                    ->label('Terhapus'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Ekspor Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Ekspor data ke Excel')
                    ->exports([
                        ExcelExport::make()
                            ->withColumns([
                                Column::make('order_number')->heading('NO ORDER'),
                                Column::make('order_date')->formatStateUsing(fn ($state) => $state?->format('d/m/Y'))->heading('TGL ORDER'),
                                Column::make('due_date')->formatStateUsing(fn ($state) => $state?->format('d/m/Y'))->heading('DEADLINE'),
                                Column::make('customer.name')->heading('PELANGGAN'),
                                Column::make('customer.phone')->heading('TELPON'),
                                Column::make('is_batch')->formatStateUsing(fn ($state) => $state ? 'BATCH' : 'SINGLE')->heading('TIPE ORDER'),
                                Column::make('is_batch_multi_item')->formatStateUsing(fn ($state) => $state ? 'MULTI JENIS' : 'SINGLE JENIS')->heading('JENIS BATCH'),
                                Column::make('group_name')->heading('NAMA GRUP'),
                                Column::make('clothing_type_display')->heading('JENIS PAKAIAN'),
                                Column::make('quantity')->heading('JUMLAH'),
                                Column::make('total_price')->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))->heading('TOTAL HARGA'),
                                Column::make('net_paid')->formatStateUsing(fn ($state) => $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : '-')->heading('SUDAH DIBAYAR'),
                                Column::make('remaining_payment')->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))->heading('SISA BAYAR'),
                                Column::make('payment_status')->formatStateUsing(fn ($state) => match($state) {
                                    'paid' => 'LUNAS',
                                    'dp' => 'DP',
                                    'partial' => 'CICILAN',
                                    'unpaid' => 'BELUM BAYAR',
                                    default => $state,
                                })->heading('STATUS BAYAR'),
                                Column::make('order_status')->formatStateUsing(fn ($state) => match($state) {
                                    'pending' => 'PENDING',
                                    'design_review' => 'REVIEW DESAIN',
                                    'measurement' => 'PENGUKURAN',
                                    'cutting' => 'PEMOTONGAN',
                                    'sewing' => 'PENJAHITAN',
                                    'finishing' => 'FINISHING',
                                    'ready' => 'SIAP DIAMBIL',
                                    'completed' => 'SELESAI',
                                    'cancelled' => 'DIBATALKAN',
                                    default => $state,
                                })->heading('STATUS ORDER'),
                                Column::make('priority')->formatStateUsing(fn ($state) => match($state) {
                                    'low' => 'RENDAH',
                                    'normal' => 'NORMAL',
                                    'high' => 'TINGGI',
                                    'urgent' => 'MENDESAK',
                                    default => $state,
                                })->heading('PRIORITAS'),
                                Column::make('payment_method')->formatStateUsing(fn ($state) => match($state) {
                                    'cash' => 'CASH',
                                    'transfer' => 'TRANSFER',
                                    'qris' => 'QRIS',
                                    'other' => 'LAINNYA',
                                    default => $state,
                                })->heading('METODE BAYAR'),
                                Column::make('customer_notes')->heading('CATATAN CUSTOMER'),
                                Column::make('internal_notes')->heading('CATATAN INTERNAL'),
                                Column::make('measurement_notes')->heading('CATATAN UKURAN'),
                                Column::make('created_at')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i'))->heading('DIBUAT PADA'),
                                Column::make('updated_at')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i'))->heading('DIPERBARUI PADA'),
                            ])
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                            ->withFilename('orders-export-' . date('Y-m-d-H-i-s'))
                            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'batchClothingItems.clothingType']))
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->tooltip('Lihat Detail'),
                
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-o-pencil')
                    ->color('gray')
                    ->tooltip('Edit'),
                
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->tooltip('Hapus'),
                
                Tables\Actions\Action::make('view_payments')
                    ->label('')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record]) . '?activeRelationManager=0')
                    ->tooltip('Lihat Pembayaran'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Hapus'),
                
                Tables\Actions\ForceDeleteBulkAction::make()
                    ->label('Hapus Permanen'),
                
                Tables\Actions\RestoreBulkAction::make()
                    ->label('Pulihkan'),
                
                ExportBulkAction::make()
                    ->label('Ekspor Terpilih')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename('orders-selected-' . date('Y-m-d-H-i-s'))
                            ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                    ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Buat Order Baru'),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\BatchClothingItemsRelationManager::class,
            \App\Filament\Resources\CostCalculationResource\RelationManagers\CostCalculationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
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
        try {
            $count = Order::query()
                ->whereNotIn('order_status', ['completed', 'cancelled'])
                ->count();
                
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        try {
            $count = Order::query()
                ->whereNotIn('order_status', ['completed', 'cancelled'])
                ->count();
                
            return match(true) {
                $count > 10 => 'danger',
                $count > 5 => 'warning',
                default => 'primary',
            };
        } catch (\Exception $e) {
            return 'primary';
        }
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Order Aktif (Belum Selesai)';
    }
}