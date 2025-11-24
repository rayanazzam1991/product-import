@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 sm:px-8 py-8">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-8 border-b-4 border-indigo-500 pb-2">
            Product Inventory Dashboard 📦
        </h1>

        <div class="bg-white rounded-xl shadow-2xl overflow-hidden">
            <table class="min-w-full leading-normal">
                <thead class="bg-indigo-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">
                        Product Details
                    </th>
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">
                        Base Price
                    </th>
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">
                        Variations & Inventory
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach ($products as $product)
                    <tr class="hover:bg-indigo-50 transition duration-150" data-product-id="{{ $product->id }}">
                        <td class="px-6 py-4 text-sm">
                            <p class="text-gray-900 font-bold">{{ $product->name }}</p>
                            <p class="text-gray-500 text-xs">SKU: {{ $product->sku }}</p>
                            <p class="text-gray-400 text-xs">ID: {{ $product->id }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @php
                                $statusClass = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'draft' => 'bg-yellow-100 text-yellow-800',
                                    'archived' => 'bg-gray-100 text-gray-800',
                                ][$product->status] ?? 'bg-blue-100 text-blue-800';
                            @endphp
                            <span class="inline-block px-3 py-1 text-xs font-semibold leading-none {{ $statusClass }} rounded-full">
                                {{ ucfirst($product->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <p class="text-gray-900 font-medium">{{ $product->currency }} {{ number_format($product->price, 2) }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <button
                                class="toggle-variations bg-indigo-500 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-xs shadow-md transition duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50"
                                data-target="#variations-{{ $product->id }}"
                            >
                                Show {{ $product->productVariations->count() }} Variations
                            </button>
                        </td>
                    </tr>

                    <!-- Nested Row for Variations -->
                    <tr id="variations-{{ $product->id }}" class="hidden bg-gray-50">
                        <td colspan="4" class="p-6">
                            @include('products.partials.variations-table', ['variations' => $product->productVariations])
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Simple JavaScript to toggle the nested variations table visibility
        document.querySelectorAll('.toggle-variations').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const targetRow = document.querySelector(targetId);
                const count = button.textContent.match(/\d+/)[0]; // Extract the variation count

                if (targetRow.classList.contains('hidden')) {
                    targetRow.classList.remove('hidden');
                    button.textContent = `Hide ${count} Variations`;
                    button.classList.remove('bg-indigo-500');
                    button.classList.add('bg-red-500');
                } else {
                    targetRow.classList.add('hidden');
                    button.textContent = `Show ${count} Variations`;
                    button.classList.add('bg-indigo-500');
                    button.classList.remove('bg-red-500');
                }
            });
        });
    </script>
@endpush
