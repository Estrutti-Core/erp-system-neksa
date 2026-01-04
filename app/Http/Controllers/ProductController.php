<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::with(['category', 'supplier'])
            ->orderBy('name')
            ->get();

        $categories = Category::where('active', true)
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Cadastros/ProductsPage', [
            'products' => $products,
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request)
    {
        $product = Product::create($request->validated());

        Log::info('Product created', ['id' => $product->id, 'name' => $product->name]);

        return redirect()->route('cadastros.produtos.index')
            ->with('success', 'Produto criado com sucesso!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();
        
        // Don't allow stock_balance update via this endpoint (use stock movements)
        if (isset($data['stock_balance'])) {
            unset($data['stock_balance']);
        }

        $product->update($data);

        Log::info('Product updated', ['id' => $product->id, 'name' => $product->name]);

        return redirect()->route('cadastros.produtos.index')
            ->with('success', 'Produto atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $productName = $product->name;
        $product->delete();

        Log::info('Product deleted', ['id' => $product->id, 'name' => $productName]);

        return redirect()->route('cadastros.produtos.index')
            ->with('success', 'Produto excluído com sucesso!');
    }
}
