<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StockAdjustmentController extends Controller
{
    /**
     * Display stock adjustment page.
     */
    public function index()
    {
        $products = Product::where('active', true)
            ->orderBy('name')
            ->get();

        $criticalProducts = Product::where('active', true)
            ->whereRaw('stock_balance <= min_stock')
            ->orderBy('stock_balance')
            ->limit(10)
            ->get();

        return Inertia::render('Estoque/StockAdjustmentPage', [
            'products' => $products,
            'criticalProducts' => $criticalProducts,
        ]);
    }

    /**
     * Store a stock adjustment.
     */
    public function store(StockMovementRequest $request)
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($request->product_id);
            $beforeBalance = $product->stock_balance;
            
            // Determine if adding or removing
            $adjustmentType = $request->adjustment_type ?? 'add';
            $quantity = $request->quantity;
            
            // Calculate new balance
            if ($adjustmentType === 'remove') {
                $newBalance = max(0, $beforeBalance - $quantity);
                $actualQuantity = $beforeBalance - $newBalance; // Actual removed quantity
            } else {
                $newBalance = $beforeBalance + $quantity;
                $actualQuantity = $quantity;
            }

            // Create movement record
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => $actualQuantity,
                'reason' => $request->reason,
                'user_id' => auth()->id(),
                'metadata' => [
                    'adjustment_type' => $adjustmentType,
                    'before_balance' => $beforeBalance,
                    'after_balance' => $newBalance,
                ],
            ]);

            // Update product stock
            $product->update(['stock_balance' => $newBalance]);

            DB::commit();

            Log::info('Stock adjustment', [
                'movement_id' => $movement->id,
                'product_id' => $product->id,
                'before' => $beforeBalance,
                'after' => $newBalance,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('estoque.ajuste.index')
                ->with('success', 'Ajuste de estoque realizado com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock adjustment failed', ['error' => $e->getMessage()]);
            
            return redirect()->route('estoque.ajuste.index')
                ->with('error', 'Erro ao realizar ajuste de estoque.');
        }
    }
}
