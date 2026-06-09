<?php

namespace App\Http\Controllers;

use App\Actions\ConvertQuoteAction;
use App\Http\Requests\StoreQuoteRequest;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Enums\QuoteStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class QuoteController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Quote::class, 'quote');
    }

    public function index(Request $request): View
    {
        $quotes = Quote::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['client'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statuses = QuoteStatus::cases();

        return view('quotes.index', compact('quotes', 'statuses'));
    }

    public function create(): View
    {
        $clients = Client::active()->orderBy('name')->limit(50)->get();
        return view('quotes.create', compact('clients'));
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $quote = DB::transaction(function () use ($request) {
            $quote = Quote::create([
                'client_id'         => $request->client_id,
                'client_address_id' => $request->client_address_id,
                'equipment_id'      => $request->equipment_id,
                'status'            => QuoteStatus::Draft,
                'valid_until'       => $request->valid_until,
                'notes'             => $request->notes,
                'internal_notes'    => $request->internal_notes,
                'discount_amount'   => $request->discount_amount ?? 0,
                'carrier'           => $request->carrier,
                'freight_price'     => $request->freight_price ?? 0,
                'freight_type'      => $request->freight_type ?? 9,
                'volume'            => $request->volume,
                'weight_gross'      => $request->weight_gross,
                'weight_net'        => $request->weight_net,
                'delivery_deadline' => $request->delivery_deadline,
                'warranty'          => $request->warranty,
                'validity'          => $request->validity,
            ]);

            foreach ($request->items as $item) {
                $productId = !empty($item['product_id']) ? $item['product_id'] : null;
                $serviceId = !empty($item['service_id']) ? $item['service_id'] : null;
                
                $unit = 'UN';
                $type = \App\Enums\ProductType::Product;
                if ($productId) {
                    $product = \App\Models\Product::find($productId);
                    $unit = $product?->commercial_unit ?? 'UN';
                    $type = \App\Enums\ProductType::Product;
                } elseif ($serviceId) {
                    $service = \App\Models\Service::find($serviceId);
                    $unit = 'un';
                    $type = \App\Enums\ProductType::Service;
                }

                QuoteItem::create([
                    'quote_id'    => $quote->id,
                    'product_id'  => $productId,
                    'service_id'  => $serviceId,
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit'        => $unit,
                    'unit_price'  => $item['unit_price'],
                    'type'        => $type,
                ]);
            }

            $quote->recalculateTotals();

            return $quote;
        });

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Orçamento criado com sucesso!');
    }

    public function show(Quote $quote): View
    {
        $quote->load(['client', 'clientAddress', 'items.product', 'items.service']);
        return view('quotes.show', compact('quote'));
    }

    public function edit(Quote $quote): View
    {
        $quote->load(['client.addresses', 'items.product', 'items.service']);
        $clients = Client::active()->orderBy('name')->limit(50)->get();
        return view('quotes.edit', compact('quote', 'clients'));
    }

    public function update(StoreQuoteRequest $request, Quote $quote): RedirectResponse
    {
        DB::transaction(function () use ($request, $quote) {
            $quote->update([
                'client_id'         => $request->client_id,
                'client_address_id' => $request->client_address_id,
                'equipment_id'      => $request->equipment_id,
                'valid_until'       => $request->valid_until,
                'notes'             => $request->notes,
                'internal_notes'    => $request->internal_notes,
                'discount_amount'   => $request->discount_amount ?? 0,
                'carrier'           => $request->carrier,
                'freight_price'     => $request->freight_price ?? 0,
                'freight_type'      => $request->freight_type ?? 9,
                'volume'            => $request->volume,
                'weight_gross'      => $request->weight_gross,
                'weight_net'        => $request->weight_net,
                'delivery_deadline' => $request->delivery_deadline,
                'warranty'          => $request->warranty,
                'validity'          => $request->validity,
            ]);

            // Remover itens antigos e reinserir
            $quote->items()->delete();

            foreach ($request->items as $item) {
                $productId = !empty($item['product_id']) ? $item['product_id'] : null;
                $serviceId = !empty($item['service_id']) ? $item['service_id'] : null;

                $unit = 'UN';
                $type = \App\Enums\ProductType::Product;
                if ($productId) {
                    $product = \App\Models\Product::find($productId);
                    $unit = $product?->commercial_unit ?? 'UN';
                    $type = \App\Enums\ProductType::Product;
                } elseif ($serviceId) {
                    $service = \App\Models\Service::find($serviceId);
                    $unit = 'un';
                    $type = \App\Enums\ProductType::Service;
                }

                QuoteItem::create([
                    'quote_id'    => $quote->id,
                    'product_id'  => $productId,
                    'service_id'  => $serviceId,
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit'        => $unit,
                    'unit_price'  => $item['unit_price'],
                    'type'        => $type,
                ]);
            }

            $quote->recalculateTotals();
        });

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Orçamento atualizado com sucesso!');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $quote->delete();

        return redirect()->route('quotes.index')
            ->with('success', 'Orçamento removido com sucesso!');
    }

    /**
     * Realiza a conversão do orçamento para Venda ou OS.
     */
    public function convert(Quote $quote, Request $request, ConvertQuoteAction $action): RedirectResponse
    {
        $request->validate([
            'destination_type' => ['required', 'in:sale,service_order'],
        ]);

        try {
            $destination = $action->execute($quote, $request->destination_type);
            
            if ($request->destination_type === 'sale') {
                return redirect()->route('sales.show', $destination->id)
                    ->with('success', 'Orçamento convertido em Venda com sucesso!');
            } else {
                return redirect()->route('service-orders.show', $destination->id)
                    ->with('success', 'Orçamento convertido em Ordem de Serviço com sucesso!');
            }
        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Endpoint JSON para obter endereços de um cliente.
     */
    public function getAddresses(Client $client): JsonResponse
    {
        $addresses = $client->addresses->map(fn ($addr) => [
            'id' => $addr->id,
            'label' => $addr->street . ', ' . $addr->number . ' - ' . $addr->city . '/' . $addr->state . ' (' . ($addr->label ?? 'Endereço') . ')'
        ]);

        return response()->json($addresses);
    }

    /**
     * Endpoint JSON para pesquisar clientes via autocomplete.
     */
    public function searchClients(Request $request): JsonResponse
    {
        $term = $request->input('q', '');
        
        $clients = Client::query()
            ->active()
            ->search($term)
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'document' => $c->formatted_document,
            ]);

        return response()->json($clients);
    }

    /**
     * Endpoint JSON para pesquisar produtos e serviços via autocomplete.
     */
    public function searchItems(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        $products = \App\Models\Product::productsOnly()
            ->active()
            ->search($term)
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'sale_price' => number_format($p->sale_price, 2, ',', '.'),
                'sale_price_raw' => $p->sale_price,
                'unit' => $p->commercial_unit,
                'type' => 'product',
                'type_label' => 'Produto',
            ]);

        $services = \App\Models\Service::query()
            ->active()
            ->search($term)
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sku' => $s->sku,
                'sale_price' => number_format((float) $s->price, 2, ',', '.'),
                'sale_price_raw' => $s->price,
                'unit' => 'un',
                'type' => 'service',
                'type_label' => 'Serviço',
            ]);

        return response()->json($products->concat($services));
    }

    public function pdf(Quote $quote, Request $request): \Illuminate\Http\Response
    {
        $this->authorize('view', $quote);

        $quote->load(['client', 'clientAddress', 'items.product', 'items.service', 'equipment']);

        $mode = $request->query('mode', 'client');

        if ($mode === 'operational') {
            $pdf = Pdf::loadView('pdf.quote_operational', compact('quote'))->setPaper('a4');
            $docType = 'FICHA-DE-CAMPO';
        } else {
            $pdf = Pdf::loadView('pdf.quote', compact('quote'))->setPaper('a4');
            $docType = 'ORC';
        }

        $clientName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($quote->client->name));
        $number = preg_replace('/^ORC-/', '', $quote->code);
        $filename = "{$clientName}-{$docType}-{$number}.pdf";

        return $pdf->stream($filename);
    }
}
