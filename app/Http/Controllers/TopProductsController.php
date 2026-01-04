<?php

namespace App\Http\Controllers;

use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopProductsController extends Controller
{
    /**
     * Display top selling products report.
     */
    public function index()
    {
        // Aggregate sales by product
        $productStats = SaleItem::select('product_id')
            ->selectRaw('SUM(quantity) as quantity_sold')
            ->selectRaw('SUM(total) as total_revenue')
            ->selectRaw('AVG(unit_price) as avg_price')
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('quantity_sold')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->product_id,
                    'name' => $item->product->name ?? 'Produto removido',
                    'code' => $item->product->sku ?? $item->product->barcode ?? '-',
                    'quantitySold' => (float) $item->quantity_sold,
                    'totalRevenue' => (float) $item->total_revenue,
                    'avgPrice' => (float) $item->avg_price,
                ];
            });

        // Calculate totals
        $totalRevenue = $productStats->sum('totalRevenue');
        $totalItems = $productStats->sum('quantitySold');

        return Inertia::render('Relatorios/TopProductsPage', [
            'productStats' => $productStats,
            'totalRevenue' => $totalRevenue,
            'totalItems' => $totalItems,
        ]);
    }
}
