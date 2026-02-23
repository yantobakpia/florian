{{-- resources/views/filament/infolists/components/order-multi-items.blade.php --}}
@php
    $record = $getRecord();
    $items = $record->clothingItems;
@endphp

<div class="space-y-2">
    @forelse($items as $item)
        <div class="p-3 bg-gray-50 rounded-lg">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ $item->item_name }}</div>
                    <div class="grid grid-cols-4 gap-4 text-sm text-gray-600 mt-1">
                        <div>
                            <span class="font-medium">Qty:</span> 
                            <span class="font-semibold">{{ $item->quantity }} pcs</span>
                        </div>
                        <div>
                            <span class="font-medium">Size:</span> 
                            <span class="px-2 py-0.5 bg-gray-200 rounded text-xs font-semibold">{{ $item->size }}</span>
                            @if($item->size_surcharge > 0)
                                <span class="text-xs text-orange-600">(+{{ number_format($item->size_surcharge, 0, ',', '.') }})</span>
                            @endif
                        </div>
                        <div>
                            <span class="font-medium">Warna:</span> 
                            <span>{{ $item->color ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="font-medium">Subtotal:</span> 
                            <span class="font-semibold text-primary-600">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @if($item->notes)
                        <div class="mt-2 text-sm text-gray-500">
                            <span class="font-medium">Catatan:</span> {{ $item->notes }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-4 text-gray-500">
            Tidak ada item
        </div>
    @endforelse
    
    <div class="pt-2 border-t">
        <div class="flex justify-between items-center">
            <div class="font-medium text-gray-700">
                Total {{ $items->count() }} jenis, {{ $items->sum('quantity') }} pcs
            </div>
            <div class="font-bold text-lg text-primary-600">
                Subtotal Item: Rp {{ number_format($items->sum('subtotal'), 0, ',', '.') }}
            </div>
        </div>
    </div>
</div>