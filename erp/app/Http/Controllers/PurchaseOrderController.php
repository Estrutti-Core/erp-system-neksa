<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Enums\PurchaseOrderStatus;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {
        $this->authorizeResource(PurchaseOrder::class, 'purchase_order');
    }

    public function index(Request $request): View
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'creator'])
            ->when($request->search, function ($q, $search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'ilike', "%{$search}%");
                  });
            })
            ->when($request->status, function ($q, $status) {
                $q->where('status', $status);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('purchase_orders.index', compact('orders'));
    }

    public function create(): View
    {
        $suppliers = Supplier::orderBy('name')->get();
        $products  = Product::productsOnly()->active()->orderBy('name')->get();

        return view('purchase_orders.create', compact('suppliers', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items'       => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_cost'  => 'required|numeric|min:0.00',
        ], [
            'supplier_id.required' => 'O fornecedor é obrigatório.',
            'items.required'       => 'Você precisa adicionar pelo menos um produto ao pedido.',
            'items.min'            => 'Você precisa adicionar pelo menos um produto ao pedido.',
            'items.*.quantity.min' => 'A quantidade deve ser maior que zero.',
            'items.*.unit_cost.min' => 'O custo unitário não pode ser negativo.',
        ]);

        DB::transaction(function () use ($request) {
            $totalAmount = 0;
            $itemsData   = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty     = (float) $item['quantity'];
                $cost    = (float) $item['unit_cost'];
                $total   = $qty * $cost;
                $totalAmount += $total;

                $itemsData[] = [
                    'product_id'  => $product->id,
                    'description' => $product->name,
                    'quantity'    => $qty,
                    'unit'        => $product->commercial_unit ?? 'UN',
                    'unit_cost'   => $cost,
                    'total_cost'  => $total,
                ];
            }

            $order = PurchaseOrder::create([
                'supplier_id'  => $request->supplier_id,
                'status'       => PurchaseOrderStatus::Draft,
                'total_amount' => $totalAmount,
                'created_by'   => auth()->id(),
            ]);

            foreach ($itemsData as $iData) {
                $order->items()->create($iData);
            }
        });

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Pedido de compra cadastrado com sucesso!');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'items.product', 'inventoryConferences.checker', 'creator']);
        $pendingBalances = $this->purchaseOrderService->getPendingBalances($purchaseOrder);

        return view('purchase_orders.show', compact('purchaseOrder', 'pendingBalances'));
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Apenas pedidos em rascunho podem ser editados.');
        }

        $suppliers = Supplier::orderBy('name')->get();
        $products  = Product::productsOnly()->active()->orderBy('name')->get();
        $purchaseOrder->load('items');

        return view('purchase_orders.edit', compact('purchaseOrder', 'suppliers', 'products'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Apenas pedidos em rascunho podem ser alterados.');
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items'       => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_cost'  => 'required|numeric|min:0.00',
        ], [
            'supplier_id.required' => 'O fornecedor é obrigatório.',
            'items.required'       => 'Você precisa adicionar pelo menos um produto ao pedido.',
            'items.min'            => 'Você precisa adicionar pelo menos um produto ao pedido.',
        ]);

        DB::transaction(function () use ($request, $purchaseOrder) {
            $totalAmount = 0;
            $purchaseOrder->items()->delete();

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty     = (float) $item['quantity'];
                $cost    = (float) $item['unit_cost'];
                $total   = $qty * $cost;
                $totalAmount += $total;

                $purchaseOrder->items()->create([
                    'product_id'  => $product->id,
                    'description' => $product->name,
                    'quantity'    => $qty,
                    'unit'        => $product->commercial_unit ?? 'UN',
                    'unit_cost'   => $cost,
                    'total_cost'  => $total,
                ]);
            }

            $purchaseOrder->update([
                'supplier_id'  => $request->supplier_id,
                'total_amount' => $totalAmount,
            ]);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Pedido de compra atualizado com sucesso!');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Apenas pedidos em rascunho podem ser excluídos.');
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Pedido de compra excluído com sucesso!');
    }

    /**
     * Envia o pedido de compra (Ordered).
     */
    public function order(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        try {
            $this->purchaseOrderService->order($purchaseOrder, auth()->user());
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('success', 'Pedido de compra emitido e enviado com sucesso!');
        } catch (ValidationException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors($e->errors());
        }
    }

    /**
     * Cancela o pedido de compra.
     */
    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        try {
            $this->purchaseOrderService->cancel($purchaseOrder, auth()->user());
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('success', 'Pedido de compra cancelado com sucesso!');
        } catch (ValidationException $e) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors($e->errors());
        }
    }
}
