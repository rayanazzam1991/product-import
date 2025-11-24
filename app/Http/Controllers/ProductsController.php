<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class ProductsController extends Controller
{
    public function index(): Factory|View
    {
        $products = Product::with([
            'productVariations',
            'productVariations.options',
            'productVariations.options.attribute', // To get the attribute name
            'productVariations.inventories',
            'productVariations.inventories.warehouse',
        ])->get();

        return view('products.index', compact('products'));
    }
}
