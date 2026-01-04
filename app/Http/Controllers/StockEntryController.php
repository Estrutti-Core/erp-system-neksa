<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockMovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StockEntryController extends Controller
{
    /**
     * Display stock entry page.
     */
    public function index()
    {
        $products = Product::where('active', true)
            ->orderBy('name')
            ->get();

        $recentEntries = StockMovement::with(['product', 'user'])
            ->where('type', 'entry')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Estoque/StockEntryPage', [
            'products' => $products,
            'recentEntries' => $recentEntries,
        ]);
    }

    /**
     * Store a stock entry.
     */
    public function store(StockMovementRequest $request)
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($request->product_id);
            $quantity = $request->quantity;

            // Create movement record
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'type' => 'entry',
                'quantity' => $quantity,
                'reason' => $request->reason,
                'user_id' => auth()->id(),
            ]);

            // Update product stock
            $product->increment('stock_balance', $quantity);

            DB::commit();

            Log::info('Stock entry', [
                'movement_id' => $movement->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('estoque.entrada.index')
                ->with('success', 'Entrada de mercadoria registrada com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock entry failed', ['error' => $e->getMessage()]);
            
            return redirect()->route('estoque.entrada.index')
                ->with('error', 'Erro ao registrar entrada de mercadoria.');
        }
    }
}
