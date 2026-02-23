{{-- resources/views/filament/infolists/components/order-batch-multi-items.blade.php --}}
@php
    $record = $getRecord();
    $items = $record->batchClothingItems;
    $batchSummary = $record->getBatchMultiItemSummary();
@endphp

<div class="space-y-4">
    @if(empty($batchSummary))
        <div class="text-center py-4 text-gray-500">
            Tidak ada data jenis pakaian
        </div>
    @else
        @foreach($batchSummary as $item)
            <div class="p-4 bg-gray-50 rounded-lg">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="font-medium text-gray-900">{{ $item['name'] }}</div>
                        
                        @if($item['color'])
                            <div class="text-sm text-gray-600 mt-1">
                                <span class="font-medium">Warna:</span> {{ $item['color'] }}
                            </div>
                        @endif
                        
                        <div class="grid grid-cols-3 gap-4 text-sm text-gray-600 mt-2">
                            <div>
                                <span class="font-medium">Qty Total:</span> 
                                <span class="font-semibold">{{ $item['total_quantity'] }} pcs</span>
                            </div>
                            <div>
                                <span class="font-medium">Harga Dasar:</span> 
                                <span class="font-semibold">Rp {{ number_format($item['base_price'], 0, ',', '.') }}</span>
                            </div>
                            <div>
                                <span class="font-medium">Subtotal:</span> 
                                <span class="font-semibold text-primary-600">Rp {{ number_format($item['total_price'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                        
                        @if(!empty($item['size_distribution']))
                            <div class="mt-3">
                                <div class="font-medium text-gray-700 text-sm mb-1">Distribusi Ukuran:</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($item['size_distribution'] as $size => $quantity)
                                        @if($quantity > 0)
                                            @php
                                                $surcharge = \App\Models\Order::calculateSizeSurcharge($size);
                                                $sizeLabel = \App\Models\Order::getSizeLabel($size);
                                            @endphp
                                            <div class="px-2 py-1 bg-white rounded border text-xs">
                                                <span class="font-medium">{{ $sizeLabel }}:</span> 
                                                <span class="font-semibold">{{ $quantity }} pcs</span>
                                                @if($surcharge > 0)
                                                    <span class="text-orange-600 text-xs">(+{{ number_format($surcharge, 0, ',', '.') }})</span>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        
        <div class="pt-4 border-t">
            <div class="flex justify-between items-center">
                <div class="font-medium text-gray-700">
                    Total {{ count($batchSummary) }} jenis, {{ $record->quantity }} pcs
                </div>
                <div class="font-bold text-lg text-primary-600">
                    Total Item: Rp {{ number_format($items->sum('total_price'), 0, ',', '.') }}
                </div>
            </div>
        </div>
    @endif
</div>