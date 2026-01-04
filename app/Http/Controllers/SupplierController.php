<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->get();

        return Inertia::render('Cadastros/SuppliersPage', [
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        Log::info('Supplier created', ['id' => $supplier->id, 'name' => $supplier->name]);

        return redirect()->route('cadastros.fornecedores.index')
            ->with('success', 'Fornecedor criado com sucesso!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        Log::info('Supplier updated', ['id' => $supplier->id, 'name' => $supplier->name]);

        return redirect()->route('cadastros.fornecedores.index')
            ->with('success', 'Fornecedor atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $supplierName = $supplier->name;
        $supplier->delete();

        Log::info('Supplier deleted', ['id' => $supplier->id, 'name' => $supplierName]);

        return redirect()->route('cadastros.fornecedores.index')
            ->with('success', 'Fornecedor excluído com sucesso!');
    }
}
