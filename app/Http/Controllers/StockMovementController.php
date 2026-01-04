<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    /**
     * Display stock movements history (READ-ONLY).
     */
    public function index(Request $request)
    {
        $query = StockMovement::with(['product.category', 'user']);

        // Apply filters
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        if ($request->has('product_id') && $request->product_id !== '') {
            $query->where('product_id', $request->product_id);
        }

        $movements = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        // Calculate statistics
        $statistics = [
            'total_entries' => StockMovement::where('type', 'entry')->sum('quantity'),
            'total_exits' => StockMovement::where('type', 'exit')->sum('quantity'),
            'total_adjustments' => StockMovement::where('type', 'adjustment')->count(),
        ];

        return Inertia::render('Estoque/StockMovementsPage', [
            'movements' => $movements,
            'statistics' => $statistics,
        ]);
    }
}
