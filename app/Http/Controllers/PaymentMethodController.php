<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paymentMethods = PaymentMethod::orderBy('name')->get();

        return Inertia::render('Cadastros/PaymentMethodsPage', [
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaymentMethodRequest $request)
    {
        $paymentMethod = PaymentMethod::create($request->validated());

        Log::info('PaymentMethod created', ['id' => $paymentMethod->id, 'name' => $paymentMethod->name]);

        return redirect()->route('cadastros.pagamentos.index')
            ->with('success', 'Forma de pagamento criada com sucesso!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($request->validated());

        Log::info('PaymentMethod updated', ['id' => $paymentMethod->id, 'name' => $paymentMethod->name]);

        return redirect()->route('cadastros.pagamentos.index')
            ->with('success', 'Forma de pagamento atualizada com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethodName = $paymentMethod->name;
        $paymentMethod->delete();

        Log::info('PaymentMethod deleted', ['id' => $paymentMethod->id, 'name' => $paymentMethodName]);

        return redirect()->route('cadastros.pagamentos.index')
            ->with('success', 'Forma de pagamento excluída com sucesso!');
    }
}
