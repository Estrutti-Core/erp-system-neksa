<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'subtotal' => 'required|numeric',
            'discount' => 'required|numeric',
            'total' => 'required|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric',
            'items.*.total' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            // Get next sale number from sequence
            $saleNumber = DB::select("SELECT nextval('sales_number_seq') as val")[0]->val;

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'customer_id' => $request->customer_id,
                'user_id' => $request->user()->id,
                'payment_method_id' => $request->payment_method_id,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount,
                'total' => $request->total,
                'status' => 'completed',
            ]);

            foreach ($request->items as $itemData) {
                $product = Product::lockForUpdate()->find($itemData['product_id']);
                
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'cost_price_at_time' => $product->cost_price,
                    'total' => $itemData['total'],
                ]);

                // Update stock
                $product->decrement('stock_balance', $itemData['quantity']);

                // Record movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'quantity' => -$itemData['quantity'],
                    'reason' => "Venda #{$sale->id}",
                    'user_id' => $request->user()->id,
                ]);
            }

            return response()->json($sale->load('items.product'), 201);
        });
    }

    public function index()
    {
        return Sale::with(['customer', 'user', 'paymentMethod'])->latest()->paginate(20);
    }
}
