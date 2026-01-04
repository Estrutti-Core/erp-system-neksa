<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return Product::where('active', true)->with('category')->get();
    }

    public function show($id)
    {
        return Product::with(['category', 'supplier'])->findOrFail($id);
    }

    public function findByBarcode($barcode)
    {
        $product = Product::where('barcode', $barcode)
            ->where('active', true)
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        return $product;
    }
}
