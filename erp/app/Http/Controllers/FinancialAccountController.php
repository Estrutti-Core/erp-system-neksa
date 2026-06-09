<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\FinancialAccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = FinancialAccount::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('bank_name', 'ilike', "%{$search}%");
            })
            ->with('type')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('financial_accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        $types = FinancialAccountType::all();
        return view('financial_accounts.create', compact('types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'type_id'        => 'required|exists:financial_account_types,id',
            'bank_name'      => 'nullable|string|max:255',
            'agency'         => 'nullable|string|max:50',
            'account_number' => 'nullable|string|max:50',
            'balance'        => 'required|numeric',
            'is_active'      => 'boolean',
        ], [
            'name.required'    => 'O nome da conta é obrigatório.',
            'type_id.required' => 'O tipo de conta é obrigatório.',
            'balance.required' => 'O saldo inicial é obrigatório.',
        ]);

        if (!$request->has('is_active')) {
            $validated['is_active'] = true;
        }

        FinancialAccount::create($validated);

        return redirect()->route('financial-accounts.index')
            ->with('success', 'Conta financeira cadastrada com sucesso!');
    }

    public function edit(FinancialAccount $financialAccount): View
    {
        $types = FinancialAccountType::all();
        return view('financial_accounts.edit', compact('financialAccount', 'types'));
    }

    public function update(Request $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'type_id'        => 'required|exists:financial_account_types,id',
            'bank_name'      => 'nullable|string|max:255',
            'agency'         => 'nullable|string|max:50',
            'account_number' => 'nullable|string|max:50',
            'balance'        => 'required|numeric',
            'is_active'      => 'boolean',
        ], [
            'name.required'    => 'O nome da conta é obrigatório.',
            'type_id.required' => 'O tipo de conta é obrigatório.',
            'balance.required' => 'O saldo da conta é obrigatório.',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $financialAccount->update($validated);

        return redirect()->route('financial-accounts.index')
            ->with('success', 'Conta financeira atualizada com sucesso!');
    }

    public function destroy(FinancialAccount $financialAccount): RedirectResponse
    {
        $financialAccount->delete();

        return redirect()->route('financial-accounts.index')
            ->with('success', 'Conta financeira removida com sucesso!');
    }
}
