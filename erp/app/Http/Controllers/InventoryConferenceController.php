<?php

namespace App\Http\Controllers;

use App\Models\InventoryConference;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryConferenceController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {
        $this->authorizeResource(InventoryConference::class, 'inventory_conference');
    }

    /**
     * Inicia uma nova conferência de recebimento físico para um Pedido de Compra.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
        ]);

        $order = PurchaseOrder::findOrFail($request->purchase_order_id);

        try {
            $conference = $this->purchaseOrderService->createConference($order, auth()->user());
            return redirect()->route('inventory-conferences.show', $conference)
                ->with('success', 'Conferência física iniciada. Digite as quantidades reais recebidas.');
        } catch (ValidationException $e) {
            return redirect()->route('purchase-orders.show', $order)
                ->withErrors($e->errors());
        }
    }

    /**
     * Exibe a tela de digitação de quantidades recebidas.
     */
    public function show(InventoryConference $inventoryConference): View
    {
        $inventoryConference->load(['purchaseOrder.supplier', 'items.product', 'checker']);

        return view('inventory_conferences.show', compact('inventoryConference'));
    }

    /**
     * Encerra a conferência física e atualiza o estoque.
     */
    public function complete(Request $request, InventoryConference $inventoryConference): RedirectResponse
    {
        $this->authorize('update', $inventoryConference);

        $request->validate([
            'counts' => 'required|array',
            'counts.*' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->purchaseOrderService->completeConference(
                $inventoryConference,
                auth()->user(),
                $request->counts,
                $request->notes
            );

            return redirect()->route('purchase-orders.show', $inventoryConference->purchase_order_id)
                ->with('success', 'Conferência física concluída com sucesso! O estoque foi atualizado.');
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors($e->errors());
        }
    }
}
