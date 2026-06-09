<?php

namespace App\Http\Controllers;

use App\Models\PaymentCondition;
use App\Models\FinancialAccount;
use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentConditionController extends Controller
{
    public function index(): View
    {
        $conditions = PaymentCondition::with('defaultFinancialAccount')->orderBy('name')->get();
        return view('settings.payment-conditions.index', compact('conditions'));
    }

    public function create(): View
    {
        $accounts = FinancialAccount::where('is_active', true)->get();
        $methods = PaymentMethod::cases();
        return view('settings.payment-conditions.create', compact('accounts', 'methods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:cash,installments,custom'],
            'installments_count' => ['required', 'integer', 'min:1'],
            'interval_days' => ['required', 'integer', 'min:0'],
            'default_payment_method' => ['nullable', 'string'],
            'default_financial_account_id' => ['nullable', 'exists:financial_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');

        PaymentCondition::create($validated);

        return redirect()->route('payment-conditions.index')
            ->with('success', 'Condição de pagamento criada com sucesso!');
    }

    public function edit(PaymentCondition $paymentCondition): View
    {
        $accounts = FinancialAccount::where('is_active', true)->get();
        $methods = PaymentMethod::cases();
        return view('settings.payment-conditions.edit', compact('paymentCondition', 'accounts', 'methods'));
    }

    public function update(Request $request, PaymentCondition $paymentCondition): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:cash,installments,custom'],
            'installments_count' => ['required', 'integer', 'min:1'],
            'interval_days' => ['required', 'integer', 'min:0'],
            'default_payment_method' => ['nullable', 'string'],
            'default_financial_account_id' => ['nullable', 'exists:financial_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');

        $paymentCondition->update($validated);

        return redirect()->route('payment-conditions.index')
            ->with('success', 'Condição de pagamento atualizada com sucesso!');
    }

    public function destroy(PaymentCondition $paymentCondition): RedirectResponse
    {
        $paymentCondition->delete();

        return redirect()->route('payment-conditions.index')
            ->with('success', 'Condição de pagamento excluída com sucesso!');
    }
}
