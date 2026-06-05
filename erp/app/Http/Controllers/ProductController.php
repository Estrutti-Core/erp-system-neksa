<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Enums\FiscalOrigin;
use App\Enums\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request): View
    {
        $products = Product::productsOnly()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $s === 'inactive' ? $q->where('is_active', false) : $q->active())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        $fiscalOrigins = FiscalOrigin::cases();
        $productTypes = [ProductType::Product];

        return view('products.create', compact('fiscalOrigins', 'productTypes'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Item cadastrado com sucesso!');
    }

    public function show(Product $product): View
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $fiscalOrigins = FiscalOrigin::cases();
        $productTypes = [ProductType::Product];

        return view('products.edit', compact('product', 'fiscalOrigins', 'productTypes'));
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')
            ->with('success', 'Item atualizado com sucesso!');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Item removido com sucesso!');
    }

    /**
     * Endpoint JSON para autocomplete na criação do Orçamento.
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->get('q', '');
        
        $products = Product::productsOnly()
            ->active()
            ->search($term)
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'sale_price' => number_format($p->sale_price, 2, ',', '.'),
                'sale_price_raw' => $p->sale_price,
                'unit' => $p->commercial_unit,
                'type' => $p->type->value,
                'type_label' => $p->type->label(),
            ]);

        return response()->json($products);
    }
}
