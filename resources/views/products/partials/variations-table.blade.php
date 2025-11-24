{{-- $variations is an Eloquent Collection confirmed by dd() --}}
@if ($variations->isNotEmpty())
    <h4 class="text-xl font-bold text-gray-700 mb-4">Variation Details ({{ $variations->count() }})</h4>
    <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white shadow-inner">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-gray-700 font-semibold">Variation SKU</th>
                <th class="px-4 py-3 text-left text-gray-700 font-semibold">Attributes</th>
                <th class="px-4 py-3 text-left text-gray-700 font-semibold">Price</th>
                <th class="px-4 py-3 text-center text-gray-700 font-semibold">Total Stock</th>
                <th class="px-4 py-3 text-left text-gray-700 font-semibold">Inventory Action</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @foreach ($variations as $variation)
                <tr class="hover:bg-white transition duration-100">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $variation->sku }}</td>
                    <td class="px-4 py-3">
                            <span class="text-xs text-gray-600 space-x-2">
                                {{
                                    // $variation->options is a Collection, so map/implode works.
                                    $variation->options->map(function($option) {
                                        return $option->attribute->name . ': ' . $option->value;
                                    })->implode(' / ')
                                }}
                            </span>
                    </td>
                    <td class="px-4 py-3 text-gray-800 font-medium">{{ $variation->product->currency }} {{ number_format($variation->price, 2) }}</td>
                    <td class="px-4 py-3 font-extrabold text-center text-indigo-600">
                        {{ $variation->inventories->sum('quantity') }}
                    </td>
                    <td class="px-4 py-3">
                        <button
                            class="toggle-inventory text-xs text-indigo-600 hover:text-indigo-800 font-bold bg-indigo-100 py-1 px-2 rounded-full transition duration-150"
                            data-target="#inventory-{{ $variation->id }}"
                            data-count="{{ $variation->inventories->count() }}"
                        >
                            View Inventory ({{ $variation->inventories->count() }})
                        </button>
                    </td>
                </tr>

                <!-- Nested Row for Warehouse Inventory -->
                <tr id="inventory-{{ $variation->id }}" class="hidden bg-gray-100 border-b">
                    <td colspan="5" class="px-6 py-4">
                        @include('products.partials.inventory-list', ['inventories' => $variation->inventories])
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-gray-500 italic p-4 border border-gray-200 rounded-lg bg-white">No variations found for this product.</p>
@endif

<script>
    // JavaScript for Inventory Toggle (needs to be defined once)
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.toggle-inventory').forEach(button => {
            button.addEventListener('click', (e) => {
                const buttonElement = e.currentTarget;
                const targetId = buttonElement.getAttribute('data-target');
                const targetRow = document.querySelector(targetId);
                const count = buttonElement.getAttribute('data-count');

                targetRow.classList.toggle('hidden');

                buttonElement.textContent = targetRow.classList.contains('hidden')
                    ? `View Inventory (${count})`
                    : `Hide Inventory`;
            });
        });
    });
</script>
