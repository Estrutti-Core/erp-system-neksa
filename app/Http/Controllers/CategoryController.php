<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::withCount('products')
            ->orderBy('name')
            ->get();

        return Inertia::render('Cadastros/CategoriesPage', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CategoryRequest $request)
    {
        $category = Category::create($request->validated());

        Log::info('Category created', ['id' => $category->id, 'name' => $category->name]);

        return redirect()->route('cadastros.categorias.index')
            ->with('success', 'Categoria criada com sucesso!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        Log::info('Category updated', ['id' => $category->id, 'name' => $category->name]);

        return redirect()->route('cadastros.categorias.index')
            ->with('success', 'Categoria atualizada com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        // Check if category has products
        $productCount = $category->products()->count();
        
        if ($productCount > 0) {
            return redirect()->route('cadastros.categorias.index')
                ->with('error', "Não é possível excluir. Existem {$productCount} produtos nesta categoria.");
        }

        $categoryName = $category->name;
        $category->delete();

        Log::info('Category deleted', ['id' => $category->id, 'name' => $categoryName]);

        return redirect()->route('cadastros.categorias.index')
            ->with('success', 'Categoria excluída com sucesso!');
    }
}
