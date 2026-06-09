<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Supplier::class, 'supplier');
    }

    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('document', 'like', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            })
            ->withCount('purchaseOrders')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        return view('suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'document'      => 'nullable|string|max:14|unique:suppliers,document',
            'document_type' => 'nullable|string|in:cpf,cnpj',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:255',
        ], [
            'name.required' => 'O nome do fornecedor é obrigatório.',
            'document.unique' => 'Este documento já está cadastrado para outro fornecedor.',
        ]);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('success', 'Fornecedor cadastrado com sucesso!');
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load(['purchaseOrders' => function ($q) {
            $q->latest()->limit(10);
        }]);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'document'      => 'nullable|string|max:14|unique:suppliers,document,' . $supplier->id,
            'document_type' => 'nullable|string|in:cpf,cnpj',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:255',
        ], [
            'name.required' => 'O nome do fornecedor é obrigatório.',
            'document.unique' => 'Este documento já está cadastrado para outro fornecedor.',
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Fornecedor atualizado com sucesso!');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Fornecedor removido com sucesso!');
    }
}
