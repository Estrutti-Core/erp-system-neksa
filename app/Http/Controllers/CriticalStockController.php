<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;

class CriticalStockController extends Controller
{
    /**
     * Display products with critical stock levels.
     */
    public function index()
    {
        $criticalProducts = Product::with(['category', 'supplier'])
            ->whereRaw('stock_balance <= min_stock')
            ->where('active', true)
            ->orderBy('stock_balance')
            ->get();

        return Inertia::render('Relatorios/CriticalStockPage', [
            'criticalProducts' => $criticalProducts,
        ]);
    }
}
