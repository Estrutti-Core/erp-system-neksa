<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Enums\SaleStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Sale::class, 'sale');
    }

    public function index(Request $request): View
    {
        $sales = Sale::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['client'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statuses = SaleStatus::cases();

        return view('sales.index', compact('sales', 'statuses'));
    }

    public function show(Sale $sale): View
    {
        $sale->load(['client', 'clientAddress', 'items.product', 'items.service', 'quote']);
        return view('sales.show', compact('sale'));
    }

    /**
     * Gera o PDF da Venda.
     */
    public function pdf(Sale $sale): \Illuminate\Http\Response
    {
        $this->authorize('view', $sale);

        $sale->load(['client', 'clientAddress', 'items.product', 'items.service']);

        $pdf = Pdf::loadView('pdf.sale', compact('sale'))->setPaper('a4');

        $clientName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($sale->client->name));
        $number = preg_replace('/^VEN-/', '', $sale->code);
        $filename = "{$clientName}-VENDA-{$number}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Cancela a venda e devolve os itens ao estoque se controlados.
     */
    public function cancel(Sale $sale): RedirectResponse
    {
        if ($sale->status === SaleStatus::Cancelled) {
            return redirect()->back()->with('error', 'Esta venda já está cancelada.');
        }

        try {
            DB::transaction(function () use ($sale) {
                // Devolve itens ao estoque
                foreach ($sale->items as $item) {
                    if ($item->product && $item->product->is_stock_controlled && !$item->product->isService()) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }

                $sale->update(['status' => SaleStatus::Cancelled]);
            });

            return redirect()->route('sales.show', $sale)
                ->with('success', 'Venda cancelada e estoque estornado com sucesso!');
        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Erro ao cancelar venda: ' . $e->getMessage());
        }
    }
}
