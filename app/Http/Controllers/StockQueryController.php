<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockQueryController extends Controller
{
    /**
     * Display stock query page with current balances.
     */
    public function index(Request $request)
    {
        $query = Product::with('category')
            ->where('active', true);

        // Apply filters
        if ($request->has('status')) {
            $status = $request->get('status');
            if ($status === 'low') {
                $query->whereRaw('stock_balance <= min_stock AND stock_balance > 0');
            } elseif ($status === 'critical') {
                $query->where('stock_balance', '<=', 0);
            } elseif ($status === 'ok') {
                $query->whereRaw('stock_balance > min_stock');
            }
        }

        $products = $query->orderBy('name')->get();

        // Calculate total stock value
        $totalValue = $products->sum(function ($product) {
            return $product->stock_balance * $product->cost_price;
        });

        return Inertia::render('Estoque/StockQueryPage', [
            'products' => $products,
            'totalValue' => $totalValue,
        ]);
    }
}
