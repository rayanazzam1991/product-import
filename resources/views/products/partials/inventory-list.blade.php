{{-- $inventories is an Eloquent Collection confirmed by dd() --}}
@if ($inventories->isNotEmpty())
    <div class="ml-4 p-4 bg-white border border-dashed border-indigo-300 rounded-xl">
        <h5 class="text-base font-semibold text-indigo-700 mb-3 border-b border-indigo-100 pb-1">Stock Levels by Warehouse</h5>
        <ul class="space-y-2">
            @foreach ($inventories as $inventory)
                <li class="flex justify-between items-center text-sm text-gray-800 bg-indigo-50 p-3 rounded-lg shadow-sm">
                    <div class="flex flex-col">
                        <span class="font-bold text-indigo-800">
                            {{ $inventory->warehouse->name }}
                        </span>
                        <span class="text-xs text-gray-600 italic">
                            Location: {{ $inventory->warehouse->location ?? 'N/A' }}
                        </span>
                    </div>

                    <span class="px-3 py-1 bg-green-200 text-green-900 rounded-full font-extrabold text-base border border-green-300 min-w-[80px] text-center">
                        {{ $inventory->quantity }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@else
    <p class="text-gray-500 italic ml-4 p-2">This variation has no recorded stock in any warehouse.</p>
@endif
